'use client';

import React, { useEffect, useState, useMemo, useRef, useCallback } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { useToast } from '@/components/ui';
import { useAuth } from '@/lib/AuthContext';
import {
  getOrders,
  OrderDetail,
  getOrderCategory,
  isOrderActionRequired,
  ORDER_STATUS_META,
} from '@/lib/order';
import { cn } from '@/lib/format';
import {
  OrderStatsCards,
  OrderToolbar,
  OrderSortOption,
  OrderCard,
  OrderEmptyState,
  OrderSkeleton,
} from '@/components/orders';
import { RefreshCw, WifiOff, AlertCircle, Plus } from 'lucide-react';
import { usePolling } from '@/hooks/usePolling';

const POLLING_INTERVAL_MS = 20000; // 20 seconds

export default function CustomerOrdersPage() {
  const router = useRouter();
  const { toast } = useToast();
  const { isAuthenticated, loading: authLoading } = useAuth();

  const [orders, setOrders] = useState<OrderDetail[]>([]);
  const [initialLoading, setInitialLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [networkWarning, setNetworkWarning] = useState<string | null>(null);

  // Filter & Search & Sort states
  const [activeTab, setActiveTab] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [sortBy, setSortBy] = useState<OrderSortOption>('newest');

  // Real-time tracking states
  const [highlightedOrderIds, setHighlightedOrderIds] = useState<Set<string>>(new Set());
  const [lastUpdatedTime, setLastUpdatedTime] = useState<number | null>(null);
  const [relativeTimeText, setRelativeTimeText] = useState<string>('');

  // Refs for state comparison and polling lifecycle
  const prevOrdersMapRef = useRef<Map<string, OrderDetail>>(new Map());
  const isFirstLoadDoneRef = useRef<boolean>(false);
  const isFetchingRef = useRef<boolean>(false);
  const highlightTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Compute counts for tab badges & stats via Single Source of Truth
  const counts = useMemo(() => {
    let active = 0;
    let completed = 0;
    let cancelled = 0;

    for (const order of orders) {
      const cat = getOrderCategory(order.status);
      if (cat === 'active') active++;
      else if (cat === 'completed') completed++;
      else if (cat === 'cancelled') cancelled++;
    }

    return {
      all: orders.length,
      active,
      completed,
      cancelled,
    };
  }, [orders]);

  /**
   * Core function to fetch orders.
   * @param silent - If true, do not display full skeleton, silently merge in background.
   */
  const fetchOrdersData = useCallback(
    async (silent = false) => {
      if (isFetchingRef.current) return;
      isFetchingRef.current = true;

      if (!silent) {
        setIsRefreshing(true);
      }

      try {
        const freshOrders = await getOrders({ role: 'customer' });
        const list = freshOrders || [];

        // 1. Detect status changes if this is not the initial load
        if (isFirstLoadDoneRef.current) {
          const changedOrders: { order: OrderDetail; prevStatus: string; newStatus: string }[] = [];
          const newlyHighlighted = new Set<string>();

          for (const item of list) {
            const previous = prevOrdersMapRef.current.get(item.id);
            if (previous && previous.status !== item.status) {
              changedOrders.push({
                order: item,
                prevStatus: previous.status,
                newStatus: item.status,
              });
              newlyHighlighted.add(item.id);
            }
          }

          if (changedOrders.length > 0) {
            // Apply subtle highlight to rows
            setHighlightedOrderIds((prev) => new Set([...prev, ...newlyHighlighted]));

            if (highlightTimeoutRef.current) {
              clearTimeout(highlightTimeoutRef.current);
            }
            highlightTimeoutRef.current = setTimeout(() => {
              setHighlightedOrderIds(new Set());
            }, 6000);

            // Display toast notifications (max 3 individual, or 1 aggregated if more)
            if (changedOrders.length <= 3) {
              for (const change of changedOrders) {
                const meta = ORDER_STATUS_META[change.order.status];
                toast({
                  type: 'info',
                  title: `Pesanan #${change.order.order_number}`,
                  message: `Status diperbarui: ${meta?.label || change.order.status}`,
                  duration: 5000,
                });
              }
            } else {
              toast({
                type: 'info',
                title: `${changedOrders.length} Status Pesanan Diperbarui`,
                message: 'Beberapa status pesanan Anda telah diperbarui secara otomatis.',
                duration: 5000,
              });
            }
          }
        }

        // 2. Update reference map & orders state
        const nextMap = new Map<string, OrderDetail>();
        for (const item of list) {
          nextMap.set(item.id, item);
        }
        prevOrdersMapRef.current = nextMap;
        isFirstLoadDoneRef.current = true;

        setOrders(list);
        setError(null);
        setNetworkWarning(null);
        setLastUpdatedTime(Date.now());
      } catch (err: unknown) {
        const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
        const errMsg = errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat daftar pesanan Anda.';

        if (!isFirstLoadDoneRef.current) {
          setError(errMsg);
        } else {
          // Network warning during background poll without wiping existing list
          setNetworkWarning('Koneksi terputus. Mencoba menghubungkan kembali...');
        }
      } finally {
        isFetchingRef.current = false;
        setInitialLoading(false);
        setIsRefreshing(false);
      }
    },
    [toast]
  );

  // Initial load on mount
  useEffect(() => {
    let active = true;

    if (authLoading) return;
    if (!isAuthenticated) {
      router.push('/auth');
      return;
    }

    async function init() {
      try {
        const freshOrders = await getOrders({ role: 'customer' });
        if (!active) return;
        const list = freshOrders || [];
        const nextMap = new Map<string, OrderDetail>();
        for (const item of list) {
          nextMap.set(item.id, item);
        }
        prevOrdersMapRef.current = nextMap;
        isFirstLoadDoneRef.current = true;
        setOrders(list);
        setError(null);
        setNetworkWarning(null);
        setLastUpdatedTime(Date.now());
      } catch (err: unknown) {
        if (!active) return;
        const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
        setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat daftar pesanan Anda.');
      } finally {
        if (active) {
          setInitialLoading(false);
        }
      }
    }

    init();

    return () => {
      active = false;
    };
  }, [authLoading, isAuthenticated, router]);

  // Relative time counter effect (updates "Diperbarui X detik lalu" every 1s)
  useEffect(() => {
    if (!lastUpdatedTime) return;

    const updateRelativeText = () => {
      const diffSec = Math.floor((Date.now() - lastUpdatedTime) / 1000);
      if (diffSec < 5) {
        setRelativeTimeText('Diperbarui baru saja');
      } else if (diffSec < 60) {
        setRelativeTimeText(`Diperbarui ${diffSec} detik lalu`);
      } else {
        const mins = Math.floor(diffSec / 60);
        setRelativeTimeText(`Diperbarui ${mins} menit lalu`);
      }
    };

    updateRelativeText();
    const timer = setInterval(updateRelativeText, 1000);
    return () => clearInterval(timer);
  }, [lastUpdatedTime]);

  // Real-time silent polling with visibility guard & immediate refocus refetch
  usePolling(() => fetchOrdersData(true), POLLING_INTERVAL_MS, {
    enabled: isAuthenticated,
  });

  // Cleanup highlight timeout on unmount
  useEffect(() => {
    return () => {
      if (highlightTimeoutRef.current) {
        clearTimeout(highlightTimeoutRef.current);
      }
    };
  }, []);

  // Client-side filtering, searching, and sorting
  const processedOrders = useMemo(() => {
    let result = [...orders];

    // 1. Tab Filter
    if (activeTab === 'active') {
      result = result.filter((o) => getOrderCategory(o.status) === 'active');
    } else if (activeTab === 'completed') {
      result = result.filter((o) => getOrderCategory(o.status) === 'completed');
    } else if (activeTab === 'cancelled') {
      result = result.filter((o) => getOrderCategory(o.status) === 'cancelled');
    }

    // 2. Search Query (Order Number, Store Name, Services, Pickup Address)
    if (searchQuery.trim().length > 0) {
      const q = searchQuery.toLowerCase().trim();
      result = result.filter((o) => {
        const orderNum = (o.order_number || '').toLowerCase();
        const storeName = (o.store_name || '').toLowerCase();
        const address = (o.pickup_address?.address_text || '').toLowerCase();
        const items = (o.items || [])
          .map((i) => (i.service_name || '').toLowerCase())
          .join(' ');

        return (
          orderNum.includes(q) ||
          storeName.includes(q) ||
          address.includes(q) ||
          items.includes(q)
        );
      });
    }

    // 3. Sorting (with urgent action items prioritized to top in 'active' / 'all' tabs)
    result.sort((a, b) => {
      if (activeTab === 'active' || activeTab === 'all') {
        const aNeedsAction = isOrderActionRequired(a.status) ? 1 : 0;
        const bNeedsAction = isOrderActionRequired(b.status) ? 1 : 0;
        if (aNeedsAction !== bNeedsAction) {
          return bNeedsAction - aNeedsAction;
        }
      }

      if (sortBy === 'newest') {
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
      }
      if (sortBy === 'oldest') {
        return new Date(a.created_at).getTime() - new Date(b.created_at).getTime();
      }
      if (sortBy === 'highest_amount') {
        const amountA = a.final_amount !== null && a.final_amount !== undefined ? a.final_amount : a.estimated_amount;
        const amountB = b.final_amount !== null && b.final_amount !== undefined ? b.final_amount : b.estimated_amount;
        return amountB - amountA;
      }
      if (sortBy === 'lowest_amount') {
        const amountA = a.final_amount !== null && a.final_amount !== undefined ? a.final_amount : a.estimated_amount;
        const amountB = b.final_amount !== null && b.final_amount !== undefined ? b.final_amount : b.estimated_amount;
        return amountA - amountB;
      }
      return 0;
    });

    return result;
  }, [orders, activeTab, searchQuery, sortBy]);

  return (
    <PublicLayout>
      {/* 1. Hero Header Section (Navy-900 with Wave & Bubbles) */}
      <div className="bg-navy-900 text-white relative overflow-hidden pt-8 pb-14 px-4 sm:px-6 lg:px-8 select-none">
        {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
        <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
          {/* Bubble 1 (Top Left) */}
          <div className="absolute top-4 left-10 w-24 h-24 rounded-full border border-sky-300/30 bg-sky-400/10 backdrop-blur-[1px] shadow-inner animate-gentle-float">
            <div className="w-2.5 h-2.5 rounded-full bg-white/60 ml-3.5 mt-3.5" />
          </div>

          {/* Bubble 2 (Top Right) */}
          <div className="absolute top-6 right-16 w-32 h-32 rounded-full border border-sky-300/20 bg-sky-300/10 animate-gentle-float-slow">
            <div className="w-3.5 h-3.5 rounded-full bg-white/50 ml-4 mt-4" />
          </div>
        </div>

        {/* Main Hero Content */}
        <div className="max-w-5xl mx-auto relative z-10 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                  Pesanan Saya
                </h1>
                {relativeTimeText && (
                  <span className="text-xs text-sky-200/90 font-medium bg-navy-800/80 px-2.5 py-1 rounded-lg border border-navy-700/80">
                    {relativeTimeText}
                  </span>
                )}
              </div>
              <p className="text-xs text-navy-200 leading-relaxed">
                Pantau status cucian, pembayaran, dan riwayat pesanan Anda.
              </p>
            </div>

            {/* Action Controls in Header */}
            <div className="flex items-center gap-2 shrink-0 self-start sm:self-end">
              <button
                type="button"
                onClick={() => fetchOrdersData(false)}
                disabled={isRefreshing || initialLoading}
                className="w-10 h-10 flex items-center justify-center text-navy-200 hover:text-white hover:bg-navy-800 rounded-xl border border-navy-700 bg-navy-800/90 transition-all cursor-pointer disabled:opacity-50 shadow-2xs"
                aria-label="Perbarui status"
                title="Perbarui status data pesanan"
              >
                <RefreshCw
                  className={cn(
                    'w-4 h-4 text-sky-300 transition-transform',
                    isRefreshing && 'animate-spin text-white'
                  )}
                  strokeWidth={1.75}
                />
              </button>

              <Link
                href="/stores"
                className="inline-flex items-center gap-1.5 px-4 py-2.5 bg-navy-700 hover:bg-navy-800 active:scale-95 text-white text-xs sm:text-sm font-bold rounded-xl transition-all shadow-2xs border border-navy-600/40"
              >
                <Plus className="w-4 h-4 text-sky-300" strokeWidth={2} />
                <span>Pesan laundry</span>
              </Link>
            </div>
          </div>
        </div>

        {/* Wave SVG Divider at Bottom of Hero */}
        <div className="absolute -bottom-1 left-0 right-0 pointer-events-none overflow-hidden leading-none z-10" aria-hidden="true">
          <svg
            viewBox="0 0 1440 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className="w-full h-8 sm:h-12 text-slate-50 preserve-3d"
          >
            <path
              d="M0 20C240 45 480 5 720 25C960 45 1200 10 1440 20V48H0V20Z"
              fill="currentColor"
            />
          </svg>
        </div>
      </div>

      {/* 2. Main Body Content (Slate-50) */}
      <div className="bg-slate-50 min-h-[calc(100vh-14rem)] pb-12 pt-2">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 sm:-mt-7 relative z-20 space-y-5">
          {/* Network Polling Warning Banner */}
          {networkWarning && (
            <div className="p-3.5 bg-amber-50 border border-amber-200/90 rounded-2xl flex items-center gap-2.5 text-xs text-amber-900 shadow-2xs">
              <WifiOff className="w-4 h-4 text-amber-600 shrink-0" strokeWidth={1.75} />
              <span className="font-medium">{networkWarning}</span>
            </div>
          )}

          {/* Summary Metric Chips */}
          <section aria-label="Ringkasan status pesanan">
            <OrderStatsCards
              counts={counts}
              loading={initialLoading}
              activeTab={activeTab}
              onTabChange={setActiveTab}
            />
          </section>

          {/* Filter Tabs & Toolbar Card */}
          <section className="bg-white p-3 sm:p-4 rounded-3xl border border-navy-100/90 shadow-2xs" aria-label="Filter dan pencarian pesanan">
            {/* Toolbar: Search + Sort in 1 Row */}
            <OrderToolbar
              searchQuery={searchQuery}
              onSearchChange={setSearchQuery}
              sortBy={sortBy}
              onSortChange={setSortBy}
            />
          </section>

          {/* Orders List Container */}
          <main aria-label="Daftar pesanan">
            {/* Loading Skeleton */}
            {initialLoading && <OrderSkeleton />}

            {/* Error State */}
            {!initialLoading && error && (
              <div className="bg-white border border-rose-200 rounded-3xl p-8 sm:p-10 text-center space-y-3 shadow-2xs">
                <div className="w-12 h-12 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                  <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
                </div>
                <div className="space-y-1">
                  <h2 className="text-sm sm:text-base font-bold text-navy-900">Tidak dapat memuat daftar pesanan</h2>
                  <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{error}</p>
                </div>
                <button
                  type="button"
                  onClick={() => fetchOrdersData(false)}
                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
                >
                  <RefreshCw className="w-3.5 h-3.5" strokeWidth={1.75} />
                  <span>Coba lagi</span>
                </button>
              </div>
            )}

            {/* Empty State */}
            {!initialLoading && !error && processedOrders.length === 0 && (
              <OrderEmptyState
                activeTab={activeTab}
                searchQuery={searchQuery}
                onResetSearch={() => setSearchQuery('')}
                onResetFilter={() => {
                  setActiveTab('all');
                  setSearchQuery('');
                }}
              />
            )}

            {/* Orders Card List (Independent Squircle Cards in space-y-4) */}
            {!initialLoading && !error && processedOrders.length > 0 && (
              <div className="space-y-4">
                {processedOrders.map((order) => (
                  <OrderCard
                    key={order.id}
                    order={order}
                    isHighlighted={highlightedOrderIds.has(order.id)}
                  />
                ))}
              </div>
            )}
          </main>
        </div>
      </div>
    </PublicLayout>
  );
}

'use client';

import React, { useEffect, useState, useMemo, useRef, useCallback } from 'react';
import Link from 'next/link';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { useToast } from '@/components/ui';
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
  OrderFilterTabs,
  OrderToolbar,
  OrderSortOption,
  OrderCard,
  OrderEmptyState,
  OrderSkeleton,
} from '@/components/orders';
import { RotateCw, WifiOff } from 'lucide-react';

const POLLING_INTERVAL_MS = 20000; // 20 seconds

export default function CustomerOrdersPage() {
  const { toast } = useToast();

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
  }, []);

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

  // Real-time silent polling & Visibility / Focus listeners
  useEffect(() => {
    let pollingInterval: NodeJS.Timeout | null = null;

    const startPolling = () => {
      if (pollingInterval) clearInterval(pollingInterval);
      pollingInterval = setInterval(() => {
        if (document.visibilityState === 'visible') {
          fetchOrdersData(true);
        }
      }, POLLING_INTERVAL_MS);
    };

    const stopPolling = () => {
      if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
      }
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        fetchOrdersData(true);
        startPolling();
      } else {
        stopPolling();
      }
    };

    const handleWindowFocus = () => {
      if (document.visibilityState === 'visible') {
        fetchOrdersData(true);
      }
    };

    if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
      startPolling();
    }

    if (typeof window !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);
      window.addEventListener('focus', handleWindowFocus);
    }

    return () => {
      stopPolling();
      if (typeof window !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('focus', handleWindowFocus);
      }
    };
  }, [fetchOrdersData, counts.active]);

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
      <div className="bg-slate-50 min-h-[calc(100vh-4rem)] py-6 sm:py-8">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          {/* Header Section */}
          <header className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-[24px] font-semibold text-slate-900 tracking-tight leading-none">
                  Pesanan
                </h1>
                {relativeTimeText && (
                  <span className="text-xs text-slate-500 font-normal">
                    {relativeTimeText}
                  </span>
                )}
              </div>
              <p className="text-xs text-slate-500 mt-1">
                Riwayat transaksi dan status pengerjaan laundry Anda.
              </p>
            </div>

            {/* Action Controls in Header */}
            <div className="flex items-center gap-2 shrink-0 self-start sm:self-center">
              <button
                type="button"
                onClick={() => fetchOrdersData(false)}
                disabled={isRefreshing || initialLoading}
                className="w-9 h-9 flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-200/60 rounded-lg border border-slate-200 bg-white transition-colors cursor-pointer disabled:opacity-50"
                aria-label="Muat ulang"
                title="Muat ulang data pesanan"
              >
                <RotateCw
                  className={cn(
                    'w-4 h-4 transition-transform',
                    isRefreshing && 'animate-spin text-[#1A3A5C]'
                  )}
                  strokeWidth={1.75}
                />
              </button>

              <Link
                href="/stores"
                className="inline-flex items-center justify-center px-4 py-2 bg-[#1A3A5C] hover:bg-[#152e4a] text-white text-xs sm:text-sm font-medium rounded-lg transition-colors min-h-[36px]"
              >
                Pesan laundry
              </Link>
            </div>
          </header>

          {/* Network Polling Warning Banner */}
          {networkWarning && (
            <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-2 text-xs text-amber-800">
              <WifiOff className="w-4 h-4 text-amber-600 shrink-0" />
              <span>{networkWarning}</span>
            </div>
          )}

          {/* Summary Metric Text */}
          <section aria-label="Ringkasan status pesanan">
            <OrderStatsCards counts={counts} loading={initialLoading} />
          </section>

          {/* Filter Tabs & Search/Sort */}
          <section className="space-y-4" aria-label="Filter dan pencarian pesanan">
            {/* Underline Tabs */}
            <OrderFilterTabs
              activeTab={activeTab}
              onChange={setActiveTab}
              counts={counts}
            />

            {/* Toolbar: Search + Sort in 1 Row */}
            <OrderToolbar
              searchQuery={searchQuery}
              onSearchChange={setSearchQuery}
              sortBy={sortBy}
              onSortChange={setSortBy}
            />
          </section>

          {/* Main Content */}
          <main aria-label="Daftar pesanan">
            {/* Loading Skeleton */}
            {initialLoading && <OrderSkeleton />}

            {/* Error State */}
            {!initialLoading && error && (
              <div className="p-8 text-center bg-white border border-slate-200 rounded-lg space-y-3">
                <p className="text-sm text-slate-700">{error}</p>
                <button
                  type="button"
                  onClick={() => fetchOrdersData(false)}
                  className="inline-flex items-center px-4 py-2 bg-[#1A3A5C] text-white text-xs font-medium rounded-lg hover:bg-[#152e4a] cursor-pointer"
                >
                  Coba lagi
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

            {/* Orders List (divide-y layout) */}
            {!initialLoading && !error && processedOrders.length > 0 && (
              <div className="divide-y divide-slate-100 border border-slate-200 rounded-lg bg-white overflow-hidden">
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

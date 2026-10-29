'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { DynamicStoreMap } from '@/components/map/DynamicStoreMap';
import { StoreCard } from '@/components/store/StoreCard';
import {
  getNearbyStores,
  getServiceCategories,
  StoreItem,
  ServiceCategoryItem,
  StoreNearbyMeta,
} from '@/lib/store';
import { getAddresses, AddressItem } from '@/lib/profile';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getOrders, OrderDetail, ORDER_STATUS_META } from '@/lib/order';
import { isAuthenticated } from '@/lib/auth';
import { useAuth } from '@/lib/AuthContext';
import {
  Search,
  MapPin,
  SlidersHorizontal,
  Navigation,
  Package,
  ArrowRight,
  RefreshCw,
  AlertCircle,
  Store as StoreIcon,
} from 'lucide-react';

const DEFAULT_LAT = -6.8900;
const DEFAULT_LNG = 107.6150;
const FALLBACK_LOCATION_LABEL = 'Lokasi belum diatur';

function formatAddressLocationLabel(addr: AddressItem): string {
  if (addr.label && addr.city && !addr.label.toLowerCase().includes(addr.city.toLowerCase())) {
    return `${addr.label} (${addr.city})`;
  }
  return addr.label || addr.city || addr.address_text;
}

export default function StoreSearchPage() {
  const { user } = useAuth();
  const [customerLocation, setCustomerLocation] = useState<{ lat: number; lng: number }>({
    lat: DEFAULT_LAT,
    lng: DEFAULT_LNG,
  });
  const [locationLabel, setLocationLabel] = useState<string>(FALLBACK_LOCATION_LABEL);
  const [locationResolved, setLocationResolved] = useState<boolean>(false);
  const [isLocating, setIsLocating] = useState<boolean>(false);

  // Tracks if the user intentionally overrode location manually (drop-down, map drag, or GPS click)
  const isManualOverrideRef = useRef<boolean>(false);

  // Filters
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [radiusKm, setRadiusKm] = useState<number>(10);
  const [showOnlyServiceable, setShowOnlyServiceable] = useState<boolean>(false);

  // Data & State
  const [stores, setStores] = useState<StoreItem[]>([]);
  const [meta, setMeta] = useState<StoreNearbyMeta | null>(null);
  const [categories, setCategories] = useState<ServiceCategoryItem[]>([]);
  const [savedAddresses, setSavedAddresses] = useState<AddressItem[]>([]);
  const [activeOrder, setActiveOrder] = useState<OrderDetail | null>(null);
  const [selectedStoreId, setSelectedStoreId] = useState<string | null>(null);

  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Request browser GPS with strict 5-second timeout
  const requestBrowserGps = useCallback((timeoutMs = 5000): Promise<{ lat: number; lng: number } | null> => {
    return new Promise((resolve) => {
      if (typeof window === 'undefined' || !navigator.geolocation) {
        resolve(null);
        return;
      }
      let completed = false;
      const timer = setTimeout(() => {
        if (!completed) {
          completed = true;
          resolve(null);
        }
      }, timeoutMs);

      navigator.geolocation.getCurrentPosition(
        (pos) => {
          if (!completed) {
            completed = true;
            clearTimeout(timer);
            resolve({
              lat: pos.coords.latitude,
              lng: pos.coords.longitude,
            });
          }
        },
        () => {
          if (!completed) {
            completed = true;
            clearTimeout(timer);
            resolve(null);
          }
        },
        { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 60000 }
      );
    });
  }, []);

  // Listen for address changes from profile or other tabs (only if user hasn't chosen manual override)
  useEffect(() => {
    const onAddressChanged = async () => {
      if (isAuthenticated() && !isManualOverrideRef.current) {
        try {
          const addrRes = await getAddresses();
          setSavedAddresses(addrRes);
          const activeAddr = addrRes.find((a) => a.is_default) || addrRes[0];
          if (activeAddr && activeAddr.latitude != null && activeAddr.longitude != null) {
            const lat = Number(activeAddr.latitude);
            const lng = Number(activeAddr.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
              setCustomerLocation({ lat, lng });
              setLocationLabel(formatAddressLocationLabel(activeAddr));
              setLocationResolved(true);
            }
          }
        } catch {
          // ignore
        }
      }
    };
    window.addEventListener('address-changed', onAddressChanged);
    window.addEventListener('focus', onAddressChanged);
    return () => {
      window.removeEventListener('address-changed', onAddressChanged);
      window.removeEventListener('focus', onAddressChanged);
    };
  }, []);

  // Load Initial Metadata & Resolve Location
  useEffect(() => {
    let isCancelled = false;

    async function loadInitialData() {
      // 1. Fetch service categories
      try {
        const catRes = await getServiceCategories();
        if (!isCancelled) {
          setCategories(catRes);
        }
      } catch {
        // ignore
      }

      // 2. Fetch active orders if authenticated
      if (isAuthenticated()) {
        try {
          const orders = await getOrders({ role: 'customer' });
          const ongoing = (Array.isArray(orders) ? orders : []).find(
            (o: OrderDetail) =>
              o.status !== 'COMPLETED' &&
              o.status !== 'CANCELLED' &&
              o.status !== 'PAYMENT_FAILED' &&
              o.status !== 'PAYMENT_EXPIRED'
          );
          if (ongoing && !isCancelled) {
            setActiveOrder(ongoing);
          }
        } catch {
          // ignore
        }
      }

      // 3. Resolve location with strict priorities:
      // If user has already made a manual selection before async finishes, do NOT overwrite
      if (isManualOverrideRef.current || isCancelled) {
        return;
      }

      let resolvedCoords: { lat: number; lng: number } | null = null;
      let resolvedLabel: string = FALLBACK_LOCATION_LABEL;

      // Priority (a): Default / first saved address of authenticated customer
      if (isAuthenticated()) {
        try {
          const addrRes = await getAddresses();
          if (!isCancelled) {
            setSavedAddresses(addrRes);
          }
          const activeAddr = addrRes.find((a) => a.is_default) || addrRes[0];
          if (activeAddr && activeAddr.latitude != null && activeAddr.longitude != null) {
            const lat = Number(activeAddr.latitude);
            const lng = Number(activeAddr.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
              resolvedCoords = { lat, lng };
              resolvedLabel = formatAddressLocationLabel(activeAddr);
            }
          }
        } catch {
          // Jika getAddresses gagal (network/401), lanjut ke GPS tanpa macet di skeleton
        }
      }

      if (isManualOverrideRef.current || isCancelled) {
        return;
      }

      // Priority (b): Browser GPS with 5s timeout if (a) did not yield coordinates
      if (!resolvedCoords) {
        const gpsCoords = await requestBrowserGps(5000);
        if (gpsCoords && !isManualOverrideRef.current && !isCancelled) {
          resolvedCoords = gpsCoords;
          resolvedLabel = 'Lokasi GPS Saat Ini';
        }
      }

      if (isManualOverrideRef.current || isCancelled) {
        return;
      }

      // Priority (c): Fallback with "Lokasi belum diatur" label
      if (!resolvedCoords) {
        resolvedCoords = { lat: DEFAULT_LAT, lng: DEFAULT_LNG };
        resolvedLabel = FALLBACK_LOCATION_LABEL;
      }

      if (!isCancelled) {
        setCustomerLocation(resolvedCoords);
        setLocationLabel(resolvedLabel);
        setLocationResolved(true);
      }
    }

    loadInitialData();

    return () => {
      isCancelled = true;
    };
  }, [requestBrowserGps]);

  // Fetch Nearby Stores with AbortController
  const fetchStores = useCallback(
    async (signal?: AbortSignal) => {
      setLoading(true);
      setError(null);
      try {
        const res = await getNearbyStores(
          {
            lat: customerLocation.lat,
            lng: customerLocation.lng,
            radius_km: radiusKm,
            search: searchQuery.trim() || undefined,
            category_slug: selectedCategory !== 'all' ? selectedCategory : undefined,
          },
          { signal }
        );
        setStores(res.stores);
        setMeta(res.meta);
      } catch (err: unknown) {
        if ((err as { name?: string })?.name === 'AbortError' || signal?.aborted) {
          return;
        }
        setError(formatFriendlyErrorMessage(err, 'Gagal memuat daftar toko.'));
      } finally {
        if (!signal?.aborted) {
          setLoading(false);
        }
      }
    },
    [customerLocation, radiusKm, searchQuery, selectedCategory]
  );

  // Fetch stores ONLY after location is resolved, with AbortController
  useEffect(() => {
    if (!locationResolved) return;

    const controller = new AbortController();
    fetchStores(controller.signal);

    return () => {
      controller.abort();
    };
  }, [locationResolved, fetchStores]);

  // Manual Trigger: Browser GPS Geolocation
  const handleUseGPS = () => {
    if (!navigator.geolocation) {
      alert('Perangkat Anda tidak mendukung geolokasi browser.');
      return;
    }
    isManualOverrideRef.current = true;
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsLocating(false);
        setCustomerLocation({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
        });
        setLocationLabel('Lokasi GPS Saat Ini');
        setLocationResolved(true);
      },
      (err) => {
        setIsLocating(false);
        alert(`Gagal mendeteksi lokasi GPS: ${err.message}. Pastikan izin lokasi aktif.`);
      },
      { enableHighAccuracy: true, timeout: 5000 }
    );
  };

  // Manual Trigger: Select Saved Address
  const handleSelectSavedAddress = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const addrId = e.target.value;
    if (!addrId) return;
    const addr = savedAddresses.find((a) => a.id === addrId);
    if (!addr) return;

    isManualOverrideRef.current = true;

    if (addr.latitude != null && addr.longitude != null) {
      const lat = Number(addr.latitude);
      const lng = Number(addr.longitude);
      if (!isNaN(lat) && !isNaN(lng)) {
        setCustomerLocation({ lat, lng });
        setLocationLabel(formatAddressLocationLabel(addr));
        setLocationResolved(true);
        return;
      }
    }

    // Jika koordinatnya kosong, gunakan titik default
    setCustomerLocation({ lat: DEFAULT_LAT, lng: DEFAULT_LNG });
    setLocationLabel(FALLBACK_LOCATION_LABEL);
    setLocationResolved(true);
  };

  // Manual Trigger: Map Pin Click/Drag
  const handleLocationChange = (lat: number, lng: number) => {
    isManualOverrideRef.current = true;
    setCustomerLocation({ lat, lng });
    setLocationLabel(`Koordinat Kustom (${lat.toFixed(4)}, ${lng.toFixed(4)})`);
    setLocationResolved(true);
  };

  const filteredStores = React.useMemo(() => {
    if (!showOnlyServiceable) return stores;
    return stores.filter((s) => s.is_serviceable !== false);
  }, [stores, showOnlyServiceable]);

  return (
    <PublicLayout>
      {/* 1. Active Order Banner Strip (if any) */}
      {activeOrder && (
        <div className="bg-navy-950 text-white px-4 py-2.5 text-xs border-b border-navy-800">
          <div className="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 truncate">
              <Package className="w-4 h-4 text-sky-400 shrink-0" strokeWidth={1.75} />
              <span className="font-semibold text-white">Pesanan aktif:</span>
              <span className="font-mono text-sky-200">{activeOrder.order_number}</span>
              <span className="text-navy-400 hidden sm:inline">·</span>
              <span className="text-navy-200 hidden sm:inline truncate font-medium">
                {ORDER_STATUS_META[activeOrder.status]?.label || activeOrder.status}
              </span>
            </div>
            <Link
              href={`/orders/${activeOrder.id}`}
              className="inline-flex items-center gap-1 font-semibold text-sky-300 hover:text-white underline shrink-0 transition-colors"
            >
              <span>Lacak pesanan</span>
              <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
            </Link>
          </div>
        </div>
      )}

      {/* 2. Hero Header Section (Navy-900 with Wave & Bubbles) */}
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
        <div className="max-w-7xl mx-auto relative z-10 space-y-4">
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                {user ? `Halo, ${user.name}! Mau cuci apa hari ini?` : 'Temukan laundry di sekitar Anda'}
              </h1>
              <div className="flex flex-wrap items-center gap-2 text-xs text-navy-200">
                <MapPin className="w-3.5 h-3.5 text-sky-400 shrink-0" strokeWidth={1.75} />
                {!locationResolved ? (
                  <span className="inline-flex items-center gap-2">
                    <span>Mendeteksi lokasi:</span>
                    <span className="inline-block w-24 h-3.5 bg-navy-800 rounded animate-pulse" />
                  </span>
                ) : (
                  <>
                    <span className="truncate">
                      Lokasi Anda: <strong className="text-white font-semibold">{locationLabel}</strong>
                    </span>
                    {locationLabel === FALLBACK_LOCATION_LABEL && (
                      <button
                        type="button"
                        onClick={handleUseGPS}
                        className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-sky-500/20 hover:bg-sky-500/30 text-sky-200 border border-sky-400/30 text-[11px] font-semibold transition-colors cursor-pointer"
                      >
                        <Navigation className="w-3 h-3 text-sky-300" />
                        <span>Pilih lokasi</span>
                      </button>
                    )}
                  </>
                )}
              </div>
            </div>

            {/* Address Selector & GPS Action */}
            <div className="flex flex-wrap items-center gap-2 shrink-0">
              {savedAddresses.length > 0 && (
                <select
                  onChange={handleSelectSavedAddress}
                  className="py-2 px-3 text-xs bg-navy-800/90 border border-navy-700 rounded-xl text-white focus:outline-hidden focus:ring-1 focus:ring-navy-400 cursor-pointer max-w-xs truncate"
                  value={
                    savedAddresses.find(
                      (a) =>
                        a.latitude != null &&
                        a.longitude != null &&
                        Number(a.latitude) === customerLocation.lat &&
                        Number(a.longitude) === customerLocation.lng
                    )?.id || ''
                  }
                >
                  <option value="" disabled className="text-slate-400">
                    Pilih alamat tersimpan...
                  </option>
                  {savedAddresses.map((addr) => (
                    <option key={addr.id} value={addr.id} className="bg-navy-900 text-white">
                      {addr.is_default ? '★ ' : ''}{formatAddressLocationLabel(addr)}
                    </option>
                  ))}
                </select>
              )}

              <button
                onClick={handleUseGPS}
                disabled={isLocating}
                className="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-navy-700 bg-navy-800/90 hover:bg-navy-700 text-xs font-semibold text-white transition-colors cursor-pointer shrink-0 shadow-2xs"
                title="Gunakan lokasi GPS perangkat Anda"
              >
                <Navigation className={`w-3.5 h-3.5 text-sky-300 ${isLocating ? 'animate-spin' : ''}`} strokeWidth={1.75} />
                <span>{isLocating ? 'Mendeteksi lokasi...' : 'Pakai lokasi saya'}</span>
              </button>
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

      {/* 3. Floating Elevated Search Hub & Filter Bar */}
      <div className="bg-slate-50 pb-6 pt-2">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-7 sm:-mt-8 relative z-20 space-y-3">
          {/* Search Bar Container */}
          <div className="bg-white rounded-2xl shadow-md border border-navy-100 p-2 sm:p-2.5 flex items-center gap-2">
            <div className="relative flex-1 flex items-center">
              <Search className="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none" strokeWidth={1.75} />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cari nama laundry, layanan, atau alamat..."
                className="w-full pl-10 pr-3.5 py-2 text-xs sm:text-sm bg-transparent focus:outline-hidden text-navy-900 placeholder:text-slate-400 font-medium"
              />
            </div>
          </div>

          {/* Desktop Filter Row */}
          <div className="hidden lg:flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-navy-100/80 shadow-2xs">
            <div className="flex items-center gap-2.5">
              <span className="text-xs font-bold text-navy-900 flex items-center gap-1.5 pl-1">
                <SlidersHorizontal className="w-3.5 h-3.5 text-navy-700" strokeWidth={1.75} />
                <span>Filter:</span>
              </span>

              {/* Category Select */}
              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="py-2 px-3 text-xs bg-slate-50 border border-slate-200 rounded-xl text-navy-900 font-medium focus:outline-hidden focus:border-navy-700 cursor-pointer"
              >
                <option value="all">Semua layanan</option>
                {categories.map((cat) => (
                  <option key={cat.id} value={cat.slug}>
                    {cat.name}
                  </option>
                ))}
              </select>

              {/* Radius Select */}
              <select
                value={radiusKm}
                onChange={(e) => setRadiusKm(Number(e.target.value))}
                className="py-2 px-3 text-xs bg-slate-50 border border-slate-200 rounded-xl text-navy-900 font-medium focus:outline-hidden focus:border-navy-700 cursor-pointer"
              >
                <option value={3}>Radius 3 km</option>
                <option value={5}>Radius 5 km</option>
                <option value={10}>Radius 10 km</option>
                <option value={25}>Radius 25 km</option>
              </select>
            </div>

            {/* Serviceable Only Checkbox (Antar jemput) */}
            <label className="inline-flex items-center gap-2 text-xs font-semibold text-navy-900 cursor-pointer select-none bg-slate-50 hover:bg-navy-50/60 border border-slate-200 px-3 py-2 rounded-xl transition-colors">
              <input
                type="checkbox"
                checked={showOnlyServiceable}
                onChange={(e) => setShowOnlyServiceable(e.target.checked)}
                className="rounded text-navy-700 focus:ring-navy-700 w-4 h-4"
              />
              <span>Antar jemput</span>
            </label>
          </div>

          {/* Mobile Native <details> for Filter */}
          <details className="lg:hidden group bg-white rounded-2xl border border-navy-100 shadow-2xs overflow-hidden">
            <summary className="flex items-center justify-between p-3.5 text-xs font-bold text-navy-900 cursor-pointer select-none">
              <span className="flex items-center gap-2">
                <SlidersHorizontal className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                <span>Filter</span>
              </span>
              <span className="text-[11px] text-slate-500 font-normal group-open:rotate-180 transition-transform">
                ▼
              </span>
            </summary>
            <div className="p-3.5 pt-0 border-t border-slate-100 space-y-3">
              <div>
                <label className="block text-[11px] font-bold text-navy-900 mb-1">Kategori Layanan</label>
                <select
                  value={selectedCategory}
                  onChange={(e) => setSelectedCategory(e.target.value)}
                  className="w-full py-2 px-3 text-xs bg-slate-50 border border-slate-200 rounded-xl text-navy-900 font-medium focus:outline-hidden"
                >
                  <option value="all">Semua layanan</option>
                  {categories.map((cat) => (
                    <option key={cat.id} value={cat.slug}>
                      {cat.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-[11px] font-bold text-navy-900 mb-1">Radius Jangkauan</label>
                <select
                  value={radiusKm}
                  onChange={(e) => setRadiusKm(Number(e.target.value))}
                  className="w-full py-2 px-3 text-xs bg-slate-50 border border-slate-200 rounded-xl text-navy-900 font-medium focus:outline-hidden"
                >
                  <option value={3}>Radius 3 km</option>
                  <option value={5}>Radius 5 km</option>
                  <option value={10}>Radius 10 km</option>
                  <option value={25}>Radius 25 km</option>
                </select>
              </div>

              <label className="flex items-center gap-2.5 text-xs font-semibold text-navy-900 cursor-pointer select-none bg-slate-50 border border-slate-200 p-2.5 rounded-xl">
                <input
                  type="checkbox"
                  checked={showOnlyServiceable}
                  onChange={(e) => setShowOnlyServiceable(e.target.checked)}
                  className="rounded text-navy-700 focus:ring-navy-700 w-4 h-4"
                />
                <span>Antar jemput</span>
              </label>
            </div>
          </details>

          {/* Mobile Native <details> for Map */}
          <details className="lg:hidden group bg-white rounded-2xl border border-navy-200/90 shadow-2xs overflow-hidden">
            <summary className="flex items-center justify-between p-3.5 text-xs font-bold text-navy-900 cursor-pointer select-none">
              <span className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                <span>Lihat peta</span>
              </span>
              <span className="text-[11px] text-navy-700 font-semibold group-open:hidden">
                Buka peta ▼
              </span>
              <span className="text-[11px] text-navy-700 font-semibold hidden group-open:inline">
                Tutup peta ▲
              </span>
            </summary>
            <div className="p-2 pt-0 border-t border-slate-100">
              <div className="h-[360px] rounded-xl overflow-hidden border border-slate-200 mt-2">
                {locationResolved ? (
                  <DynamicStoreMap
                    customerLocation={customerLocation}
                    stores={filteredStores}
                    selectedStoreId={selectedStoreId}
                    onStoreSelect={(store) => setSelectedStoreId(store.id)}
                    onLocationChange={handleLocationChange}
                    searchRadiusKm={radiusKm}
                  />
                ) : (
                  <div className="h-full w-full bg-slate-100 animate-pulse flex flex-col items-center justify-center text-slate-400 gap-2">
                    <MapPin className="w-6 h-6 animate-bounce text-slate-400" />
                    <span className="text-xs font-medium">Menentukan lokasi layanan...</span>
                  </div>
                )}
              </div>
            </div>
          </details>
        </div>

        {/* 4. Main Content: Split List & Sticky Map Grid */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
          {error ? (
            <div className="bg-white border border-rose-200 rounded-3xl p-8 sm:p-10 text-center space-y-3 shadow-2xs">
              <div className="w-12 h-12 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
              </div>
              <div className="space-y-1">
                <h2 className="text-sm sm:text-base font-bold text-navy-900">Tidak dapat memuat daftar laundry</h2>
                <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{error}</p>
              </div>
              <button
                onClick={() => fetchStores()}
                className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
              >
                <RefreshCw className="w-3.5 h-3.5" strokeWidth={1.75} />
                <span>Muat ulang</span>
              </button>
            </div>
          ) : !locationResolved ? (
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
              {/* Left Column Skeleton */}
              <div className="lg:col-span-6 space-y-3">
                <div className="h-4 w-48 bg-slate-200 rounded animate-pulse" />
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-40 bg-white border border-slate-200/90 rounded-2xl animate-pulse" />
                  ))}
                </div>
              </div>

              {/* Right Column Skeleton */}
              <div className="hidden lg:block lg:col-span-6 lg:sticky lg:top-24">
                <div className="bg-white border border-navy-200/90 rounded-3xl p-3.5 shadow-sm">
                  <div className="h-4 w-36 bg-slate-200 rounded animate-pulse mb-3" />
                  <div className="h-[520px] rounded-2xl bg-slate-100 animate-pulse border border-slate-200 flex flex-col items-center justify-center text-slate-400 gap-2">
                    <MapPin className="w-6 h-6 animate-bounce text-slate-400" />
                    <span className="text-xs font-medium">Menentukan lokasi layanan...</span>
                  </div>
                </div>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
              {/* Left Column: Stores List */}
              <div className="lg:col-span-6 space-y-3">
                <div className="flex items-center justify-between text-xs text-slate-500 pb-1">
                  <span>
                    Menampilkan <strong className="text-navy-900">{filteredStores.length}</strong> laundry
                  </span>
                  {meta?.serviceable_total !== undefined && (
                    <span className="text-xs font-medium">
                      <strong className="text-emerald-700 font-bold">{meta.serviceable_total}</strong> dalam jangkauan Anda
                    </span>
                  )}
                </div>

                {loading ? (
                  <div className="space-y-3">
                    {[1, 2, 3].map((i) => (
                      <div key={i} className="h-40 bg-white border border-slate-200/90 rounded-2xl animate-pulse" />
                    ))}
                  </div>
                ) : filteredStores.length === 0 ? (
                  <div className="bg-white border border-navy-100 rounded-3xl p-8 sm:p-12 text-center space-y-3 shadow-2xs">
                    <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center shadow-2xs">
                      <StoreIcon className="w-7 h-7" strokeWidth={1.75} />
                    </div>
                    <div className="space-y-1">
                      <h3 className="text-sm sm:text-base font-bold text-navy-900">Laundry tidak ditemukan</h3>
                      <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                        Coba perbesar radius atau gunakan kata kunci lain.
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {filteredStores.map((store) => (
                      <StoreCard
                        key={store.id}
                        store={store}
                        isSelected={selectedStoreId === store.id}
                        onSelect={(s) => setSelectedStoreId(s.id)}
                      />
                    ))}
                  </div>
                )}
              </div>

              {/* Right Column: Sticky Interactive Leaflet Map (Desktop) */}
              <div className="hidden lg:block lg:col-span-6 lg:sticky lg:top-24">
                <div className="bg-white border border-navy-200/90 rounded-3xl p-3.5 shadow-sm">
                  <div className="flex items-center justify-between text-xs text-slate-600 mb-2 px-1">
                    <span className="font-bold text-navy-900">Peta Jangkauan Layanan</span>
                    <span className="text-[11px] text-slate-400">Klik/geser pin untuk ubah lokasi</span>
                  </div>

                  <div className="h-[520px] rounded-2xl overflow-hidden border border-slate-200">
                    <DynamicStoreMap
                      customerLocation={customerLocation}
                      stores={filteredStores}
                      selectedStoreId={selectedStoreId}
                      onStoreSelect={(store) => setSelectedStoreId(store.id)}
                      onLocationChange={handleLocationChange}
                      searchRadiusKm={radiusKm}
                    />
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </PublicLayout>
  );
}

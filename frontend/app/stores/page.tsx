'use client';

import React, { useState, useEffect, useCallback } from 'react';
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
import { getOrders, OrderDetail, ORDER_STATUS_META } from '@/lib/order';
import { isAuthenticated, getStoredUser } from '@/lib/auth';
import { useAuth } from '@/lib/AuthContext';
import {
  Search,
  MapPin,
  SlidersHorizontal,
  Navigation,
  List,
  Map as MapIcon,
  Package,
  ArrowRight,
  RefreshCw,
  AlertCircle,
  Store as StoreIcon,
} from 'lucide-react';

const DEFAULT_LAT = -6.8900;
const DEFAULT_LNG = 107.6150;

export default function StoreSearchPage() {
  const { user } = useAuth();
  const [customerLocation, setCustomerLocation] = useState<{ lat: number; lng: number }>({
    lat: DEFAULT_LAT,
    lng: DEFAULT_LNG,
  });
  const [locationLabel, setLocationLabel] = useState<string>('Bandung (Titik Default)');
  const [isLocating, setIsLocating] = useState<boolean>(false);

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
  const [activeMobileView, setActiveMobileView] = useState<'list' | 'map'>('list');

  // Load Initial Metadata & Active Order
  useEffect(() => {
    async function loadInitialData() {
      try {
        const catRes = await getServiceCategories();
        setCategories(catRes);

        if (isAuthenticated()) {
          try {
            const addrRes = await getAddresses();
            setSavedAddresses(addrRes);
            const defaultAddr = addrRes.find((a) => a.is_default && a.latitude && a.longitude);
            if (defaultAddr && defaultAddr.latitude && defaultAddr.longitude) {
              setCustomerLocation({ lat: defaultAddr.latitude, lng: defaultAddr.longitude });
              setLocationLabel(defaultAddr.label || defaultAddr.address_text);
            }
          } catch {
            // ignore
          }

          // Fetch active orders
          try {
            const orders = await getOrders({ role: 'customer' });
            const ongoing = (Array.isArray(orders) ? orders : []).find(
              (o: OrderDetail) =>
                o.status !== 'COMPLETED' &&
                o.status !== 'CANCELLED' &&
                o.status !== 'PAYMENT_FAILED' &&
                o.status !== 'PAYMENT_EXPIRED'
            );
            if (ongoing) {
              setActiveOrder(ongoing);
            }
          } catch {
            // ignore
          }
        }
      } catch {
        // ignore
      }
    }
    loadInitialData();
  }, []);

  // Fetch Nearby Stores
  const fetchStores = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getNearbyStores({
        lat: customerLocation.lat,
        lng: customerLocation.lng,
        radius_km: radiusKm,
        search: searchQuery.trim() || undefined,
        category_slug: selectedCategory !== 'all' ? selectedCategory : undefined,
      });
      setStores(res.stores);
      setMeta(res.meta);
    } catch (err: any) {
      setError(err.message || 'Gagal memuat daftar toko.');
    } finally {
      setLoading(false);
    }
  }, [customerLocation, radiusKm, searchQuery, selectedCategory]);

  useEffect(() => {
    fetchStores();
  }, [fetchStores]);

  // Use Browser GPS Geolocation
  const handleUseGPS = () => {
    if (!navigator.geolocation) {
      alert('Perangkat Anda tidak mendukung geolokasi browser.');
      return;
    }
    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsLocating(false);
        setCustomerLocation({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
        });
        setLocationLabel('Lokasi GPS Saat Ini');
      },
      (err) => {
        setIsLocating(false);
        alert(`Gagal mendeteksi lokasi GPS: ${err.message}. Pastikan izin lokasi aktif.`);
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  // Select Saved Address
  const handleSelectSavedAddress = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const addrId = e.target.value;
    if (!addrId) return;
    const addr = savedAddresses.find((a) => a.id === addrId);
    if (addr && addr.latitude && addr.longitude) {
      setCustomerLocation({ lat: addr.latitude, lng: addr.longitude });
      setLocationLabel(addr.label ? `${addr.label}: ${addr.address_text}` : addr.address_text);
    }
  };

  const handleLocationChange = (lat: number, lng: number) => {
    setCustomerLocation({ lat, lng });
    setLocationLabel(`Koordinat Kustom (${lat.toFixed(4)}, ${lng.toFixed(4)})`);
  };

  const filteredStores = React.useMemo(() => {
    if (!showOnlyServiceable) return stores;
    return stores.filter((s) => s.is_serviceable !== false);
  }, [stores, showOnlyServiceable]);

  return (
    <PublicLayout>
      {/* 1. Active Order Banner Strip (if any) */}
      {activeOrder && (
        <div className="bg-slate-900 text-white px-4 py-2.5 text-xs border-b border-slate-800">
          <div className="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div className="flex items-center gap-2 truncate">
              <Package className="w-4 h-4 text-slate-300 shrink-0" strokeWidth={1.5} />
              <span className="font-semibold text-slate-200">Pesanan Berjalan:</span>
              <span className="font-mono text-slate-300">{activeOrder.order_number}</span>
              <span className="text-slate-400 hidden sm:inline">•</span>
              <span className="text-slate-300 hidden sm:inline truncate">
                {ORDER_STATUS_META[activeOrder.status]?.label || activeOrder.status}
              </span>
            </div>
            <Link
              href={`/orders/${activeOrder.id}`}
              className="inline-flex items-center gap-1 font-medium text-slate-200 hover:text-white underline shrink-0"
            >
              <span>Lacak Pesanan</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      )}

      {/* 2. Top Location & Greeting Bar */}
      <div className="bg-white border-b border-slate-200 py-3.5 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          {/* Sapaan & Location info */}
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-md bg-slate-100 flex items-center justify-center text-slate-700 shrink-0">
              <MapPin className="w-4 h-4" strokeWidth={1.5} />
            </div>
            <div>
              <h1 className="text-sm font-semibold text-slate-900 leading-tight">
                {user ? `Halo, ${user.name}` : 'Cari Outlet Laundry'}
              </h1>
              <p className="text-xs text-slate-500 truncate max-w-md">
                Titik: <span className="font-medium text-slate-700">{locationLabel}</span>
              </p>
            </div>
          </div>

          {/* Quick Location Action: Saved Addresses Dropdown & GPS Button */}
          <div className="flex items-center gap-2">
            {savedAddresses.length > 0 && (
              <select
                onChange={handleSelectSavedAddress}
                className="py-1.5 px-2.5 text-xs bg-slate-50 border border-slate-200 rounded-md text-slate-700 focus:outline-hidden focus:ring-1 focus:ring-slate-400 cursor-pointer max-w-xs truncate"
                defaultValue=""
              >
                <option value="" disabled>
                  Pilih Alamat Tersimpan...
                </option>
                {savedAddresses.map((addr) => (
                  <option key={addr.id} value={addr.id}>
                    {addr.label ? `${addr.label} (${addr.address_text.slice(0, 30)}...)` : addr.address_text.slice(0, 35)}
                  </option>
                ))}
              </select>
            )}

            <button
              onClick={handleUseGPS}
              disabled={isLocating}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-xs font-medium text-slate-700 transition-colors cursor-pointer shrink-0"
              title="Gunakan lokasi GPS perangkat Anda"
            >
              <Navigation className={`w-3.5 h-3.5 ${isLocating ? 'animate-spin' : ''}`} strokeWidth={1.5} />
              <span>{isLocating ? 'Mendeteksi...' : 'Lokasi Saya'}</span>
            </button>
          </div>
        </div>
      </div>

      {/* 3. Search & Filter Toolbar */}
      <div className="bg-slate-50 border-b border-slate-200 py-3 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-3">
          {/* Search Box */}
          <div className="relative w-full lg:w-96">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" strokeWidth={1.5} />
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Cari nama gerai, jalan, layanan..."
              className="w-full pl-9 pr-3 py-1.5 text-xs bg-white border border-slate-200 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400 text-slate-900 placeholder-slate-400"
            />
          </div>

          {/* Filter Pills & Radius Controls */}
          <div className="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            {/* Category Filter */}
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="py-1.5 px-2.5 text-xs bg-white border border-slate-200 rounded-md text-slate-700 focus:outline-hidden cursor-pointer"
            >
              <option value="all">Semua Kategori</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.slug}>
                  {cat.name}
                </option>
              ))}
            </select>

            {/* Radius Selector */}
            <select
              value={radiusKm}
              onChange={(e) => setRadiusKm(Number(e.target.value))}
              className="py-1.5 px-2.5 text-xs bg-white border border-slate-200 rounded-md text-slate-700 focus:outline-hidden cursor-pointer"
            >
              <option value={3}>Radius 3 KM</option>
              <option value={5}>Radius 5 KM</option>
              <option value={10}>Radius 10 KM</option>
              <option value={25}>Radius 25 KM</option>
            </select>

            {/* Serviceable Only Checkbox */}
            <label className="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer select-none bg-white border border-slate-200 px-2.5 py-1.5 rounded-md">
              <input
                type="checkbox"
                checked={showOnlyServiceable}
                onChange={(e) => setShowOnlyServiceable(e.target.checked)}
                className="rounded text-[#1A3A5C] focus:ring-0"
              />
              <span>Hanya Bisa Antar-Jemput</span>
            </label>

            {/* Mobile View Toggle (List vs Map) */}
            <div className="lg:hidden flex items-center bg-white border border-slate-200 rounded-md p-0.5 ml-auto">
              <button
                onClick={() => setActiveMobileView('list')}
                className={`p-1 rounded text-xs ${activeMobileView === 'list' ? 'bg-slate-900 text-white' : 'text-slate-600'}`}
                aria-label="Tampilan Daftar"
              >
                <List className="w-3.5 h-3.5" />
              </button>
              <button
                onClick={() => setActiveMobileView('map')}
                className={`p-1 rounded text-xs ${activeMobileView === 'map' ? 'bg-slate-900 text-white' : 'text-slate-600'}`}
                aria-label="Tampilan Peta"
              >
                <MapIcon className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* 4. Main Content: Split List & Map Grid */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {error ? (
          <div className="bg-white border border-rose-200 rounded-lg p-6 text-center">
            <AlertCircle className="w-8 h-8 text-rose-500 mx-auto mb-2" strokeWidth={1.5} />
            <h2 className="text-sm font-semibold text-slate-900 mb-1">Gagal Memuat Toko</h2>
            <p className="text-xs text-slate-500 mb-4">{error}</p>
            <button
              onClick={() => fetchStores()}
              className="px-3 py-1.5 bg-slate-900 text-white rounded-md text-xs font-medium hover:bg-slate-800 transition-colors cursor-pointer"
            >
              Coba Lagi
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* Left Column: Stores List */}
            <div
              className={`lg:col-span-6 space-y-3 ${
                activeMobileView === 'map' ? 'hidden lg:block' : 'block'
              }`}
            >
              <div className="flex items-center justify-between text-xs text-slate-500 pb-1">
                <span>
                  Menampilkan <strong className="text-slate-900">{filteredStores.length}</strong> outlet laundry
                </span>
                {meta?.serviceable_total !== undefined && (
                  <span>
                    <strong className="text-emerald-700">{meta.serviceable_total}</strong> dalam jangkauan
                  </span>
                )}
              </div>

              {loading ? (
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <div key={i} className="h-36 bg-white border border-slate-200 rounded-lg animate-pulse" />
                  ))}
                </div>
              ) : filteredStores.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-lg p-8 text-center">
                  <StoreIcon className="w-8 h-8 text-slate-400 mx-auto mb-2" strokeWidth={1.5} />
                  <h3 className="text-xs font-semibold text-slate-800">Tidak ada toko yang cocok</h3>
                  <p className="text-[11px] text-slate-500 mt-1">
                    Coba perluas radius pencarian atau gunakan kata kunci lain.
                  </p>
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

            {/* Right Column: Sticky Interactive Leaflet Map */}
            <div
              className={`lg:col-span-6 lg:sticky lg:top-24 ${
                activeMobileView === 'list' ? 'hidden lg:block' : 'block'
              }`}
            >
              <div className="bg-white border border-slate-200 rounded-lg p-3 shadow-xs">
                <div className="flex items-center justify-between text-xs text-slate-600 mb-2">
                  <span className="font-semibold text-slate-800">Peta Jangkauan Layanan</span>
                  <span className="text-[11px] text-slate-400">Klik/geser pin untuk ubah lokasi</span>
                </div>

                <div className="h-[420px] lg:h-[540px] rounded overflow-hidden border border-slate-200">
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
    </PublicLayout>
  );
}

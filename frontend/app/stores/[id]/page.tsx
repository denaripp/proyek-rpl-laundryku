'use client';

import React, { useEffect, useState, use } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Card,
  Button,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import { DynamicStoreMap } from '@/components/map/DynamicStoreMap';
import { StoreReviewsSection } from '@/components/rating/StoreReviewsSection';
import { getStore, StoreItem, StoreServiceItem } from '@/lib/store';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { startOrGetConversation } from '@/lib/chat';
import { formatRupiah, cn } from '@/lib/format';
import {
  ArrowLeft,
  MapPin,
  Clock,
  ShieldCheck,
  Sparkles,
  CheckCircle2,
  Timer,
  ShoppingBag,
  MessageSquare,
  Star,
  Store as StoreIcon,
  Truck,
} from 'lucide-react';

export default function StoreDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const router = useRouter();
  const resolvedParams = use(params);
  const storeId = resolvedParams.id;

  const [store, setStore] = useState<StoreItem | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [chatLoading, setChatLoading] = useState<boolean>(false);
  const [selectedCategoryTab, setSelectedCategoryTab] = useState<string>('all');

  const handleStartChat = async () => {
    if (!store) return;
    setChatLoading(true);
    try {
      const conv = await startOrGetConversation({ store_id: store.id });
      router.push(`/chat/${conv.id}`);
    } catch {
      alert('Gagal memulai percakapan. Pastikan Anda sudah login sebagai pelanggan.');
    } finally {
      setChatLoading(false);
    }
  };

  useEffect(() => {
    let ignore = false;
    getStore(storeId)
      .then((data) => {
        if (!ignore) {
          setStore(data);
          setLoading(false);
        }
      })
      .catch((err) => {
        if (!ignore) {
          setError(formatFriendlyErrorMessage(err, 'Toko tidak ditemukan.'));
          setLoading(false);
        }
      });

    return () => {
      ignore = true;
    };
  }, [storeId]);

  const handleRetry = () => {
    setLoading(true);
    setError(null);
    getStore(storeId)
      .then((data) => {
        setStore(data);
        setLoading(false);
      })
      .catch((err) => {
        setError(formatFriendlyErrorMessage(err, 'Toko tidak ditemukan.'));
        setLoading(false);
      });
  };

  // Category Tabs
  const categoryTabs = React.useMemo(() => {
    if (!store) return [];
    const tabs = [{ id: 'all', label: 'Semua Layanan', count: store.services?.length || 0 }];
    if (store.categories) {
      store.categories.forEach((cat) => {
        const count = store.services?.filter((s) => s.category_id === cat.id).length || 0;
        tabs.push({ id: cat.id, label: cat.name.split('(')[0].trim(), count });
      });
    }
    return tabs;
  }, [store]);

  // Filtered Services based on active tab
  const filteredServices = React.useMemo(() => {
    if (!store || !store.services) return [];
    if (selectedCategoryTab === 'all') return store.services;
    return store.services.filter((s) => s.category_id === selectedCategoryTab);
  }, [store, selectedCategoryTab]);

  return (
    <PublicLayout>
      <div className="bg-slate-50 min-h-[calc(100vh-5rem)] py-6 sm:py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 sm:space-y-8">
          {/* Back Navigation */}
          <div>
            <Link
              href="/stores"
              className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-navy-900 transition-colors min-h-[44px]"
            >
              <ArrowLeft className="w-4 h-4 text-teal-600" />
              <span>Kembali ke Pencarian Toko</span>
            </Link>
          </div>

          {/* Loading State */}
          {loading && (
            <div className="space-y-6">
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <Card variant="bento" className="lg:col-span-7 p-6 sm:p-8 space-y-4">
                  <div className="flex items-center gap-4">
                    <Skeleton className="w-16 h-16 rounded-2xl shrink-0" />
                    <div className="space-y-2 flex-1">
                      <Skeleton className="h-7 w-3/4 rounded-lg" />
                      <Skeleton className="h-4 w-1/2 rounded" />
                    </div>
                  </div>
                  <Skeleton className="h-16 w-full rounded-xl" />
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                    <Skeleton className="h-16 rounded-xl" />
                    <Skeleton className="h-16 rounded-xl" />
                    <Skeleton className="h-16 rounded-xl" />
                    <Skeleton className="h-16 rounded-xl" />
                  </div>
                </Card>
                <div className="lg:col-span-5 space-y-6">
                  <Card variant="bento" className="p-6">
                    <Skeleton className="h-6 w-1/3 mb-4" />
                    <Skeleton className="h-28 w-full rounded-xl" />
                  </Card>
                  <Card variant="bento" className="p-6">
                    <Skeleton className="h-6 w-1/3 mb-4" />
                    <Skeleton className="h-40 w-full rounded-xl" />
                  </Card>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {[1, 2, 3].map((i) => (
                  <Skeleton key={i} className="h-56 rounded-2xl" />
                ))}
              </div>
            </div>
          )}

          {/* Error State */}
          {!loading && error && (
            <div className="py-12">
              <ErrorState
                title="Toko Tidak Ditemukan"
                message={error}
                onRetry={handleRetry}
                className="bg-white rounded-3xl p-8 max-w-lg mx-auto border border-slate-200"
              />
            </div>
          )}

          {/* Store Detail Content */}
          {!loading && store && (
            <div className="space-y-8">
              {/* Store Header & Overview Grid (Balanced 7:5 Desktop Layout) */}
              <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                {/* Left Column: Store Profile Info Card (7 cols) */}
                <Card variant="bento" className="lg:col-span-7 p-5 sm:p-7 bg-white rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between">
                  <div className="space-y-4">
                    {/* Badges and Chat Action Bar */}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-800 border border-teal-200/80">
                          <ShieldCheck className="w-3.5 h-3.5 text-teal-600" />
                          <span>Mitra Terverifikasi</span>
                        </span>
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                          <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                          <span>Operasional Aktif</span>
                        </span>
                        <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-sky-50 text-sky-800 border border-sky-200/80">
                          <Truck className="w-3.5 h-3.5 text-sky-600" />
                          <span>Antar-Jemput: {store.service_radius_km} km</span>
                        </span>
                      </div>

                      <Button
                        variant="outline"
                        size="sm"
                        onClick={handleStartChat}
                        isLoading={chatLoading}
                        className="rounded-xl border-teal-300 text-teal-700 hover:bg-teal-50 font-semibold shadow-xs min-h-[44px]"
                      >
                        <MessageSquare className="w-4 h-4 mr-1.5 text-teal-600" />
                        Chat Toko
                      </Button>
                    </div>

                    {/* Store Title, Avatar, and Rating */}
                    <div className="flex items-start gap-4 pt-1">
                      <div className="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-teal-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                        {store.logo_url ? (
                          <Image
                            src={store.logo_url}
                            alt={store.name}
                            width={64}
                            height={64}
                            unoptimized
                            className="w-full h-full object-cover rounded-2xl"
                          />
                        ) : (
                          <StoreIcon className="w-7 h-7 text-white" />
                        )}
                      </div>

                      <div className="space-y-1 flex-1">
                        <div className="flex flex-wrap items-center gap-2.5">
                          <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900 tracking-tight">
                            {store.name}
                          </h1>
                          {store.rating_average !== undefined && store.rating_average !== null && store.rating_average > 0 && (
                            <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-amber-50 rounded-full border border-amber-200">
                              <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                              <span className="font-heading font-bold text-xs text-navy-900 tabular-nums">
                                {store.rating_average.toFixed(1)}
                              </span>
                              <span className="text-[11px] text-slate-500 font-normal">
                                ({store.rating_count || 0} ulasan)
                              </span>
                            </div>
                          )}
                        </div>

                        <p className="text-xs sm:text-sm text-slate-600 leading-relaxed line-clamp-2">
                          {store.description || 'Layanan laundry profesional terpercaya dengan peralatan modern dan standar kebersihan terjamin.'}
                        </p>
                      </div>
                    </div>

                    {/* Store Address Banner */}
                    <div className="flex items-start gap-2.5 text-xs sm:text-sm text-slate-700 bg-sky-50/70 p-3.5 rounded-2xl border border-sky-100">
                      <MapPin className="w-4 h-4 text-teal-600 shrink-0 mt-0.5" />
                      <span className="leading-snug">{store.address_text}</span>
                    </div>
                  </div>

                  {/* Highlights 4-Stats Grid */}
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-4 mt-4 border-t border-slate-100">
                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                      <span className="text-[11px] text-slate-500 font-medium block">Rating Toko</span>
                      <span className="text-sm sm:text-base font-bold text-navy-900 font-heading flex items-center gap-1 mt-0.5">
                        <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                        <span>{store.rating_average ? store.rating_average.toFixed(1) : '5.0'}</span>
                        <span className="text-[11px] font-normal text-slate-400">({store.rating_count || 0})</span>
                      </span>
                    </div>

                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                      <span className="text-[11px] text-slate-500 font-medium block">Total Layanan</span>
                      <span className="text-sm sm:text-base font-bold text-navy-900 font-heading block mt-0.5">
                        {store.services_count || store.services?.length || 0} Layanan
                      </span>
                    </div>

                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                      <span className="text-[11px] text-slate-500 font-medium block">Tarif Mulai</span>
                      <span className="text-sm sm:text-base font-bold text-teal-600 font-heading tabular-nums block mt-0.5">
                        {store.starting_price ? formatRupiah(store.starting_price) : '-'}
                      </span>
                    </div>

                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-200/70">
                      <span className="text-[11px] text-slate-500 font-medium block">Jangkauan Antar</span>
                      <span className="text-sm sm:text-base font-bold text-navy-900 font-heading block mt-0.5">
                        Maks {store.service_radius_km} km
                      </span>
                    </div>
                  </div>
                </Card>

                {/* Right Column: Jam Operasional & Lokasi Toko Mini-Map (5 cols) */}
                <div className="lg:col-span-5 space-y-5">
                  {/* Jam Operasional Card */}
                  <Card variant="bento" className="p-5 sm:p-6 bg-white rounded-3xl border border-slate-200 shadow-sm">
                    <div className="flex items-center justify-between mb-3 text-navy-900">
                      <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4 text-teal-600" />
                        <h2 className="font-heading font-bold text-sm sm:text-base">Jam Operasional</h2>
                      </div>
                      <span className="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                        Buka
                      </span>
                    </div>

                    {store.opening_hours && typeof store.opening_hours === 'object' ? (
                      <div className="space-y-1 text-xs">
                        {Object.entries(store.opening_hours).map(([day, hours]) => (
                          <div key={day} className="flex items-center justify-between py-1 border-b border-slate-100 last:border-0">
                            <span className="capitalize font-medium text-slate-600">{day}</span>
                            <span className="font-bold text-navy-900 tabular-nums">{hours as string}</span>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="text-xs text-slate-500">Buka setiap hari pukul 08:00 - 20:00 WIB.</p>
                    )}
                  </Card>

                  {/* Lokasi Toko Mini Map Card */}
                  {store.latitude !== null && store.longitude !== null && (
                    <Card variant="flat" className="p-3 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                      <div className="flex items-center gap-2 mb-2 px-1 text-navy-900">
                        <MapPin className="w-4 h-4 text-teal-600" />
                        <h3 className="font-heading font-bold text-xs sm:text-sm">Lokasi & Titik Antar-Jemput</h3>
                      </div>
                      <div className="rounded-2xl overflow-hidden border border-slate-200/80">
                        <DynamicStoreMap
                          customerLocation={null}
                          stores={[store]}
                          selectedStoreId={store.id}
                          className="h-[200px] w-full"
                          showLegend={false}
                          autoOpenPopup={false}
                          isMiniMap={true}
                        />
                      </div>
                      <div className="mt-2.5 px-1 flex items-center justify-between text-[11px] text-slate-500">
                        <span>📍 Titik lokasi terverifikasi toko</span>
                        <span className="font-medium text-teal-700">Radius: {store.service_radius_km} km</span>
                      </div>
                    </Card>
                  )}
                </div>
              </div>

              {/* Store Services & Pricing Section */}
              <div className="space-y-5 pt-2">
                <div className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <div className="flex items-center gap-1.5 text-xs font-bold text-teal-600 uppercase tracking-wider mb-0.5">
                        <Sparkles className="w-3.5 h-3.5" />
                        <span>Pilihan Layanan & Tarif</span>
                      </div>
                      <h2 className="font-heading font-extrabold text-xl sm:text-2xl text-navy-900">
                        Layanan Laundry Tersedia
                      </h2>
                    </div>
                    <span className="text-xs font-semibold text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200 shadow-xs">
                      {filteredServices.length} Layanan Ditampilkan
                    </span>
                  </div>

                  {/* Category Filter Chips (Responsive Flex-Wrap, No Overflow) */}
                  {categoryTabs.length > 1 && (
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                      {categoryTabs.map((tab) => {
                        const isActive = selectedCategoryTab === tab.id;
                        return (
                          <button
                            key={tab.id}
                            type="button"
                            onClick={() => setSelectedCategoryTab(tab.id)}
                            className={cn(
                              'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all duration-200 min-h-[44px]',
                              isActive
                                ? 'bg-navy-900 text-white border-navy-900 shadow-sm'
                                : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:text-navy-900'
                            )}
                          >
                            <span>{tab.label}</span>
                            <span
                              className={cn(
                                'text-[10px] px-1.5 py-0.5 rounded-full font-bold',
                                isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'
                              )}
                            >
                              {tab.count}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>

                {/* Services Grid */}
                {filteredServices.length === 0 ? (
                  <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-slate-200">
                    <EmptyState
                      title="Belum Ada Layanan di Kategori Ini"
                      description="Toko ini belum menambahkan layanan aktif pada kategori yang dipilih."
                      actionText="Tampilkan Semua Layanan"
                      onAction={() => setSelectedCategoryTab('all')}
                    />
                  </Card>
                ) : (
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    {filteredServices.map((service: StoreServiceItem) => (
                      <Card
                        key={service.id}
                        variant="bento"
                        className="p-5 sm:p-6 flex flex-col justify-between bg-white rounded-2xl border border-slate-200 hover:border-teal-300 hover:shadow-md transition-all duration-300 h-full"
                      >
                        <div className="space-y-3">
                          {/* Service Category & Pricing Unit Badge */}
                          <div className="flex items-center justify-between gap-2">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-navy-900 border border-sky-100">
                              {service.category_name?.split('(')[0].trim() || 'Layanan'}
                            </span>
                            <span className="text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2 py-0.5 rounded-md border border-teal-200/70">
                              Per {service.pricing_unit}
                            </span>
                          </div>

                          {/* Service Title */}
                          <h3 className="font-heading font-bold text-base sm:text-lg text-navy-900 leading-snug">
                            {service.name}
                          </h3>

                          {/* Price Display Box */}
                          <div className="p-3.5 bg-sky-50/60 rounded-xl border border-sky-100/80 space-y-1">
                            <span className="text-xs text-slate-500 block">Tarif per {service.pricing_unit}:</span>
                            <div className="flex items-baseline gap-1">
                              <span className="font-heading font-extrabold text-xl sm:text-2xl text-teal-600 tabular-nums">
                                {formatRupiah(service.price_per_unit)}
                              </span>
                              <span className="text-xs text-slate-500 font-medium">/ {service.pricing_unit}</span>
                            </div>
                            {service.minimum_charge > 0 ? (
                              <p className="text-[11px] text-slate-600 pt-1 border-t border-sky-100 font-medium">
                                Minimum order: <b className="text-slate-800">{formatRupiah(service.minimum_charge)}</b>
                              </p>
                            ) : (
                              <p className="text-[11px] text-slate-400 pt-1 border-t border-sky-100/60">
                                Tanpa minimum order
                              </p>
                            )}
                          </div>

                          {/* Duration Info */}
                          <div className="flex items-center gap-1.5 text-xs text-slate-600">
                            <Timer className="w-3.5 h-3.5 text-teal-600 shrink-0" />
                            <span>Estimasi pengerjaan: <b>{service.estimated_duration_hours} Jam</b></span>
                          </div>
                        </div>

                        {/* Action CTA Button */}
                        <div className="pt-4 mt-4 border-t border-slate-100">
                          <Link
                            href={`/stores/${store.id}/order?service=${service.id}`}
                            className="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-xs font-bold text-white bg-navy-900 hover:bg-navy-800 active:scale-95 transition-all shadow-sm min-h-[44px]"
                          >
                            <ShoppingBag className="w-4 h-4 text-teal-400" />
                            <span>Pesan Layanan Ini</span>
                          </Link>
                        </div>
                      </Card>
                    ))}
                  </div>
                )}
              </div>

              {/* Customer Reviews & Ratings Section */}
              <div className="pt-4">
                <StoreReviewsSection storeId={store.id} />
              </div>
            </div>
          )}
        </div>
      </div>
    </PublicLayout>
  );
}

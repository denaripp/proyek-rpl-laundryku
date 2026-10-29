'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  Search,
  ArrowRight,
  Store as StoreIcon,
  ChevronDown,
  MapPin,
  Loader2,
  AlertCircle,
  Truck,
  Scale,
  Wallet,
  CalendarCheck,
  CheckCircle2,
  Mail,
} from 'lucide-react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { StoreCard } from '@/components/store/StoreCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { getStores, StoreItem } from '@/lib/store';
import { useAuth } from '@/lib/AuthContext';
import { getHomePathForRole } from '@/lib/auth';

const CS_EMAIL = process.env.NEXT_PUBLIC_CS_EMAIL || 'bantuan@laundryku.com';

const FAQ_SECTIONS = [
  {
    category: 'Pemesanan',
    items: [
      {
        question: 'Bagaimana cara memesan?',
        answer:
          'Pilih laundry terdekat, tentukan paket cuci yang diinginkan, lalu atur alamat dan jadwal jemput pakaian.',
      },
      {
        question: 'Bagaimana cara mendaftarkan laundry?',
        answer:
          'Klik "Gabung jadi mitra laundry", buat akun, lengkapi alamat usaha Anda, lalu tunggu persetujuan dari tim kami.',
      },
      {
        question: 'Bagaimana jika cucian rusak atau hilang?',
        answer:
          'Ajukan keluhan langsung lewat halaman rincian pesanan Anda. Pihak laundry akan memeriksa dan membantu menyelesaikannya.',
      },
    ],
  },
  {
    category: 'Pembayaran',
    items: [
      {
        question: 'Metode pembayaran apa saja yang tersedia?',
        answer:
          'Anda bisa bayar lewat QRIS, transfer bank, atau bayar tunai di tempat jika laundry menyediakan pilihan tersebut.',
      },
      {
        question: 'Berapa lama batas pembayaran online?',
        answer:
          'Waktu pembayaran adalah 1 jam sejak tagihan keluar. Jika terlewat, Anda cukup buat pembayaran baru di halaman pesanan.',
      },
    ],
  },
  {
    category: 'Biaya & Pengantaran',
    items: [
      {
        question: 'Apakah harga bisa berubah?',
        answer:
          'Perkiraan harga bisa berubah setelah cucian ditimbang laundry. Anda akan menerima rincian harga pasti untuk disetujui atau dibatalkan sebelum cucian diproses.',
      },
      {
        question: 'Apakah ada layanan antar jemput?',
        answer:
          'Ya, selama laundry yang dipilih menyediakan kurir. Jika tidak ada kurir, Anda bisa mengantar dan mengambil cucian sendiri.',
      },
      {
        question: 'Sampai jarak berapa toko melayani?',
        answer:
          'Setiap laundry menentukan jarak antar-jemputnya masing-masing, dengan jarak paling jauh hingga 10 km.',
      },
    ],
  },
];

export default function Home() {
  const router = useRouter();
  const { user, isAuthenticated, loading: authLoading } = useAuth();
  const [searchLocation, setSearchLocation] = useState('');
  const [isLocating, setIsLocating] = useState(false);
  const [featuredStores, setFeaturedStores] = useState<StoreItem[]>([]);
  const [storesLoading, setStoresLoading] = useState(true);
  const [storesError, setStoresError] = useState(false);
  const [userCoords, setUserCoords] = useState<{ lat: number; lng: number } | null>(null);

  // Anti-flash: If user is authenticated, redirect immediately to role home path
  useEffect(() => {
    if (!authLoading && isAuthenticated && user) {
      router.replace(getHomePathForRole(user));
    }
  }, [authLoading, isAuthenticated, user, router]);

  // Fetch real stores from API for landing showcase with 8-second timeout
  const loadStores = React.useCallback(async (coords?: { lat: number; lng: number } | null) => {
    setStoresLoading(true);
    setStoresError(false);

    const activeCoords = coords !== undefined ? coords : userCoords;

    const timeoutPromise = new Promise<never>((_, reject) =>
      setTimeout(() => reject(new Error('TIMEOUT_EXCEEDED')), 8000)
    );

    try {
      const fetchPromise = getStores({
        per_page: 6,
        lat: activeCoords?.lat,
        lng: activeCoords?.lng,
      });

      const res = await Promise.race([fetchPromise, timeoutPromise]);
      setFeaturedStores(res.stores ? res.stores.slice(0, 6) : []);
      setStoresError(false);
    } catch {
      setFeaturedStores([]);
      setStoresError(true);
    } finally {
      setStoresLoading(false);
    }
  }, [userCoords]);

  useEffect(() => {
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      if (params.get('preview') === 'skeleton') {
        setStoresLoading(true);
        setStoresError(false);
        return;
      }
      if (params.get('preview') === 'error') {
        setStoresLoading(false);
        setStoresError(true);
        setFeaturedStores([]);
        return;
      }
    }
    if (!isAuthenticated) {
      loadStores();
    }
  }, [isAuthenticated, loadStores]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchLocation.trim()) {
      router.push(`/stores?q=${encodeURIComponent(searchLocation.trim())}`);
    } else {
      router.push('/stores');
    }
  };

  const handleCurrentLocation = () => {
    if (!navigator.geolocation) {
      router.push('/stores');
      return;
    }

    setIsLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsLocating(false);
        const coords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        setUserCoords(coords);
        loadStores(coords);
      },
      () => {
        setIsLocating(false);
        router.push('/stores');
      },
      { timeout: 8000, enableHighAccuracy: false }
    );
  };

  // Prevent flash landing while checking auth or redirecting
  if (authLoading || (isAuthenticated && user)) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="flex flex-col items-center gap-2">
          <div className="w-5 h-5 border-2 border-navy-700 border-t-transparent rounded-full animate-spin" />
          <span className="text-xs text-slate-500 font-medium">Memuat halaman...</span>
        </div>
      </div>
    );
  }

  return (
    <PublicLayout>
      {/* 1. Hero Section */}
      <section className="relative bg-gradient-to-b from-navy-50/80 via-sky-50/40 to-white pt-14 pb-8 sm:pt-20 sm:pb-12 overflow-hidden border-b border-navy-100/40">
        {/* Soap Bubbles Decorative Elements (aria-hidden="true") */}
        <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
          {/* Bubble 1 (Top Left, always visible) */}
          <div className="absolute -top-3 -left-3 sm:top-6 sm:left-10 w-20 h-20 sm:w-28 sm:h-28 rounded-full border border-sky-300/40 bg-sky-200/20 backdrop-blur-[1px] shadow-inner animate-gentle-float">
            <div className="w-2.5 h-2.5 rounded-full bg-white/70 ml-3.5 mt-3.5" />
          </div>

          {/* Bubble 2 (Bottom Left, visible only on sm+) */}
          <div className="hidden sm:block absolute bottom-12 left-1/4 w-12 h-12 rounded-full border border-sky-300/30 bg-sky-100/20 animate-gentle-float-slow">
            <div className="w-1.5 h-1.5 rounded-full bg-white/60 ml-2 mt-2" />
          </div>

          {/* Bubble 3 (Top Right, always visible) */}
          <div className="absolute top-6 right-3 sm:right-16 w-16 h-16 sm:w-24 sm:h-24 rounded-full border border-sky-300/40 bg-sky-200/20 backdrop-blur-[1px] shadow-inner animate-gentle-float-slow">
            <div className="w-2 h-2 rounded-full bg-white/70 ml-3 mt-3" />
          </div>

          {/* Bubble 4 (Center Right, hidden on mobile) */}
          <div className="hidden sm:block absolute top-1/2 right-6 w-10 h-10 rounded-full border border-sky-300/30 bg-sky-100/25 animate-gentle-float">
            <div className="w-1.5 h-1.5 rounded-full bg-white/50 ml-1.5 mt-1.5" />
          </div>
        </div>

        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-center">
            {/* Left Column: Headline, Search, & Value Props */}
            <div className="lg:col-span-7 space-y-6 text-center lg:text-left">
              <div className="space-y-4">
                <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-navy-900 tracking-tight leading-[1.15]">
                  Laundry antar jemput praktis di sekitar Anda
                </h1>

                <p className="text-sm sm:text-base text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                  Pesan cuci kiloan, satuan, atau dry clean dari laundry terdekat. Kurir siap jemput cucian Anda, lalu antar kembali dalam keadaan bersih.
                </p>
              </div>

              {/* Primary Action: Location Search Card */}
              <div className="max-w-xl mx-auto lg:mx-0 space-y-2.5">
                <form onSubmit={handleSearchSubmit}>
                  <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-2 border border-navy-200 rounded-2xl shadow-sm focus-within:ring-2 focus-within:ring-navy-600 focus-within:border-transparent transition-all">
                    <div className="relative flex-1">
                      <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" strokeWidth={1.5} />
                      <input
                        type="text"
                        value={searchLocation}
                        onChange={(e) => setSearchLocation(e.target.value)}
                        placeholder="Tulis alamat atau area Anda..."
                        aria-label="Tulis alamat atau area Anda"
                        className="w-full pl-10 pr-3 py-2 text-sm bg-transparent border-0 focus:outline-hidden text-navy-900 placeholder-slate-400 font-medium"
                      />
                    </div>
                    <Button
                      type="submit"
                      size="md"
                      className="rounded-xl px-6 w-full sm:w-auto shrink-0"
                      rightIcon={<ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />}
                    >
                      <span>Cari laundry</span>
                    </Button>
                  </div>
                </form>

                <div className="flex items-center justify-between px-2 text-xs">
                  <button
                    type="button"
                    onClick={handleCurrentLocation}
                    disabled={isLocating}
                    className="inline-flex items-center gap-1.5 text-slate-600 hover:text-navy-700 font-medium transition-colors cursor-pointer disabled:opacity-60 py-1"
                  >
                    {isLocating ? (
                      <Loader2 className="w-3.5 h-3.5 animate-spin text-navy-700" />
                    ) : (
                      <MapPin className="w-3.5 h-3.5 text-navy-700" strokeWidth={1.5} />
                    )}
                    <span>Pakai lokasi saya</span>
                  </button>

                  <Link
                    href="/upgrade"
                    className="inline-flex items-center gap-1.5 text-navy-700 font-semibold hover:text-navy-800 hover:underline py-1 transition-colors"
                  >
                    <span>Gabung jadi mitra laundry</span>
                    <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
                  </Link>
                </div>
              </div>

              {/* Value Props Bar */}
              <div className="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-2.5 sm:gap-3 text-xs text-slate-700 font-medium">
                <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-navy-100 shadow-2xs">
                  <Truck className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={1.75} />
                  <span>Antar jemput</span>
                </div>
                <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-navy-100 shadow-2xs">
                  <Scale className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={1.75} />
                  <span>Harga sesuai timbangan</span>
                </div>
                <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-navy-100 shadow-2xs">
                  <Wallet className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={1.75} />
                  <span>Bayar QRIS atau tunai</span>
                </div>
              </div>
            </div>

            {/* Right Column: Floating Live Courier & Order Mockup Card */}
            <div className="lg:col-span-5 w-full max-w-md mx-auto lg:max-w-none pt-2 lg:pt-0">
              <div className="relative animate-gentle-float" aria-hidden="true">
                <div className="relative rounded-2xl border border-navy-200/90 bg-white p-5 sm:p-6 shadow-lg shadow-navy-900/5 space-y-4">
                  {/* Top Badge & Courier Info */}
                  <div className="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div className="flex items-center gap-2.5">
                      <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 border border-navy-100/60">
                        <Truck className="w-4 h-4" strokeWidth={1.75} />
                      </div>
                      <div>
                        <div className="text-xs font-bold text-navy-900 leading-tight">Penjemputan Cucian</div>
                        <div className="text-[11px] text-slate-500">Kurir Mitra LaundryKu</div>
                      </div>
                    </div>
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                      Menuju alamat
                    </span>
                  </div>

                  {/* Visual Route Indicator */}
                  <div className="space-y-2 pt-1">
                    <div className="flex items-center justify-between text-[11px] font-medium text-slate-600">
                      <span className="flex items-center gap-1 text-navy-900 font-semibold">
                        <MapPin className="w-3.5 h-3.5 text-navy-700" />
                        Lokasi Anda
                      </span>
                      <span className="flex items-center gap-1 text-slate-700">
                        <StoreIcon className="w-3.5 h-3.5 text-slate-500" />
                        Laundry Terdekat
                      </span>
                    </div>

                    {/* Progress Track */}
                    <div className="relative h-2 w-full bg-navy-100 rounded-full overflow-hidden">
                      <div className="absolute left-0 top-0 bottom-0 w-2/3 bg-navy-700 rounded-full" />
                    </div>
                  </div>

                  {/* Sub-Card Note: Ditimbang di laundry */}
                  <div className="p-3 rounded-xl bg-navy-50/70 border border-navy-100 flex items-center gap-2.5">
                    <div className="w-7 h-7 rounded-lg bg-white border border-navy-200 text-navy-700 flex items-center justify-center shrink-0 shadow-2xs">
                      <Scale className="w-3.5 h-3.5" strokeWidth={1.75} />
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="text-xs font-bold text-navy-900">Ditimbang di laundry</div>
                      <div className="text-[11px] text-slate-600 truncate">Rincian harga pasti sebelum diproses</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Wave SVG Divider at bottom of Hero */}
        <div className="w-full overflow-hidden leading-none text-white -mb-px pt-8 relative z-10" aria-hidden="true">
          <svg viewBox="0 0 1200 120" preserveAspectRatio="none" className="relative block w-full h-7 sm:h-11 fill-current">
            <path d="M0,0 C150,75 350,5 500,45 C650,85 900,15 1200,35 L1200,120 L0,120 Z"></path>
          </svg>
        </div>
      </section>

      {/* 2. Cara Kerja (4 Langkah dengan Garis Rute Kurir) */}
      <section className="py-16 sm:py-20 bg-white border-b border-slate-200">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="max-w-xl mb-12">
            <h2 className="text-xl sm:text-2xl md:text-3xl font-extrabold text-navy-900 tracking-tight">
              Cara mudah pesan laundry
            </h2>
            <p className="text-xs sm:text-sm text-slate-600 mt-1.5">
              Empat langkah praktis dari cucian kotor dijemput sampai diantar bersih.
            </p>
          </div>

          {/* Stepper with Connected Courier Route Line */}
          <div className="relative">
            {/* Desktop Horizontal Connecting Line */}
            <div
              className="hidden lg:block absolute top-[44px] left-[10%] right-[10%] border-t-2 border-dashed border-navy-200 z-0"
              aria-hidden="true"
            />

            {/* Mobile Vertical Connecting Line (runs behind the icons) */}
            <div
              className="sm:hidden absolute top-8 bottom-8 left-[39px] border-l-2 border-dashed border-navy-200 z-0"
              aria-hidden="true"
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
              {/* Step 1 */}
              <div className="flex sm:flex-col items-start gap-4 sm:gap-3 p-5 sm:p-6 rounded-2xl bg-white border border-slate-200 hover:border-navy-300 transition-all shadow-2xs hover:shadow-xs">
                <div className="w-12 h-12 rounded-2xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 border border-navy-100 shadow-2xs relative">
                  <MapPin className="w-5 h-5" strokeWidth={1.75} />
                  <span className="sm:hidden absolute -top-1.5 -right-1.5 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-full bg-navy-700 text-white shadow-2xs">
                    01
                  </span>
                </div>
                <div className="space-y-1.5 flex-1 min-w-0">
                  <div className="hidden sm:flex items-center justify-between pb-1">
                    <span className="tabular-nums font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-navy-100 text-navy-800">
                      01
                    </span>
                  </div>
                  <h3 className="font-bold text-sm sm:text-base text-navy-900 leading-snug">Pilih laundry terdekat</h3>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Cari dan pilih tempat laundry yang paling dekat dengan lokasi Anda.
                  </p>
                </div>
              </div>

              {/* Step 2 */}
              <div className="flex sm:flex-col items-start gap-4 sm:gap-3 p-5 sm:p-6 rounded-2xl bg-white border border-slate-200 hover:border-navy-300 transition-all shadow-2xs hover:shadow-xs">
                <div className="w-12 h-12 rounded-2xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 border border-navy-100 shadow-2xs relative">
                  <CalendarCheck className="w-5 h-5" strokeWidth={1.75} />
                  <span className="sm:hidden absolute -top-1.5 -right-1.5 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-full bg-navy-700 text-white shadow-2xs">
                    02
                  </span>
                </div>
                <div className="space-y-1.5 flex-1 min-w-0">
                  <div className="hidden sm:flex items-center justify-between pb-1">
                    <span className="tabular-nums font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-navy-100 text-navy-800">
                      02
                    </span>
                  </div>
                  <h3 className="font-bold text-sm sm:text-base text-navy-900 leading-snug">Tentukan layanan & jadwal</h3>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Pilih jenis cuci, perkiraan berat pakaian, dan tentukan waktu penjemputan.
                  </p>
                </div>
              </div>

              {/* Step 3 */}
              <div className="flex sm:flex-col items-start gap-4 sm:gap-3 p-5 sm:p-6 rounded-2xl bg-white border border-slate-200 hover:border-navy-300 transition-all shadow-2xs hover:shadow-xs">
                <div className="w-12 h-12 rounded-2xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 border border-navy-100 shadow-2xs relative">
                  <Scale className="w-5 h-5" strokeWidth={1.75} />
                  <span className="sm:hidden absolute -top-1.5 -right-1.5 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-full bg-navy-700 text-white shadow-2xs">
                    03
                  </span>
                </div>
                <div className="space-y-1.5 flex-1 min-w-0">
                  <div className="hidden sm:flex items-center justify-between pb-1">
                    <span className="tabular-nums font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-navy-100 text-navy-800">
                      03
                    </span>
                  </div>
                  <h3 className="font-bold text-sm sm:text-base text-navy-900 leading-snug">Kurir jemput & timbang</h3>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Kurir mengambil cucian Anda, lalu laundry menimbang ulang agar harga pasti.
                  </p>
                </div>
              </div>

              {/* Step 4 */}
              <div className="flex sm:flex-col items-start gap-4 sm:gap-3 p-5 sm:p-6 rounded-2xl bg-white border border-slate-200 hover:border-navy-300 transition-all shadow-2xs hover:shadow-xs">
                <div className="w-12 h-12 rounded-2xl bg-navy-50 text-navy-700 flex items-center justify-center shrink-0 border border-navy-100 shadow-2xs relative">
                  <CheckCircle2 className="w-5 h-5" strokeWidth={1.75} />
                  <span className="sm:hidden absolute -top-1.5 -right-1.5 text-[10px] font-bold font-mono px-1.5 py-0.5 rounded-full bg-navy-700 text-white shadow-2xs">
                    04
                  </span>
                </div>
                <div className="space-y-1.5 flex-1 min-w-0">
                  <div className="hidden sm:flex items-center justify-between pb-1">
                    <span className="tabular-nums font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-navy-100 text-navy-800">
                      04
                    </span>
                  </div>
                  <h3 className="font-bold text-sm sm:text-base text-navy-900 leading-snug">Bayar & terima cucian bersih</h3>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Setujui total biaya, bayar online (QRIS/transfer) atau tunai, lalu cucian diantar pulang.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 3. Toko Terdekat / Toko Aktif */}
      <section id="toko-terdekat" className="py-16 sm:py-20 bg-navy-50/40 border-b border-navy-100/60">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
            <div>
              <div className="flex items-center gap-2.5">
                <h2 className="text-xl sm:text-2xl md:text-3xl font-extrabold text-navy-900 tracking-tight">
                  Laundry di sekitar Anda
                </h2>
                {userCoords && (
                  <span className="inline-flex items-center text-xs font-semibold text-navy-800 bg-navy-100 border border-navy-200 px-2.5 py-0.5 rounded-full">
                    Dekat lokasi Anda
                  </span>
                )}
              </div>
              <p className="text-xs sm:text-sm text-slate-600 mt-1">
                Pilih laundry untuk melihat daftar harga dan paket layanannya.
              </p>
            </div>

            <Link
              href="/stores"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-navy-700 hover:text-navy-800 hover:underline shrink-0"
            >
              <span>Lihat semua laundry</span>
              <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
            </Link>
          </div>

          {storesLoading ? (
            <div className="flex overflow-x-auto snap-x snap-mandatory sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-4 pb-2 sm:pb-0 -mx-4 px-4 sm:mx-0 sm:px-0">
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="snap-start w-[280px] sm:w-auto shrink-0 sm:shrink bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between h-44 animate-pulse shadow-2xs"
                >
                  <div className="space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="w-10 h-10 rounded-xl bg-slate-200 shrink-0" />
                      <div className="space-y-1.5 flex-1 min-w-0">
                        <div className="h-4 bg-slate-200 rounded w-3/4" />
                        <div className="h-3 bg-slate-100 rounded w-1/2" />
                      </div>
                    </div>
                    <div className="flex gap-1.5 pt-1">
                      <div className="h-5 w-16 bg-slate-100 rounded border border-slate-200" />
                      <div className="h-5 w-20 bg-slate-100 rounded border border-slate-200" />
                    </div>
                  </div>
                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div className="h-4 bg-slate-200 rounded w-24" />
                    <div className="h-4 bg-slate-100 rounded w-16" />
                  </div>
                </div>
              ))}
            </div>
          ) : storesError ? (
            <Card variant="flat" className="p-8 text-center max-w-md mx-auto space-y-3 bg-white border-slate-200 rounded-2xl shadow-xs">
              <AlertCircle className="w-8 h-8 text-rose-500 mx-auto" strokeWidth={1.5} />
              <p className="text-xs sm:text-sm text-slate-800 font-medium">
                Daftar laundry belum berhasil dimuat.
              </p>
              <div>
                <Button
                  type="button"
                  size="sm"
                  onClick={() => loadStores()}
                  className="rounded-xl px-5"
                >
                  Muat ulang
                </Button>
              </div>
            </Card>
          ) : featuredStores.length === 0 ? (
            <Card variant="flat" className="p-8 text-center max-w-lg mx-auto bg-white border-slate-200 rounded-2xl shadow-xs space-y-2">
              <StoreIcon className="w-8 h-8 text-slate-400 mx-auto mb-1" strokeWidth={1.5} />
              <p className="text-xs sm:text-sm text-slate-700 font-medium">
                Belum ada laundry yang tersedia di area ini.
              </p>
              <div className="mt-4 flex items-center justify-center gap-4 text-xs">
                <Link href="/stores" className="text-navy-700 font-semibold hover:underline">
                  Cari di area lain
                </Link>
                <span className="text-slate-300">•</span>
                <Link href="/upgrade" className="text-navy-700 font-semibold hover:underline">
                  Gabung jadi mitra laundry
                </Link>
              </div>
            </Card>
          ) : (
            <div className="flex overflow-x-auto snap-x snap-mandatory sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-4 pb-2 sm:pb-0 -mx-4 px-4 sm:mx-0 sm:px-0">
              {featuredStores.map((store) => (
                <div key={store.id} className="snap-start w-[280px] sm:w-auto shrink-0 sm:shrink">
                  <StoreCard store={store} variant="compact" />
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* 4. FAQ (Accordion Native <details>/<summary>) */}
      <section id="faq" className="py-16 sm:py-24 bg-white">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">
            {/* Left Column: Heading & CS Contact Card */}
            <div className="lg:col-span-4 lg:sticky lg:top-24 space-y-4">
              <div className="space-y-2">
                <h2 className="text-xl sm:text-2xl md:text-3xl font-extrabold text-navy-900 tracking-tight">
                  Pertanyaan yang sering ditanyakan
                </h2>
                <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                  Jawaban singkat seputar cara pesan, bayar, dan layanan di LaundryKu.
                </p>
              </div>

              <div className="p-4 sm:p-5 rounded-2xl bg-navy-50 border border-navy-200/80 space-y-2.5 text-xs">
                <div className="flex items-center gap-2 text-navy-900 font-bold text-sm">
                  <Mail className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                  <span>Masih butuh bantuan?</span>
                </div>
                <p className="text-slate-600 leading-relaxed">
                  Tim customer care kami siap membantu kendala pesanan atau pendaftaran Anda.
                </p>
                <div>
                  <a
                    href={`mailto:${CS_EMAIL}`}
                    className="inline-flex items-center gap-1 font-semibold text-navy-700 hover:text-navy-800 underline underline-offset-2 break-all"
                  >
                    {CS_EMAIL}
                  </a>
                </div>
              </div>
            </div>

            {/* Right Column: Categorized Accordion */}
            <div className="lg:col-span-8 space-y-8">
              {FAQ_SECTIONS.map((section) => (
                <div key={section.category} className="space-y-3.5">
                  <div>
                    <span className="text-[11px] uppercase tracking-wider font-bold text-navy-800 bg-navy-100/70 border border-navy-200/60 px-2.5 py-1 rounded-md inline-block">
                      {section.category}
                    </span>
                  </div>

                  <div className="space-y-2.5">
                    {section.items.map((item, idx) => (
                      <details
                        key={idx}
                        className="group border border-slate-200 rounded-2xl bg-white open:border-navy-300 open:bg-navy-50/20 hover:border-navy-200 transition-all shadow-2xs"
                      >
                        <summary className="flex items-center justify-between p-4 cursor-pointer select-none font-semibold text-xs sm:text-sm text-navy-900 focus:outline-hidden focus:ring-2 focus:ring-navy-600 rounded-2xl">
                          <span className="pr-3 leading-snug">{item.question}</span>
                          <ChevronDown
                            className="w-4 h-4 text-slate-400 group-open:rotate-180 group-open:text-navy-700 transition-transform duration-200 shrink-0"
                            strokeWidth={1.5}
                          />
                        </summary>
                        <div className="px-4 pb-4 pt-1 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100">
                          {item.answer}
                        </div>
                      </details>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}

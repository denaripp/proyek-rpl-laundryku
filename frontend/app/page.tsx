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
} from 'lucide-react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { StoreCard } from '@/components/store/StoreCard';
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
          'Pilih toko di sekitar lokasi Anda, tentukan jenis layanan laundry yang dibutuhkan, lalu masukkan alamat dan jadwal penjemputan cucian.',
      },
      {
        question: 'Bagaimana cara mendaftarkan laundry?',
        answer:
          'Klik tautan "Daftarkan laundry", buat akun baru, isi data dan alamat outlet Anda, lalu tunggu proses verifikasi persetujuan admin.',
      },
      {
        question: 'Bagaimana jika cucian rusak atau hilang?',
        answer:
          'Ajukan komplain langsung dari halaman rincian pesanan terkait. Penanganan kendala dilakukan oleh pihak toko dan seluruh catatan tersimpan di sistem.',
      },
    ],
  },
  {
    category: 'Pembayaran',
    items: [
      {
        question: 'Metode pembayaran apa saja yang tersedia?',
        answer:
          'Pembayaran online melalui Xendit, termasuk QRIS dan transfer bank, serta bayar di tempat (COD) jika toko mengaktifkannya.',
      },
      {
        question: 'Berapa lama batas pembayaran online?',
        answer:
          'Batas waktu pembayaran adalah 1 jam sejak tagihan dibuat. Jika lewat, status pembayaran kedaluwarsa dan Anda bisa membuat pembayaran baru dari rincian pesanan.',
      },
    ],
  },
  {
    category: 'Pengiriman & Harga',
    items: [
      {
        question: 'Apakah harga bisa berubah?',
        answer:
          'Harga estimasi bisa berubah setelah cucian ditimbang di outlet. Anda akan menerima notifikasi rincian harga final dan dapat menyetujui atau membatalkan sebelum cucian diproses.',
      },
      {
        question: 'Apakah ada layanan antar jemput?',
        answer:
          'Ya, jika outlet yang dipilih menyediakan layanan antar-jemput. Jika toko tidak menyediakan kurir, Anda dapat mengantar dan mengambil cucian sendiri langsung ke outlet.',
      },
      {
        question: 'Sampai jarak berapa toko melayani?',
        answer:
          'Setiap toko menentukan radius layanannya masing-masing pada sistem, dengan jangkauan maksimal hingga 10 km dari lokasi toko.',
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
          <div className="w-5 h-5 border-2 border-slate-700 border-t-transparent rounded-full animate-spin" />
          <span className="text-xs text-slate-500 font-medium">Memuat halaman...</span>
        </div>
      </div>
    );
  }

  return (
    <PublicLayout>
      {/* 1. Hero Section */}
      <section className="bg-slate-50 border-b border-slate-200 py-16 sm:py-24">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-5">
          <h1 className="text-[30px] sm:text-4xl md:text-[44px] font-semibold text-slate-900 tracking-tight leading-[1.18] max-w-3xl mx-auto">
            Layanan laundry antar-jemput terpercaya di sekitar Anda
          </h1>

          <p className="text-xs sm:text-sm md:text-base text-slate-600 max-w-xl mx-auto leading-relaxed">
            Pesan jasa laundry kiloan, satuan, dan dry clean dari outlet terverifikasi. Kurir menjemput cucian dan mengantar kembali dalam kondisi bersih.
          </p>

          {/* Primary Action: Location Search Bar */}
          <div className="max-w-lg mx-auto pt-3 space-y-2">
            <form onSubmit={handleSearchSubmit}>
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 bg-white p-1.5 border border-slate-300 rounded-lg shadow-xs">
                <div className="relative flex-1">
                  <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3" strokeWidth={1.5} />
                  <input
                    type="text"
                    value={searchLocation}
                    onChange={(e) => setSearchLocation(e.target.value)}
                    placeholder="Masukkan alamat atau area..."
                    aria-label="Masukkan alamat atau area"
                    className="w-full pl-9 pr-3 py-2 text-xs sm:text-sm bg-transparent border-0 focus:outline-hidden text-slate-900 placeholder-slate-400"
                  />
                </div>
                <button
                  type="submit"
                  className="px-5 py-2.5 bg-[#1A3A5C] hover:bg-[#132d47] text-white text-xs sm:text-sm font-medium rounded-md transition-colors cursor-pointer flex items-center justify-center gap-1.5 shrink-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#1A3A5C] focus-visible:ring-offset-2"
                >
                  <span>Cari toko</span>
                  <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
                </button>
              </div>
            </form>

            <div className="flex items-center justify-between px-1 text-xs">
              <button
                type="button"
                onClick={handleCurrentLocation}
                disabled={isLocating}
                className="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 transition-colors cursor-pointer disabled:opacity-60"
              >
                {isLocating ? (
                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                ) : (
                  <MapPin className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.5} />
                )}
                <span>Gunakan lokasi saat ini</span>
              </button>

              <Link
                href="/upgrade"
                className="inline-flex items-center gap-1 text-[#1A3A5C] font-semibold hover:underline"
              >
                <span>Daftarkan laundry Anda</span>
                <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* 2. Cara Kerja (4 Langkah Bernomor) */}
      <section className="py-14 sm:py-20 bg-white border-b border-slate-200">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="max-w-xl mb-10">
            <h2 className="text-lg sm:text-2xl font-semibold text-slate-900 tracking-tight">
              Cara kerja
            </h2>
            <p className="text-xs sm:text-sm text-slate-600 mt-1">
              Alur pemesanan dari penjemputan hingga cucian kembali bersih.
            </p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 space-y-2">
              <span className="tabular-nums font-mono text-xs sm:text-sm font-semibold text-slate-400">
                01
              </span>
              <h3 className="font-semibold text-xs sm:text-sm text-slate-900">Cari toko</h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                Cari dan pilih outlet laundry terdekat yang melayani area dan jenis pakaian Anda.
              </p>
            </div>

            <div className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 space-y-2">
              <span className="tabular-nums font-mono text-xs sm:text-sm font-semibold text-slate-400">
                02
              </span>
              <h3 className="font-semibold text-xs sm:text-sm text-slate-900">Pesan dan pilih layanan</h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                Tentukan jenis layanan, perkiraan berat atau jumlah cucian, serta jadwal penjemputan.
              </p>
            </div>

            <div className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 space-y-2">
              <span className="tabular-nums font-mono text-xs sm:text-sm font-semibold text-slate-400">
                03
              </span>
              <h3 className="font-semibold text-xs sm:text-sm text-slate-900">Kurir jemput dan toko menimbang</h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                Kurir mengambil cucian dan toko melakukan penimbangan nyata untuk rincian harga final.
              </p>
            </div>

            <div className="p-4 border border-slate-200 rounded-lg bg-slate-50/50 space-y-2">
              <span className="tabular-nums font-mono text-xs sm:text-sm font-semibold text-slate-400">
                04
              </span>
              <h3 className="font-semibold text-xs sm:text-sm text-slate-900">Bayar lalu cucian diantar</h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                Setujui harga, lakukan pembayaran online atau COD, lalu pakaian bersih diantar kembali.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* 3. Toko Terdekat / Toko Aktif */}
      <section id="toko-terdekat" className="py-14 sm:py-20 bg-slate-50 border-b border-slate-200">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-lg sm:text-2xl font-semibold text-slate-900 tracking-tight">
                  Toko terdekat
                </h2>
                {userCoords && (
                  <span className="inline-flex items-center text-[11px] font-medium text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                    Dari lokasi Anda
                  </span>
                )}
              </div>
              <p className="text-xs sm:text-sm text-slate-600 mt-1">
                Pilih toko untuk melihat layanan dan harga
              </p>
            </div>

            <Link
              href="/stores"
              className="inline-flex items-center gap-1 text-xs sm:text-sm font-medium text-[#1A3A5C] hover:underline shrink-0"
            >
              <span>Lihat semua toko</span>
              <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
            </Link>
          </div>

          {storesLoading ? (
            <div className="flex overflow-x-auto snap-x snap-mandatory sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-4 pb-2 sm:pb-0 -mx-4 px-4 sm:mx-0 sm:px-0">
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="snap-start w-[280px] sm:w-auto shrink-0 sm:shrink bg-white border border-slate-200 rounded-[8px] p-4 flex flex-col justify-between h-44 animate-pulse"
                >
                  <div className="space-y-3">
                    <div className="flex items-start gap-3">
                      <div className="w-10 h-10 rounded-md bg-slate-200 shrink-0" />
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
            <div className="bg-white border border-slate-200 rounded-[8px] p-8 text-center max-w-md mx-auto">
              <AlertCircle className="w-8 h-8 text-rose-500 mx-auto mb-2" strokeWidth={1.5} />
              <p className="text-xs sm:text-sm text-slate-800 font-medium mb-3">Toko belum bisa dimuat.</p>
              <button
                type="button"
                onClick={() => loadStores()}
                className="px-4 py-2 bg-[#1A3A5C] hover:bg-[#132d47] text-white text-xs font-medium rounded-md transition-colors cursor-pointer focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#1A3A5C]"
              >
                Coba lagi
              </button>
            </div>
          ) : featuredStores.length === 0 ? (
            <div className="bg-white border border-slate-200 rounded-[8px] p-8 text-center max-w-lg mx-auto">
              <StoreIcon className="w-8 h-8 text-slate-400 mx-auto mb-2.5" strokeWidth={1.5} />
              <p className="text-xs sm:text-sm text-slate-700 font-medium">Belum ada toko yang aktif di area ini.</p>
              <div className="mt-4 flex items-center justify-center gap-4 text-xs">
                <Link href="/stores" className="text-[#1A3A5C] font-semibold hover:underline">
                  Cari di semua area
                </Link>
                <span className="text-slate-300">•</span>
                <Link href="/upgrade" className="text-[#1A3A5C] font-semibold hover:underline">
                  Daftarkan laundry Anda
                </Link>
              </div>
            </div>
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
            {/* Left Column: Heading & CS Contact */}
            <div className="lg:col-span-4 lg:sticky lg:top-24 space-y-3">
              <h2 className="text-lg sm:text-2xl font-semibold text-slate-900 tracking-tight">
                Pertanyaan umum
              </h2>
              <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                Jawaban ringkas seputar pemesanan, pembayaran, dan operasional layanan LaundryKu.
              </p>
              <div className="pt-2 text-xs sm:text-sm text-slate-600">
                Tidak menemukan jawaban? Hubungi kami di{' '}
                <a
                  href={`mailto:${CS_EMAIL}`}
                  className="text-[#1A3A5C] font-semibold underline underline-offset-2 hover:text-[#132d47]"
                >
                  {CS_EMAIL}
                </a>
              </div>
            </div>

            {/* Right Column: Categorized Accordion */}
            <div className="lg:col-span-8 space-y-8">
              {FAQ_SECTIONS.map((section) => (
                <div key={section.category} className="space-y-3">
                  <div className="text-[11px] uppercase tracking-wider font-semibold text-slate-500">
                    {section.category}
                  </div>

                  <div className="space-y-2.5">
                    {section.items.map((item, idx) => (
                      <details
                        key={idx}
                        className="group border border-slate-200 rounded-lg bg-slate-50/40 open:bg-white transition-colors"
                      >
                        <summary className="flex items-center justify-between p-3.5 sm:p-4 cursor-pointer select-none font-medium text-xs sm:text-sm text-slate-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#1A3A5C] rounded-lg">
                          <span className="pr-3">{item.question}</span>
                          <ChevronDown
                            className="w-4 h-4 text-slate-400 group-open:rotate-180 transition-transform duration-200 shrink-0"
                            strokeWidth={1.5}
                          />
                        </summary>
                        <div className="px-3.5 sm:px-4 pb-3.5 sm:pb-4 pt-1 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100">
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

'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Badge,
  Rating,
  Button,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import { getStoreAdminRatings, RatingItem } from '@/lib/rating';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, AuthUser, logoutAndRedirectHome } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  Star,
  MessageSquare,
  Filter,
  ShoppingBag,
  Award,
} from 'lucide-react';

export default function StoreAdminRatingsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [ratings, setRatings] = useState<RatingItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [scoreFilter, setScoreFilter] = useState<number | undefined>(undefined);
  const [averageScore, setAverageScore] = useState<number>(0);
  const [totalRatings, setTotalRatings] = useState<number>(0);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);

  useEffect(() => {
    setCurrentUser(getStoredUser());
  }, []);

  const fetchRatings = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getStoreAdminRatings({
        score: scoreFilter,
        page: currentPage,
      });
      setRatings(res.data);
      if (res.meta?.summary) {
        setAverageScore(res.meta.summary.average_score || 0);
        setTotalRatings(res.meta.summary.total_ratings || 0);
      }
      setTotalPages(res.meta?.last_page || 1);
    } catch (err: unknown) {
      setError(formatFriendlyErrorMessage(err, 'Gagal memuat daftar ulasan.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRatings();
  }, [scoreFilter, currentPage]);

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
      onLogout={() => logoutAndRedirectHome('mitra')}
    >
      <div className="space-y-6">
        {/* Header */}
        <div className="pb-1">
          <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
            Rating & ulasan toko
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl">
            Pantau testimoni dan skor kepuasan dari pelanggan setia Anda untuk menjaga mutu layanan cuci dan setrika.
          </p>
        </div>

        {/* Overview Score Bento Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col items-center justify-center text-center">
            <span className="text-xs font-semibold text-slate-500 mb-1">Rata-rata rating</span>
            <span className="font-mono font-bold text-4xl text-navy-950 tabular-nums mb-2">
              {averageScore > 0 ? averageScore.toFixed(1) : '0.0'}
            </span>
            <Rating value={averageScore} max={5} size="lg" showText={false} />
          </div>

          <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col items-center justify-center text-center">
            <span className="text-xs font-semibold text-slate-500 mb-1">Total ulasan masuk</span>
            <span className="font-mono font-bold text-4xl text-navy-950 tabular-nums mb-2">
              {totalRatings}
            </span>
            <span className="text-xs text-slate-400">Dari pesanan yang telah selesai</span>
          </div>

          <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col justify-center">
            <div className="flex items-center gap-2 mb-1.5 text-navy-950">
              <Award className="w-4 h-4 text-navy-800" strokeWidth={1.75} />
              <h3 className="font-bold text-xs sm:text-sm">Tips mutu layanan</h3>
            </div>
            <p className="text-xs text-slate-600 leading-relaxed">
              Jaga ketepatan waktu pengantaran kurir dan pastikan pakaian selalu rapi, bersih, dan harum untuk menjaga rating tetap tinggi.
            </p>
          </div>
        </div>

        {/* Filter Pills */}
        <div className="p-4 sm:p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-slate-500 flex items-center gap-1 mr-1">
              <Filter className="w-3.5 h-3.5" strokeWidth={1.75} />
              <span>Filter bintang:</span>
            </span>

            <button
              type="button"
              onClick={() => {
                setScoreFilter(undefined);
                setCurrentPage(1);
              }}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all min-h-[34px] cursor-pointer',
                scoreFilter === undefined
                  ? 'bg-navy-900 text-white border-navy-900 shadow-2xs'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
              )}
            >
              Semua
            </button>

            {[5, 4, 3, 2, 1].map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => {
                  setScoreFilter(s);
                  setCurrentPage(1);
                }}
                className={cn(
                  'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1 min-h-[34px] cursor-pointer',
                  scoreFilter === s
                    ? 'bg-navy-900 text-white border-navy-900 shadow-2xs'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                )}
              >
                <span>{s}</span>
                <Star className="w-3 h-3 text-amber-400 fill-amber-400" />
              </button>
            ))}
          </div>
        </div>

        {/* Loading State */}
        {loading && (
          <div className="space-y-3.5">
            {[1, 2, 3].map((i) => (
              <div key={i} className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs animate-pulse space-y-3">
                <div className="flex justify-between items-center">
                  <div className="h-4 bg-slate-200 rounded w-1/4"></div>
                  <div className="h-4 bg-slate-100 rounded w-20"></div>
                </div>
                <div className="h-12 bg-slate-100 rounded-xl"></div>
              </div>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <div className="p-8 bg-white rounded-2xl border border-rose-200/80 shadow-2xs">
            <ErrorState
              title="Gagal memuat ulasan"
              message={error}
              onRetry={fetchRatings}
            />
          </div>
        )}

        {/* Empty State */}
        {!loading && !error && ratings.length === 0 && (
          <div className="p-10 bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
            <EmptyState
              title="Belum ada ulasan"
              description={
                scoreFilter !== undefined
                  ? 'Tidak ada ulasan dengan skor bintang yang dipilih.'
                  : 'Toko Anda belum memiliki ulasan dari pelanggan. Ulasan akan muncul setelah pelanggan menyelesaikan pesanan.'
              }
            />
          </div>
        )}

        {/* Ratings List */}
        {!loading && !error && ratings.length > 0 && (
          <div className="space-y-3.5">
            {ratings.map((item) => (
              <div
                key={item.id}
                className="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3 hover:border-slate-300 transition-colors"
              >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                  <div className="flex items-center gap-2.5">
                    <Rating value={item.score} max={5} size="sm" showText={false} />
                    <span className="font-mono font-bold text-xs text-navy-950">
                      {item.score}.0
                    </span>
                    <span className="text-slate-300 text-xs">•</span>
                    <span className="font-semibold text-xs text-slate-800">
                      {item.customer_name || 'Pelanggan'}
                    </span>
                  </div>

                  <span className="text-[11px] text-slate-400 font-mono">
                    {formatDate(item.created_at)}
                  </span>
                </div>

                {item.review ? (
                  <p className="text-slate-700 text-xs leading-relaxed">
                    &ldquo;{item.review}&rdquo;
                  </p>
                ) : (
                  <p className="text-slate-400 text-xs italic">
                    Pelanggan tidak meninggalkan catatan ulasan teks.
                  </p>
                )}

                {item.order_number && (
                  <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                    <span className="text-[11px] text-slate-500 flex items-center gap-1.5 font-mono">
                      <ShoppingBag className="w-3.5 h-3.5 text-slate-400" strokeWidth={1.75} />
                      <span>Pesanan #{item.order_number}</span>
                    </span>
                  </div>
                )}
              </div>
            ))}

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 pt-4">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                  className="rounded-xl text-xs min-h-[38px] font-semibold"
                >
                  Sebelumnya
                </Button>
                <span className="text-xs text-slate-500 font-semibold px-3 font-mono">
                  Halaman {currentPage} dari {totalPages}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                  className="rounded-xl text-xs min-h-[38px] font-semibold"
                >
                  Selanjutnya
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}

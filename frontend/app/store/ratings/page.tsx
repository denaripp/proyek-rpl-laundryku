'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
  Badge,
  Rating,
  Button,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import { getStoreAdminRatings, RatingItem } from '@/lib/rating';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { getStoredUser, logout, AuthUser } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  Star,
  MessageSquare,
  Filter,
  ShoppingBag,
  ExternalLink,
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
      onLogout={logout}
    >
      <div className="space-y-6">
        {/* Banner */}
        <Card variant="bento" className="p-6 sm:p-8">
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-2 mb-2">
                <Badge variant="teal" className="text-xs px-3 py-1 font-semibold rounded-full flex items-center gap-1">
                  <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                  <span>Reputasi & Kepuasan Pelanggan</span>
                </Badge>
              </div>
              <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900 tracking-tight">
                Rating & Ulasan Toko
              </h1>
              <p className="text-xs sm:text-sm text-slate-500 mt-1 max-w-2xl">
                Pantau testimoni dan skor kepuasan dari pelanggan setia Anda untuk menjaga mutu layanan cuci dan setrika.
              </p>
            </div>
          </div>
        </Card>

        {/* Overview Score Card */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card variant="bento" className="p-6 flex flex-col items-center justify-center text-center">
            <span className="text-xs font-semibold text-slate-500 mb-1">Rata-rata Rating</span>
            <span className="font-heading font-extrabold text-4xl sm:text-5xl text-navy-900 tabular-nums mb-2">
              {averageScore > 0 ? averageScore.toFixed(1) : '0.0'}
            </span>
            <Rating value={averageScore} max={5} size="lg" showText={false} />
          </Card>

          <Card variant="bento" className="p-6 flex flex-col items-center justify-center text-center">
            <span className="text-xs font-semibold text-slate-500 mb-1">Total Ulasan Masuk</span>
            <span className="font-heading font-extrabold text-4xl sm:text-5xl text-teal-600 tabular-nums mb-2">
              {totalRatings}
            </span>
            <span className="text-xs text-slate-400">Dari pesanan yang telah selesai</span>
          </Card>

          <Card variant="bento" className="p-6 flex flex-col justify-center">
            <div className="flex items-center gap-2 mb-2 text-navy-900">
              <Award className="w-5 h-5 text-teal-600" />
              <h3 className="font-heading font-bold text-sm">Tips Mempertahankan Rating</h3>
            </div>
            <p className="text-xs text-slate-600 leading-relaxed">
              Jaga ketepatan waktu pengantaran kurir dan pastikan pakaian selalu rapi, bersih, dan harum.
            </p>
          </Card>
        </div>

        {/* Filter Pills */}
        <Card variant="bento" className="p-4 sm:p-6 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold text-slate-600 flex items-center gap-1 mr-1">
              <Filter className="w-3.5 h-3.5 text-teal-600" />
              <span>Filter Bintang:</span>
            </span>

            <button
              type="button"
              onClick={() => {
                setScoreFilter(undefined);
                setCurrentPage(1);
              }}
              className={cn(
                'px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all min-h-[44px]',
                scoreFilter === undefined
                  ? 'bg-navy-900 text-white border-navy-900 shadow-xs'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
              )}
            >
              Semua ({totalRatings})
            </button>

            {[5, 4, 3, 2, 1].map((star) => (
              <button
                key={star}
                type="button"
                onClick={() => {
                  setScoreFilter(star);
                  setCurrentPage(1);
                }}
                className={cn(
                  'px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1 min-h-[44px]',
                  scoreFilter === star
                    ? 'bg-navy-900 text-white border-navy-900 shadow-xs'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
                )}
              >
                <span>{star} Bintang</span>
                <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
              </button>
            ))}
          </div>
        </Card>

        {/* Loading State */}
        {loading && (
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <Card key={i} variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 animate-pulse space-y-3">
                <div className="flex justify-between items-center">
                  <div className="h-5 bg-slate-200 rounded w-1/4"></div>
                  <div className="h-5 bg-slate-100 rounded w-20"></div>
                </div>
                <div className="h-12 bg-slate-100 rounded-2xl"></div>
              </Card>
            ))}
          </div>
        )}

        {/* Error State */}
        {!loading && error && (
          <ErrorState
            title="Gagal Memuat Ulasan"
            message={error}
            onRetry={fetchRatings}
            className="p-8 bg-white rounded-3xl border border-slate-200"
          />
        )}

        {/* Empty State */}
        {!loading && !error && ratings.length === 0 && (
          <EmptyState
            title="Belum Ada Ulasan"
            description={
              scoreFilter !== undefined
                ? `Tidak ada ulasan dengan rating ${scoreFilter} bintang.`
                : 'Belum ada pelanggan yang memberikan ulasan untuk toko ini.'
            }
            className="p-12 bg-white rounded-3xl border border-slate-200"
          />
        )}

        {/* Ratings List */}
        {!loading && !error && ratings.length > 0 && (
          <div className="space-y-4">
            {ratings.map((item) => (
              <Card
                key={item.id}
                variant="bento"
                className="p-6 space-y-3 hover:border-teal-300 transition-all"
              >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full bg-teal-100 text-teal-800 flex items-center justify-center font-bold text-sm shrink-0">
                      {item.customer_avatar ? (
                        <img
                          src={item.customer_avatar}
                          alt={item.customer_name}
                          className="w-full h-full rounded-full object-cover"
                        />
                      ) : (
                        item.customer_name.charAt(0).toUpperCase()
                      )}
                    </div>
                    <div>
                      <span className="font-heading font-bold text-sm text-navy-900 block">
                        {item.customer_name}
                      </span>
                      <span className="text-[11px] text-slate-400 tabular-nums">
                        {formatDate(item.created_at)}
                      </span>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <Rating value={item.score} max={5} size="md" showText />
                  </div>
                </div>

                {/* Review Text */}
                {item.review ? (
                  <p className="text-xs sm:text-sm text-slate-700 leading-relaxed bg-slate-50/60 p-4 rounded-2xl border border-slate-100 whitespace-pre-line">
                    "{item.review}"
                  </p>
                ) : (
                  <p className="text-xs text-slate-400 italic">
                    (Pelanggan memberikan rating bintang tanpa ulasan teks)
                  </p>
                )}

                {/* Link to order */}
                {item.order_id && (
                  <div className="flex justify-end pt-2">
                    <Link
                      href={`/orders/${item.order_id}`}
                      className="inline-flex items-center gap-1.5 text-xs font-semibold text-teal-700 hover:text-teal-800 min-h-[44px]"
                    >
                      <ShoppingBag className="w-3.5 h-3.5" />
                      <span>Pesanan #{item.order_number}</span>
                    </Link>
                  </div>
                )}
              </Card>
            ))}

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 pt-4">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage === 1}
                  className="rounded-xl text-xs min-h-[44px]"
                >
                  Sebelumnya
                </Button>
                <span className="text-xs text-slate-500 font-semibold px-3">
                  Halaman {currentPage} dari {totalPages}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                  disabled={currentPage === totalPages}
                  className="rounded-xl text-xs min-h-[44px]"
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

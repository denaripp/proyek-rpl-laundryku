'use client';

import React, { useState, useEffect } from 'react';
import { Card, Rating, Badge, Skeleton, EmptyState, ErrorState, Button } from '@/components/ui';
import { getStoreRatings, RatingItem, RatingSummary } from '@/lib/rating';
import { formatDate, cn } from '@/lib/format';
import { Star, MessageSquare, ThumbsUp, Filter, User } from 'lucide-react';

interface StoreReviewsSectionProps {
  storeId: string;
}

export const StoreReviewsSection: React.FC<StoreReviewsSectionProps> = ({ storeId }) => {
  const [ratings, setRatings] = useState<RatingItem[]>([]);
  const [summary, setSummary] = useState<RatingSummary>({
    average_score: 0,
    total_ratings: 0,
    breakdown: { '5': 0, '4': 0, '3': 0, '2': 0, '1': 0 },
  });
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [scoreFilter, setScoreFilter] = useState<number | undefined>(undefined);
  const [hasReviewFilter, setHasReviewFilter] = useState<boolean>(false);
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [totalPages, setTotalPages] = useState<number>(1);

  const fetchReviews = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getStoreRatings(storeId, {
        score: scoreFilter,
        has_review: hasReviewFilter ? true : undefined,
        page: currentPage,
      });
      setRatings(res.data);
      if (res.meta?.summary) {
        setSummary(res.meta.summary);
      }
      setTotalPages(res.meta?.last_page || 1);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Gagal memuat ulasan toko.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReviews();
  }, [storeId, scoreFilter, hasReviewFilter, currentPage]);

  const totalReviews = summary.total_ratings || 0;

  return (
    <Card variant="bento" className="p-6 sm:p-8 space-y-6">
      {/* Title */}
      <div className="border-b border-slate-100 pb-4">
        <h2 className="font-heading font-bold text-xl text-navy-900 flex items-center gap-2">
          <Star className="w-5 h-5 text-amber-400 fill-amber-400" />
          <span>Ulasan & Penilaian Pelanggan</span>
        </h2>
        <p className="text-xs sm:text-sm text-slate-500 mt-1">
          Pengalaman nyata dari pelanggan yang telah menggunakan jasa laundry ini.
        </p>
      </div>

      {/* Summary Score Card + Breakdown */}
      <div className="grid grid-cols-1 md:grid-cols-12 gap-6 p-5 bg-sky-50/70 rounded-2xl border border-sky-100">
        {/* Left: Overall Score (4 cols) */}
        <div className="md:col-span-4 flex flex-col items-center justify-center text-center p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span className="font-heading font-extrabold text-4xl sm:text-5xl text-navy-900 tabular-nums mb-1">
            {summary.average_score.toFixed(1)}
          </span>
          <Rating value={summary.average_score} max={5} size="lg" showText={false} className="mb-2" />
          <span className="text-xs text-slate-500 font-medium">
            Berdasarkan <b>{totalReviews}</b> penilaian
          </span>
        </div>

        {/* Right: Star Breakdown Bars (8 cols) */}
        <div className="md:col-span-8 flex flex-col justify-center space-y-2 text-xs">
          {[5, 4, 3, 2, 1].map((star) => {
            const count = summary.breakdown ? summary.breakdown[star.toString() as '1'|'2'|'3'|'4'|'5'] || 0 : 0;
            const percentage = totalReviews > 0 ? (count / totalReviews) * 100 : 0;

            return (
              <div key={star} className="flex items-center gap-2">
                <span className="w-14 font-semibold text-slate-600 flex items-center gap-1 shrink-0">
                  <span>{star}</span>
                  <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                </span>

                <div className="flex-1 h-3 bg-slate-200/70 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-teal-500 rounded-full transition-all duration-500"
                    style={{ width: `${percentage}%` }}
                  />
                </div>

                <span className="w-12 text-right text-[11px] text-slate-500 font-medium tabular-nums shrink-0">
                  {count}
                </span>
              </div>
            );
          })}
        </div>
      </div>

      {/* Filter Tabs / Pills */}
      <div className="flex flex-wrap items-center gap-2 pt-2">
        <span className="text-xs font-semibold text-slate-600 flex items-center gap-1 mr-1">
          <Filter className="w-3.5 h-3.5 text-teal-600" />
          <span>Filter:</span>
        </span>

        <button
          type="button"
          onClick={() => {
            setScoreFilter(undefined);
            setCurrentPage(1);
          }}
          className={cn(
            'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all min-h-[44px]',
            scoreFilter === undefined
              ? 'bg-navy-900 text-white border-navy-900 shadow-xs'
              : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
          )}
        >
          Semua ({totalReviews})
        </button>

        {[5, 4, 3, 2, 1].map((star) => {
          const count = summary.breakdown ? summary.breakdown[star.toString() as '1'|'2'|'3'|'4'|'5'] || 0 : 0;
          return (
            <button
              key={star}
              type="button"
              onClick={() => {
                setScoreFilter(star);
                setCurrentPage(1);
              }}
              className={cn(
                'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1 min-h-[44px]',
                scoreFilter === star
                  ? 'bg-navy-900 text-white border-navy-900 shadow-xs'
                  : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
              )}
            >
              <span>{star} Bintang</span>
              <span className="text-[10px] opacity-80">({count})</span>
            </button>
          );
        })}

        <button
          type="button"
          onClick={() => {
            setHasReviewFilter(!hasReviewFilter);
            setCurrentPage(1);
          }}
          className={cn(
            'px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all flex items-center gap-1 min-h-[44px]',
            hasReviewFilter
              ? 'bg-teal-600 text-white border-teal-600 shadow-xs'
              : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'
          )}
        >
          <MessageSquare className="w-3.5 h-3.5" />
          <span>Hanya Ulasan Tertulis</span>
        </button>
      </div>

      {/* Loading State */}
      {loading && (
        <div className="space-y-4 pt-2">
          {[1, 2, 3].map((i) => (
            <div key={i} className="p-4 rounded-2xl border border-slate-200 bg-white space-y-2">
              <div className="flex items-center gap-3">
                <Skeleton className="w-9 h-9 rounded-full" />
                <div className="space-y-1">
                  <Skeleton className="w-28 h-4 rounded" />
                  <Skeleton className="w-20 h-3 rounded" />
                </div>
              </div>
              <Skeleton className="w-full h-12 rounded-xl" />
            </div>
          ))}
        </div>
      )}

      {/* Error State */}
      {!loading && error && (
        <ErrorState
          title="Gagal Memuat Ulasan"
          message={error}
          onRetry={fetchReviews}
          className="p-6 bg-white rounded-2xl border border-slate-200"
        />
      )}

      {/* Empty State */}
      {!loading && !error && ratings.length === 0 && (
        <EmptyState
          title="Belum Ada Ulasan"
          description={
            scoreFilter !== undefined || hasReviewFilter
              ? 'Tidak ada ulasan yang sesuai dengan filter yang dipilih.'
              : 'Toko ini belum memiliki ulasan dari pelanggan.'
          }
          className="p-8 bg-white rounded-2xl border border-slate-200"
        />
      )}

      {/* Reviews List */}
      {!loading && !error && ratings.length > 0 && (
        <div className="space-y-4 pt-2">
          {ratings.map((item) => (
            <div
              key={item.id}
              className="p-4 sm:p-5 rounded-2xl border border-slate-200/80 bg-white hover:border-teal-300/60 transition-all space-y-3"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                {/* Customer info */}
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-full bg-teal-100 text-teal-800 flex items-center justify-center font-bold text-xs shrink-0">
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

                {/* Rating score */}
                <div className="flex items-center gap-1.5">
                  <Rating value={item.score} max={5} size="sm" showText />
                </div>
              </div>

              {/* Review Text */}
              {item.review ? (
                <p className="text-xs sm:text-sm text-slate-700 leading-relaxed bg-slate-50/60 p-3 rounded-xl border border-slate-100 whitespace-pre-line">
                  "{item.review}"
                </p>
              ) : (
                <p className="text-xs text-slate-400 italic">
                  (Pengguna memberikan rating bintang tanpa ulasan teks)
                </p>
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
    </Card>
  );
};

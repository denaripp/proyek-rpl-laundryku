'use client';

import React, { useState, useEffect } from 'react';
import { Card, Button, Rating, Textarea, Badge } from '@/components/ui';
import { submitOrderRating, getOrderRating, RatingItem } from '@/lib/rating';
import { formatDate } from '@/lib/format';
import { Star, CheckCircle2, AlertCircle, MessageSquare, Sparkles } from 'lucide-react';

interface OrderRatingCardProps {
  orderId: string;
  orderNumber: string;
  isCompleted: boolean;
}

export const OrderRatingCard: React.FC<OrderRatingCardProps> = ({
  orderId,
  orderNumber,
  isCompleted,
}) => {
  const [ratingData, setRatingData] = useState<RatingItem | null>(null);
  const [loadingRating, setLoadingRating] = useState<boolean>(true);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [selectedScore, setSelectedScore] = useState<number>(5);
  const [reviewText, setReviewText] = useState<string>('');
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  useEffect(() => {
    if (!isCompleted) {
      setLoadingRating(false);
      return;
    }

    const fetchRating = async () => {
      setLoadingRating(true);
      try {
        const res = await getOrderRating(orderId);
        setRatingData(res.data);
      } catch (e) {
        // Not rated or error
        setRatingData(null);
      } finally {
        setLoadingRating(false);
      }
    };

    fetchRating();
  }, [orderId, isCompleted]);

  const handleSubmitRating = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedScore || selectedScore < 1 || selectedScore > 5) {
      setErrorMsg('Pilih nilai rating bintang (1 - 5).');
      return;
    }

    setIsSubmitting(true);
    setErrorMsg(null);
    setSuccessMsg(null);

    try {
      const res = await submitOrderRating(orderId, selectedScore, reviewText);
      setRatingData(res.data);
      setSuccessMsg('Terima kasih! Ulasan dan penilaian Anda berhasil disimpan.');
    } catch (err: unknown) {
      const errObj = err as { message?: string };
      setErrorMsg(errObj?.message || 'Gagal mengirim ulasan rating. Silakan coba lagi.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isCompleted) {
    return null;
  }

  if (loadingRating) {
    return (
      <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 animate-pulse">
        <div className="h-5 bg-slate-200 rounded w-1/3 mb-4"></div>
        <div className="h-20 bg-slate-100 rounded-2xl"></div>
      </Card>
    );
  }

  return (
    <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-4">
      <div className="border-b border-slate-100 pb-3">
        <h3 className="font-heading font-bold text-base text-navy-900 flex items-center gap-2">
          <Star className="w-4 h-4 text-amber-400 fill-amber-400" />
          <span>Penilaian & Ulasan Layanan</span>
        </h3>
        <p className="text-xs text-slate-500">
          Berikan masukan pengalaman Anda untuk membantu toko meningkatkan kualitas pelayanan.
        </p>
      </div>

      {/* State 1: Already Rated */}
      {ratingData ? (
        <div className="p-4 bg-teal-50/60 rounded-2xl border border-teal-200/80 space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <Badge variant="teal" className="text-xs font-semibold px-2.5 py-0.5 rounded-full flex items-center gap-1">
                <CheckCircle2 className="w-3.5 h-3.5 text-teal-600" />
                <span>Ulasan Anda</span>
              </Badge>
              <Rating value={ratingData.score} max={5} size="md" showText />
            </div>
            <span className="text-[11px] text-slate-400 tabular-nums">
              {formatDate(ratingData.created_at)}
            </span>
          </div>

          {ratingData.review ? (
            <p className="text-xs text-navy-900 font-medium whitespace-pre-line bg-white p-3 rounded-xl border border-teal-100">
              "{ratingData.review}"
            </p>
          ) : (
            <p className="text-xs text-slate-400 italic">
              (Tidak ada ulasan tertulis)
            </p>
          )}

          {successMsg && (
            <p className="text-xs font-semibold text-teal-700">{successMsg}</p>
          )}
        </div>
      ) : (
        /* State 2: Form to Submit Rating */
        <form onSubmit={handleSubmitRating} className="space-y-4 text-xs">
          {errorMsg && (
            <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 flex items-start gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{errorMsg}</span>
            </div>
          )}

          {/* Star Selection */}
          <div className="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/70 text-center space-y-2">
            <span className="font-semibold text-navy-900 text-xs block">
              Bagaimana kepuasan Anda terhadap cucian dan pelayanan toko?
            </span>
            <div className="flex justify-center py-1">
              <Rating
                value={selectedScore}
                max={5}
                size="lg"
                interactive
                onChange={(score) => setSelectedScore(score)}
                showText
              />
            </div>
            <span className="text-[11px] text-slate-400 block">
              {selectedScore === 5 && '⭐️ Sangat Puas! Hasil cucian sangat bersih & wangi'}
              {selectedScore === 4 && '⭐️ Puas dengan pelayanan dan hasil cuci'}
              {selectedScore === 3 && '⭐️ Cukup baik, namun ada yang perlu ditingkatkan'}
              {selectedScore === 2 && '⭐️ Kurang puas dengan pelayanan'}
              {selectedScore === 1 && '⭐️ Sangat kecewa dengan layanan toko'}
            </span>
          </div>

          {/* Review Text */}
          <div className="space-y-1.5">
            <label className="font-bold text-slate-700 block">
              Tulis Ulasan (Opsional)
            </label>
            <Textarea
              rows={3}
              value={reviewText}
              onChange={(e) => setReviewText(e.target.value)}
              placeholder="Ceritakan kepuasan Anda tentang ketepatan waktu kurir, keharuman pakaian, atau kerapian lipatan..."
              className="text-xs"
              maxLength={1000}
            />
            <div className="flex justify-end text-[11px] text-slate-400">
              <span>{reviewText.length} / 1000</span>
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <Button
              type="submit"
              variant="primary"
              isLoading={isSubmitting}
              disabled={isSubmitting}
              className="rounded-xl text-xs min-h-[44px] bg-navy-900 hover:bg-navy-800"
            >
              <Sparkles className="w-4 h-4 mr-1.5 text-teal-400" />
              Kirim Penilaian
            </Button>
          </div>
        </form>
      )}
    </Card>
  );
};

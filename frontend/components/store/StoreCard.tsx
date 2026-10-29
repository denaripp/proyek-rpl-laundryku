'use client';

import React from 'react';
import Link from 'next/link';
import { formatRupiah, formatOpeningHours } from '@/lib/format';
import { StoreItem } from '@/lib/store';
import { MapPin, Clock, ArrowRight, Star } from 'lucide-react';

export interface StoreCardProps {
  store: StoreItem;
  variant?: 'default' | 'compact';
  isSelected?: boolean;
  onSelect?: (store: StoreItem) => void;
  className?: string;
}

function getInitials(name: string): string {
  if (!name) return 'LK';
  const clean = name.trim().replace(/[^\w\s]/gi, '');
  const words = clean.split(/\s+/).filter(Boolean);
  if (words.length === 0) return 'LK';
  if (words.length === 1) {
    return words[0].slice(0, 2).toUpperCase();
  }
  return (words[0][0] + words[1][0]).toUpperCase();
}

export const StoreCard: React.FC<StoreCardProps> = ({
  store,
  variant = 'default',
  isSelected = false,
  onSelect,
  className = '',
}) => {
  const isServiceable = store.is_serviceable !== false;
  const hasDistance = store.distance_km !== undefined && store.distance_km !== null;

  const openingSummary = React.useMemo(() => {
    if (store.jam_buka_tutup_hari_ini) return store.jam_buka_tutup_hari_ini;
    if (!store.opening_hours) return 'Buka Setiap Hari';
    if (typeof store.opening_hours === 'object') {
      const hours = Object.values(store.opening_hours);
      if (hours.length > 0 && hours[0]) {
        const formatted = formatOpeningHours(hours[0]);
        return formatted === 'Tutup' ? 'Tutup' : `Buka: ${formatted}`;
      }
      return 'Buka Setiap Hari';
    }
    return 'Buka Setiap Hari';
  }, [store.opening_hours, store.jam_buka_tutup_hari_ini]);

  const openStatus = React.useMemo(() => {
    if (store.is_open_now !== undefined) {
      if (store.is_open_now) {
        return { isOpen: true, text: 'Buka' };
      }
      const openTime =
        typeof store.opening_hours_today === 'object' && store.opening_hours_today !== null
          ? store.opening_hours_today.open
          : null;
      if (openTime) {
        return { isOpen: false, text: `Tutup, buka ${openTime}` };
      }
      return { isOpen: false, text: 'Tutup' };
    }
    return { isOpen: true, text: 'Buka' };
  }, [store.is_open_now, store.opening_hours_today]);

  const initials = React.useMemo(() => getInitials(store.name), [store.name]);
  const shortAddress = store.short_address || store.alamat_singkat || store.address_text;

  const serviceChips = React.useMemo(() => {
    if (store.top_services && store.top_services.length > 0) {
      return store.top_services;
    }
    if (store.tiga_layanan_teratas && store.tiga_layanan_teratas.length > 0) {
      return store.tiga_layanan_teratas;
    }
    if (store.services && store.services.length > 0) {
      return store.services.slice(0, 3).map((s) => s.name);
    }
    if (store.categories && store.categories.length > 0) {
      return store.categories.slice(0, 3).map((c) => c.name.split('(')[0].trim());
    }
    return [];
  }, [store.top_services, store.tiga_layanan_teratas, store.services, store.categories]);

  const ratingValue =
    store.rating !== undefined && store.rating !== null
      ? store.rating
      : store.rating_average !== undefined && store.rating_average !== null
      ? store.rating_average
      : null;
  const reviewsCount =
    store.reviews_count !== undefined && store.reviews_count !== null
      ? store.reviews_count
      : store.rating_count !== undefined && store.rating_count !== null
      ? store.rating_count
      : null;

  // Compact variant specifically tuned for landing page showcase
  if (variant === 'compact') {
    return (
      <Link
        href={`/stores/${store.id}`}
        aria-label={`Toko ${store.name}`}
        className={`group bg-white border border-slate-200/90 hover:border-navy-300 hover:shadow-md rounded-2xl p-4 transition-all flex flex-col justify-between h-full focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-navy-700 focus-visible:ring-offset-2 ${className}`}
      >
        <div className="space-y-3">
          {/* Top Row: Squircle Monogram + Name, Address & Status */}
          <div className="flex items-start gap-3">
            <div
              className="w-10 h-10 min-w-10 min-h-10 rounded-xl bg-navy-50 border border-navy-200/80 flex items-center justify-center font-extrabold text-navy-800 text-sm tracking-wider select-none shrink-0 shadow-2xs"
              aria-hidden="true"
            >
              {initials}
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <h3 className="font-bold text-sm text-navy-900 group-hover:text-navy-700 transition-colors truncate">
                  {store.name}
                </h3>
                <span
                  className={`text-[11px] font-semibold shrink-0 ${
                    openStatus.isOpen ? 'text-emerald-700' : 'text-slate-500'
                  }`}
                >
                  {openStatus.text}
                </span>
              </div>

              <p className="text-xs text-slate-500 truncate mt-0.5" title={store.address_text}>
                {shortAddress}
              </p>
            </div>
          </div>

          {/* Service Chips from API (max 3) */}
          {serviceChips.length > 0 && (
            <div className="flex flex-wrap gap-1.5 pt-0.5">
              {serviceChips.slice(0, 3).map((chip, idx) => (
                <span
                  key={idx}
                  className="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-medium bg-navy-50/70 border border-navy-100 text-navy-800 truncate max-w-full"
                >
                  {chip}
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Bottom Row: Starting price, optional distance, rating */}
        <div className="pt-3 border-t border-slate-100 mt-4 flex items-center justify-between gap-2 text-xs">
          <div>
            {store.starting_price !== null && store.starting_price !== undefined ? (
              <p className="text-xs text-slate-700">
                Mulai{' '}
                <span className="font-bold text-navy-900 tabular-nums">
                  {formatRupiah(store.starting_price)}
                </span>
                <span className="text-slate-500 font-normal">/{store.starting_unit || 'kg'}</span>
              </p>
            ) : (
              <span className="text-[11px] text-slate-500 font-medium">Layanan Lengkap</span>
            )}
          </div>

          <div className="flex items-center gap-2 text-[11px] shrink-0">
            {hasDistance && (
              <span className="inline-flex items-center gap-1 font-semibold text-slate-700 tabular-nums">
                <MapPin className="w-3 h-3 text-slate-400" strokeWidth={1.5} />
                <span>{store.distance_km} km</span>
              </span>
            )}

            {ratingValue && Number(ratingValue) > 0 ? (
              <span className="inline-flex items-center gap-1 font-semibold text-slate-800">
                <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                <span className="tabular-nums">{Number(ratingValue).toFixed(1)}</span>
                {reviewsCount !== undefined && reviewsCount !== null && Number(reviewsCount) > 0 && (
                  <span className="text-slate-400 text-[10px] tabular-nums font-normal">({reviewsCount})</span>
                )}
              </span>
            ) : (
              <span className="text-slate-400 font-normal">Belum ada ulasan</span>
            )}
          </div>
        </div>
      </Link>
    );
  }

  // Default Variant (for Store Search list)
  return (
    <div
      onClick={() => onSelect?.(store)}
      className={`bg-white border rounded-2xl p-4 sm:p-5 transition-all flex flex-col justify-between cursor-pointer hover:shadow-md ${
        isSelected
          ? 'border-navy-700 ring-2 ring-navy-700/20 bg-navy-50/20'
          : 'border-slate-200/90 hover:border-navy-200'
      } ${!isServiceable ? 'opacity-80' : ''} ${className}`}
    >
      <div>
        {/* Header badges */}
        <div className="flex items-center justify-between gap-2 mb-2.5 text-xs">
          <div className="flex items-center gap-2">
            {hasDistance && (
              <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-lg tabular-nums">
                <MapPin className="w-3 h-3 text-slate-500" strokeWidth={1.75} />
                <span>{store.distance_km} km</span>
              </span>
            )}
            <span
              className={`inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-0.5 rounded-lg ${
                isServiceable
                  ? 'text-emerald-800 bg-emerald-50 border border-emerald-200/60'
                  : 'text-slate-600 bg-slate-100 border border-slate-200'
              }`}
            >
              <span className={`w-1.5 h-1.5 rounded-full ${isServiceable ? 'bg-emerald-500' : 'bg-slate-400'}`} />
              <span>{isServiceable ? 'Bisa antar-jemput' : `Luar radius (${store.service_radius_km} km)`}</span>
            </span>
          </div>

          <span className="text-[11px] text-slate-400 font-mono">
            Maks {store.service_radius_km} km
          </span>
        </div>

        {/* Title */}
        <h3 className="font-bold text-base text-navy-900 group-hover:text-navy-700 transition-colors line-clamp-1">
          {store.name}
        </h3>

        {/* Rating or Neutral Review State */}
        <div className="flex items-center gap-1.5 text-xs text-slate-600 mt-1 mb-2">
          {ratingValue && Number(ratingValue) > 0 ? (
            <div className="flex items-center gap-1 font-semibold text-slate-800">
              <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
              <span className="tabular-nums">{Number(ratingValue).toFixed(1)}</span>
              {reviewsCount !== undefined && Number(reviewsCount) > 0 && (
                <span className="text-slate-400 text-[11px] font-normal">({reviewsCount} ulasan)</span>
              )}
            </div>
          ) : (
            <span className="text-[11px] text-slate-400">Belum ada ulasan</span>
          )}
        </div>

        {/* Address */}
        <p className="text-xs text-slate-500 line-clamp-1 mb-3">
          {store.address_text}
        </p>

        {/* Category Pills */}
        {store.categories && store.categories.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-3">
            {store.categories.slice(0, 3).map((cat) => (
              <span
                key={cat.id}
                className="inline-flex items-center px-2 py-0.5 rounded-lg bg-navy-50/70 border border-navy-100/80 text-navy-800 text-[10px] font-semibold"
              >
                {cat.name.split('(')[0].trim()}
              </span>
            ))}
          </div>
        )}
      </div>

      {/* Footer Info & Action Button */}
      <div className="pt-3 border-t border-slate-100 mt-1 flex items-center justify-between gap-2 text-xs">
        <div>
          <div className="flex items-center gap-1 text-[11px] text-slate-500 mb-0.5">
            <Clock className="w-3 h-3 text-slate-400" strokeWidth={1.5} />
            <span>{openingSummary}</span>
          </div>
          {store.starting_price !== null && store.starting_price !== undefined ? (
            <p className="text-xs text-slate-700">
              Mulai <span className="font-bold text-navy-900 tabular-nums">{formatRupiah(store.starting_price)}</span>
              <span className="text-slate-500 font-normal">/{store.starting_unit || 'kg'}</span>
            </p>
          ) : (
            <span className="text-[11px] text-slate-400">Layanan Lengkap</span>
          )}
        </div>

        <Link
          href={`/stores/${store.id}`}
          onClick={(e) => e.stopPropagation()}
          className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 active:scale-95 transition-all shrink-0 shadow-2xs"
        >
          <span>Pilih laundry</span>
          <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
        </Link>
      </div>
    </div>
  );
};

export default StoreCard;

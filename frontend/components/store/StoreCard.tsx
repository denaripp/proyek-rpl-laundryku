'use client';

import React from 'react';
import Link from 'next/link';
import { formatRupiah } from '@/lib/format';
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
      return hours[0] ? `Buka: ${hours[0]}` : 'Buka Setiap Hari';
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
        className={`group bg-white border border-slate-200 hover:border-slate-300 rounded-[8px] p-4 transition-colors flex flex-col justify-between h-full focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#1A3A5C] focus-visible:ring-offset-2 ${className}`}
      >
        <div className="space-y-3">
          {/* Top Row: 40px Monogram + Name, Address & Status */}
          <div className="flex items-start gap-3">
            <div
              className="w-10 h-10 min-w-10 min-h-10 rounded-md bg-slate-100 flex items-center justify-center font-semibold text-slate-700 text-sm tracking-wider select-none shrink-0"
              aria-hidden="true"
            >
              {initials}
            </div>

            <div className="min-w-0 flex-1">
              <div className="flex items-center justify-between gap-2">
                <h3 className="font-semibold text-sm text-slate-900 group-hover:text-[#1A3A5C] transition-colors truncate">
                  {store.name}
                </h3>
                <span
                  className={`text-[11px] font-medium shrink-0 ${
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

          {/* Service Chips from API (max 3, 1px border slate-200) */}
          {serviceChips.length > 0 && (
            <div className="flex flex-wrap gap-1.5 pt-0.5">
              {serviceChips.slice(0, 3).map((chip, idx) => (
                <span
                  key={idx}
                  className="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-50 border border-slate-200 text-slate-600 truncate max-w-full"
                >
                  {chip}
                </span>
              ))}
            </div>
          )}
        </div>

        {/* Bottom Row: Starting price, optional distance, rating / neutral review */}
        <div className="pt-3 border-t border-slate-100 mt-4 flex items-center justify-between gap-2 text-xs">
          <div>
            {store.starting_price !== null && store.starting_price !== undefined ? (
              <p className="text-xs text-slate-700">
                Mulai{' '}
                <span className="font-semibold text-slate-900 tabular-nums">
                  {formatRupiah(store.starting_price)}
                </span>
                <span className="text-slate-500 font-normal"> / {store.starting_unit || 'kg'}</span>
              </p>
            ) : (
              <span className="text-[11px] text-slate-500">Layanan Lengkap</span>
            )}
          </div>

          <div className="flex items-center gap-2 text-[11px] shrink-0">
            {hasDistance && (
              <span className="inline-flex items-center gap-1 font-medium text-slate-600 tabular-nums">
                <MapPin className="w-3 h-3 text-slate-400" strokeWidth={1.5} />
                <span>{store.distance_km} km</span>
              </span>
            )}

            {ratingValue && Number(ratingValue) > 0 ? (
              <span className="inline-flex items-center gap-1 font-medium text-slate-800">
                <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                <span className="tabular-nums">{Number(ratingValue).toFixed(1)}</span>
                {reviewsCount !== undefined && reviewsCount !== null && Number(reviewsCount) > 0 && (
                  <span className="text-slate-400 text-[10px] tabular-nums">({reviewsCount})</span>
                )}
              </span>
            ) : (
              <span className="text-slate-400">Belum ada ulasan</span>
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
      className={`bg-white border rounded-lg p-4 transition-colors flex flex-col justify-between cursor-pointer ${
        isSelected
          ? 'border-slate-800 ring-1 ring-slate-800'
          : 'border-slate-200 hover:border-slate-300'
      } ${!isServiceable ? 'opacity-75' : ''} ${className}`}
    >
      <div>
        {/* Header badges */}
        <div className="flex items-center justify-between gap-2 mb-2 text-xs">
          <div className="flex items-center gap-1.5">
            {hasDistance && (
              <span className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                <MapPin className="w-3 h-3 text-slate-500" />
                <span>{store.distance_km} km</span>
              </span>
            )}
            <span
              className={`inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded ${
                isServiceable
                  ? 'text-emerald-700 bg-emerald-50'
                  : 'text-slate-600 bg-slate-100'
              }`}
            >
              <span className={`w-1.5 h-1.5 rounded-full ${isServiceable ? 'bg-emerald-500' : 'bg-slate-400'}`} />
              <span>{isServiceable ? 'Bisa Antar-Jemput' : `Luar Radius (${store.service_radius_km}km)`}</span>
            </span>
          </div>

          <span className="text-[11px] text-slate-400 font-mono">
            Maks {store.service_radius_km} km
          </span>
        </div>

        {/* Title */}
        <h3 className="font-semibold text-sm text-slate-900 group-hover:text-slate-700 transition-colors line-clamp-1">
          {store.name}
        </h3>

        {/* Real Rating or Neutral Review State */}
        <div className="flex items-center gap-1 text-xs text-slate-600 mt-1 mb-2">
          {ratingValue && Number(ratingValue) > 0 ? (
            <div className="flex items-center gap-1 font-medium text-slate-800">
              <Star className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
              <span>{Number(ratingValue).toFixed(1)}</span>
              {reviewsCount !== undefined && Number(reviewsCount) > 0 && (
                <span className="text-slate-400 text-[11px]">({reviewsCount} ulasan)</span>
              )}
            </div>
          ) : (
            <span className="text-[11px] text-slate-400">Belum ada ulasan</span>
          )}
        </div>

        {/* Address */}
        <p className="text-xs text-slate-500 line-clamp-1 mb-2.5">
          {store.address_text}
        </p>

        {/* Category Pills */}
        {store.categories && store.categories.length > 0 && (
          <div className="flex flex-wrap gap-1 mb-3">
            {store.categories.slice(0, 3).map((cat) => (
              <span
                key={cat.id}
                className="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-50 border border-slate-200 text-slate-600 text-[10px]"
              >
                {cat.name.split('(')[0].trim()}
              </span>
            ))}
          </div>
        )}
      </div>

      {/* Footer Info & Action Button */}
      <div className="pt-2.5 border-t border-slate-100 mt-1 flex items-center justify-between gap-2 text-xs">
        <div>
          <div className="flex items-center gap-1 text-[11px] text-slate-500 mb-0.5">
            <Clock className="w-3 h-3 text-slate-400" />
            <span>{openingSummary}</span>
          </div>
          {store.starting_price !== null && store.starting_price !== undefined ? (
            <p className="text-xs text-slate-700">
              Mulai <span className="font-semibold text-slate-900 tabular-nums">{formatRupiah(store.starting_price)}</span>
            </p>
          ) : (
            <span className="text-[11px] text-slate-400">Layanan Lengkap</span>
          )}
        </div>

        <Link
          href={`/stores/${store.id}`}
          onClick={(e) => e.stopPropagation()}
          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-medium text-white bg-[#1A3A5C] hover:bg-[#132d47] transition-colors shrink-0"
        >
          <span>Pilih</span>
          <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.5} />
        </Link>
      </div>
    </div>
  );
};

export default StoreCard;

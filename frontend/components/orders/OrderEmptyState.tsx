'use client';

import React from 'react';
import Link from 'next/link';
import { cn } from '@/lib/format';
import { Package, Search, Sparkles, ArrowRight } from 'lucide-react';

export interface OrderEmptyStateProps {
  activeTab: string;
  searchQuery?: string;
  onResetSearch?: () => void;
  onResetFilter?: () => void;
  className?: string;
}

export const OrderEmptyState: React.FC<OrderEmptyStateProps> = ({
  activeTab,
  searchQuery,
  onResetSearch,
  onResetFilter,
  className,
}) => {
  // If search active
  if (searchQuery && searchQuery.trim().length > 0) {
    return (
      <div
        className={cn(
          'p-8 sm:p-12 text-center bg-white border border-navy-100 rounded-3xl space-y-3 shadow-2xs',
          className
        )}
      >
        <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center shadow-2xs">
          <Search className="w-7 h-7" strokeWidth={1.75} />
        </div>
        <div className="space-y-1">
          <h3 className="text-sm sm:text-base font-bold text-navy-900">Pesanan tidak ditemukan</h3>
          <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
            Tidak ada pesanan yang cocok dengan &quot;{searchQuery}&quot;.
          </p>
        </div>
        <div className="pt-2">
          <button
            type="button"
            onClick={onResetSearch}
            className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
          >
            <span>Reset pencarian</span>
          </button>
        </div>
      </div>
    );
  }

  // If specific tab filter is empty
  if (activeTab === 'active') {
    return (
      <div
        className={cn(
          'p-8 sm:p-12 text-center bg-white border border-navy-100 rounded-3xl space-y-3 shadow-2xs',
          className
        )}
      >
        <div className="w-14 h-14 mx-auto rounded-2xl bg-sky-50 text-sky-700 border border-sky-100 flex items-center justify-center shadow-2xs">
          <Package className="w-7 h-7" strokeWidth={1.75} />
        </div>
        <div className="space-y-1">
          <h3 className="text-sm sm:text-base font-bold text-navy-900">Tidak ada pesanan yang sedang berjalan</h3>
          <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
            Cucian kotor menumpuk? Pesan layanan laundry antar-jemput sekarang.
          </p>
        </div>
        <div className="pt-2">
          <Link
            href="/stores"
            className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors shadow-2xs"
          >
            <span>Pesan laundry baru</span>
            <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
          </Link>
        </div>
      </div>
    );
  }

  if (activeTab === 'completed') {
    return (
      <div
        className={cn(
          'p-8 sm:p-12 text-center bg-white border border-navy-100 rounded-3xl space-y-3 shadow-2xs',
          className
        )}
      >
        <div className="w-14 h-14 mx-auto rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center justify-center shadow-2xs">
          <Sparkles className="w-7 h-7" strokeWidth={1.75} />
        </div>
        <div className="space-y-1">
          <h3 className="text-sm sm:text-base font-bold text-navy-900">Belum ada pesanan selesai</h3>
          <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
            Pesanan yang telah selesai dan diantar akan tercatat di sini.
          </p>
        </div>
        {onResetFilter && (
          <div className="pt-2">
            <button
              type="button"
              onClick={onResetFilter}
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
            >
              <span>Lihat semua pesanan</span>
            </button>
          </div>
        )}
      </div>
    );
  }

  if (activeTab === 'cancelled') {
    return (
      <div
        className={cn(
          'p-8 sm:p-12 text-center bg-white border border-navy-100 rounded-3xl space-y-3 shadow-2xs',
          className
        )}
      >
        <div className="w-14 h-14 mx-auto rounded-2xl bg-slate-50 text-slate-500 border border-slate-200 flex items-center justify-center shadow-2xs">
          <Package className="w-7 h-7" strokeWidth={1.75} />
        </div>
        <div className="space-y-1">
          <h3 className="text-sm sm:text-base font-bold text-navy-900">Tidak ada pesanan yang dibatalkan</h3>
          <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
            Semua transaksi berjalan lancar tanpa pembatalan.
          </p>
        </div>
        {onResetFilter && (
          <div className="pt-2">
            <button
              type="button"
              onClick={onResetFilter}
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
            >
              <span>Lihat semua pesanan</span>
            </button>
          </div>
        )}
      </div>
    );
  }

  // Default empty state
  return (
    <div
      className={cn(
        'p-8 sm:p-12 text-center bg-white border border-navy-100 rounded-3xl space-y-3 shadow-2xs',
        className
      )}
    >
      <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center shadow-2xs">
        <Package className="w-7 h-7" strokeWidth={1.75} />
      </div>
      <div className="space-y-1">
        <h3 className="text-sm sm:text-base font-bold text-navy-900">Belum ada pesanan</h3>
        <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
          Temukan laundry terdekat dan nikmati kemudahan antar-jemput cucian.
        </p>
      </div>
      <div className="pt-2">
        <Link
          href="/stores"
          className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors shadow-2xs"
        >
          <span>Cari laundry</span>
          <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
        </Link>
      </div>
    </div>
  );
};

export default OrderEmptyState;

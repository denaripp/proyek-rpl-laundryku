'use client';

import React from 'react';
import { cn } from '@/lib/format';
import { Search, X } from 'lucide-react';

export type OrderSortOption = 'newest' | 'oldest' | 'highest_amount' | 'lowest_amount';

export interface OrderToolbarProps {
  searchQuery: string;
  onSearchChange: (val: string) => void;
  sortBy: OrderSortOption;
  onSortChange: (val: OrderSortOption) => void;
  className?: string;
}

export const OrderToolbar: React.FC<OrderToolbarProps> = ({
  searchQuery,
  onSearchChange,
  sortBy,
  onSortChange,
  className,
}) => {
  return (
    <div
      className={cn(
        'flex flex-col sm:flex-row sm:items-center justify-between gap-3',
        className
      )}
    >
      {/* Search Input (Height 42px, squircle rounded-xl) */}
      <div className="relative flex-1 min-w-0">
        <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <Search className="w-4 h-4 text-slate-400" strokeWidth={1.75} />
        </div>
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Cari toko, nomor pesanan, atau layanan..."
          className="w-full h-10.5 pl-10 pr-9 bg-white text-xs sm:text-sm font-medium text-navy-900 placeholder:text-slate-400 rounded-xl border border-slate-200/90 hover:border-navy-200 focus:border-navy-700 focus:ring-2 focus:ring-navy-700/15 focus:outline-hidden transition-all shadow-2xs"
          aria-label="Cari riwayat pesanan"
        />
        {searchQuery && (
          <button
            type="button"
            onClick={() => onSearchChange('')}
            className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-navy-900 focus:outline-hidden cursor-pointer"
            aria-label="Hapus pencarian"
          >
            <X className="w-4 h-4" strokeWidth={1.75} />
          </button>
        )}
      </div>

      {/* Sort Select */}
      <div className="sm:w-52 shrink-0 relative">
        <select
          value={sortBy}
          onChange={(e) => onSortChange(e.target.value as OrderSortOption)}
          className="w-full h-10.5 pl-3.5 pr-8 bg-white text-xs sm:text-sm font-semibold text-slate-700 hover:text-navy-900 rounded-xl border border-slate-200/90 hover:border-navy-200 focus:border-navy-700 focus:ring-2 focus:ring-navy-700/15 focus:outline-hidden transition-all cursor-pointer shadow-2xs"
          aria-label="Urutkan pesanan"
        >
          <option value="newest">Terbaru</option>
          <option value="oldest">Terlama</option>
          <option value="highest_amount">Total tertinggi</option>
          <option value="lowest_amount">Total terendah</option>
        </select>
      </div>
    </div>
  );
};

export default OrderToolbar;

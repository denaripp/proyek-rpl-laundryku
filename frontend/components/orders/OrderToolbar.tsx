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
      {/* Search Input (Height 40px, border slate-300) */}
      <div className="relative flex-1 min-w-0">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
          <Search className="w-4 h-4" strokeWidth={1.5} />
        </div>
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Cari toko, nomor pesanan, atau layanan..."
          className="w-full h-10 min-h-[40px] pl-9 pr-9 bg-white text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 rounded-lg border border-slate-300 hover:border-slate-400 focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15 focus:outline-hidden transition-colors"
          aria-label="Cari riwayat pesanan"
        />
        {searchQuery && (
          <button
            type="button"
            onClick={() => onSearchChange('')}
            className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-hidden cursor-pointer"
            aria-label="Hapus pencarian"
          >
            <X className="w-3.5 h-3.5" strokeWidth={1.5} />
          </button>
        )}
      </div>

      {/* Sort Select (Height 40px, border slate-300) */}
      <div className="sm:w-48 shrink-0">
        <select
          value={sortBy}
          onChange={(e) => onSortChange(e.target.value as OrderSortOption)}
          className="w-full h-10 min-h-[40px] px-3 bg-white text-xs sm:text-sm font-medium text-slate-800 rounded-lg border border-slate-300 hover:border-slate-400 focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15 focus:outline-hidden transition-colors cursor-pointer"
          aria-label="Urutkan pesanan"
        >
          <option value="newest">Terbaru</option>
          <option value="oldest">Terlama</option>
          <option value="highest_amount">Total Tertinggi</option>
          <option value="lowest_amount">Total Terendah</option>
        </select>
      </div>
    </div>
  );
};

export default OrderToolbar;

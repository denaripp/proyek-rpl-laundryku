'use client';

import React from 'react';
import Link from 'next/link';
import { cn } from '@/lib/format';

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
          'p-8 text-center bg-white border border-slate-200 rounded-lg text-sm text-slate-500',
          className
        )}
      >
        <p className="inline">
          Tidak ada pesanan yang cocok dengan &quot;{searchQuery}&quot;.
        </p>{' '}
        <button
          type="button"
          onClick={onResetSearch}
          className="text-[#1A3A5C] hover:underline font-medium inline-block cursor-pointer"
        >
          Reset pencarian
        </button>
      </div>
    );
  }

  // If specific tab filter is empty
  if (activeTab === 'active') {
    return (
      <div
        className={cn(
          'p-8 text-center bg-white border border-slate-200 rounded-lg text-sm text-slate-500',
          className
        )}
      >
        <p className="inline">Tidak ada pesanan yang sedang berjalan.</p>{' '}
        <Link
          href="/stores"
          className="text-[#1A3A5C] hover:underline font-medium inline-block"
        >
          Pesan laundry baru →
        </Link>
      </div>
    );
  }

  if (activeTab === 'completed') {
    return (
      <div
        className={cn(
          'p-8 text-center bg-white border border-slate-200 rounded-lg text-sm text-slate-500',
          className
        )}
      >
        <p className="inline">Belum ada pesanan selesai.</p>{' '}
        {onResetFilter && (
          <button
            type="button"
            onClick={onResetFilter}
            className="text-[#1A3A5C] hover:underline font-medium inline-block cursor-pointer"
          >
            Lihat semua pesanan →
          </button>
        )}
      </div>
    );
  }

  if (activeTab === 'cancelled') {
    return (
      <div
        className={cn(
          'p-8 text-center bg-white border border-slate-200 rounded-lg text-sm text-slate-500',
          className
        )}
      >
        <p className="inline">Tidak ada pesanan yang dibatalkan.</p>{' '}
        {onResetFilter && (
          <button
            type="button"
            onClick={onResetFilter}
            className="text-[#1A3A5C] hover:underline font-medium inline-block cursor-pointer"
          >
            Lihat semua pesanan →
          </button>
        )}
      </div>
    );
  }

  // Default empty state
  return (
    <div
      className={cn(
        'p-8 text-center bg-white border border-slate-200 rounded-lg text-sm text-slate-500',
        className
      )}
    >
      <p className="inline">Belum ada pesanan.</p>{' '}
      <Link
        href="/stores"
        className="text-[#1A3A5C] hover:underline font-medium inline-block"
      >
        Cari toko laundry →
      </Link>
    </div>
  );
};

export default OrderEmptyState;

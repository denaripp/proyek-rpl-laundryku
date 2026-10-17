'use client';

import React from 'react';
import { cn } from '@/lib/format';

export interface OrderStatsCounts {
  all: number;
  active: number;
  completed: number;
  cancelled: number;
}

export interface OrderStatsCardsProps {
  counts: OrderStatsCounts;
  loading?: boolean;
  className?: string;
}

export const OrderStatsCards: React.FC<OrderStatsCardsProps> = ({
  counts,
  loading = false,
  className,
}) => {
  if (loading) {
    return (
      <div className={cn('h-5 w-64 bg-slate-100 animate-pulse rounded', className)} />
    );
  }

  return (
    <div className={cn('flex items-center gap-2.5 text-xs text-slate-600 flex-wrap', className)}>
      <span>
        Semua <strong className="font-medium text-slate-900 tabular-nums">{counts.all}</strong>
      </span>
      <span className="text-slate-300">·</span>
      <span>
        Berjalan <strong className="font-medium text-slate-900 tabular-nums">{counts.active}</strong>
      </span>
      <span className="text-slate-300">·</span>
      <span>
        Selesai <strong className="font-medium text-slate-900 tabular-nums">{counts.completed}</strong>
      </span>
      <span className="text-slate-300">·</span>
      <span>
        Dibatalkan <strong className="font-medium text-slate-900 tabular-nums">{counts.cancelled}</strong>
      </span>
    </div>
  );
};

export default OrderStatsCards;

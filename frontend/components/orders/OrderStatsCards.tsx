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
  activeTab?: string;
  onTabChange?: (tabId: string) => void;
}

export const OrderStatsCards: React.FC<OrderStatsCardsProps> = ({
  counts,
  loading = false,
  className,
  activeTab = 'all',
  onTabChange,
}) => {
  if (loading) {
    return (
      <div className={cn('h-8 w-72 bg-white/60 animate-pulse rounded-xl', className)} />
    );
  }

  const items = [
    {
      id: 'all',
      label: 'Semua',
      count: counts.all,
      activeClass: 'bg-navy-900 text-white border-navy-900 shadow-sm font-bold ring-2 ring-navy-900/15',
      activeCountClass: 'text-sky-300 font-bold',
      inactiveClass: 'bg-white border-navy-100/90 text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-2xs font-medium',
      inactiveCountClass: 'text-navy-900 font-bold',
    },
    {
      id: 'active',
      label: 'Sedang berjalan',
      count: counts.active,
      activeClass: 'bg-sky-600 text-white border-sky-600 shadow-sm font-bold ring-2 ring-sky-500/20',
      activeCountClass: 'text-white font-bold',
      inactiveClass: 'bg-sky-50/80 border-sky-200/80 text-sky-900 hover:bg-sky-100/80 hover:border-sky-300 shadow-2xs font-medium',
      inactiveCountClass: 'text-sky-950 font-bold',
    },
    {
      id: 'completed',
      label: 'Selesai',
      count: counts.completed,
      activeClass: 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-bold ring-2 ring-emerald-500/20',
      activeCountClass: 'text-white font-bold',
      inactiveClass: 'bg-emerald-50/80 border-emerald-200/80 text-emerald-900 hover:bg-emerald-100/80 hover:border-emerald-300 shadow-2xs font-medium',
      inactiveCountClass: 'text-emerald-950 font-bold',
    },
    {
      id: 'cancelled',
      label: 'Dibatalkan',
      count: counts.cancelled,
      activeClass: 'bg-slate-700 text-white border-slate-700 shadow-sm font-bold ring-2 ring-slate-400/20',
      activeCountClass: 'text-white font-bold',
      inactiveClass: 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 hover:border-slate-300 shadow-2xs font-medium',
      inactiveCountClass: 'text-slate-800 font-bold',
    },
  ];

  return (
    <div className={cn('flex items-center gap-2 flex-wrap text-xs select-none', className)}>
      {items.map((item) => {
        const isActive = activeTab === item.id;
        return (
          <button
            key={item.id}
            type="button"
            aria-pressed={isActive}
            onClick={() => onTabChange?.(item.id)}
            className={cn(
              'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border transition-all cursor-pointer text-xs focus:outline-hidden focus:ring-2 focus:ring-navy-600/20 active:scale-95',
              isActive ? item.activeClass : item.inactiveClass
            )}
          >
            <span>{item.label}</span>
            <strong className={cn('tabular-nums', isActive ? item.activeCountClass : item.inactiveCountClass)}>
              {item.count}
            </strong>
          </button>
        );
      })}
    </div>
  );
};

export default OrderStatsCards;

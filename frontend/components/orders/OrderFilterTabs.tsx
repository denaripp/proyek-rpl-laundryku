'use client';

import React from 'react';
import { cn } from '@/lib/format';

export interface OrderFilterTabsProps {
  activeTab: string;
  onChange: (tabId: string) => void;
  counts: {
    all: number;
    active: number;
    completed: number;
    cancelled: number;
  };
  className?: string;
}

export const OrderFilterTabs: React.FC<OrderFilterTabsProps> = ({
  activeTab,
  onChange,
  counts,
  className,
}) => {
  const tabs = [
    { id: 'all', label: 'Semua', count: counts.all },
    { id: 'active', label: 'Sedang berjalan', count: counts.active },
    { id: 'completed', label: 'Selesai', count: counts.completed },
    { id: 'cancelled', label: 'Dibatalkan', count: counts.cancelled },
  ];

  return (
    <div
      className={cn('overflow-x-auto select-none no-scrollbar py-1', className)}
      role="tablist"
      aria-label="Filter status pesanan"
    >
      <div className="inline-flex items-center gap-1.5 bg-slate-100/90 p-1.5 rounded-2xl border border-slate-200/80 min-w-max shadow-2xs">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;

          return (
            <button
              key={tab.id}
              role="tab"
              aria-selected={isActive}
              type="button"
              onClick={() => onChange(tab.id)}
              className={cn(
                'px-3.5 py-1.5 text-xs font-semibold rounded-xl transition-all cursor-pointer flex items-center gap-2 focus:outline-hidden min-h-[38px]',
                isActive
                  ? 'bg-white text-navy-900 shadow-sm border border-navy-100/80 font-bold'
                  : 'text-slate-600 hover:text-navy-900 hover:bg-white/50'
              )}
            >
              <span>{tab.label}</span>
              <span
                className={cn(
                  'px-1.5 py-0.5 rounded-lg text-[10px] tabular-nums font-bold leading-none',
                  isActive
                    ? 'bg-navy-50 text-navy-800'
                    : 'bg-slate-200/70 text-slate-600'
                )}
              >
                {tab.count}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default OrderFilterTabs;

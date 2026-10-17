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
    { id: 'active', label: 'Sedang Berjalan', count: counts.active },
    { id: 'completed', label: 'Selesai', count: counts.completed },
    { id: 'cancelled', label: 'Dibatalkan', count: counts.cancelled },
  ];

  return (
    <div
      className={cn('border-b border-slate-200 overflow-x-auto select-none', className)}
      role="tablist"
      aria-label="Filter status pesanan"
    >
      <div className="flex items-center gap-6 min-w-max">
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
                'py-2.5 text-xs sm:text-sm font-medium transition-colors relative cursor-pointer flex items-center gap-1.5 focus:outline-hidden',
                isActive
                  ? 'text-slate-900 font-semibold'
                  : 'text-slate-500 hover:text-slate-800'
              )}
            >
              <span>{tab.label}</span>
              <span className={cn('text-xs tabular-nums', isActive ? 'text-slate-900 font-semibold' : 'text-slate-400')}>
                {tab.count}
              </span>

              {/* Active underline indicator */}
              {isActive && (
                <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-[#1A3A5C] rounded-full" />
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default OrderFilterTabs;

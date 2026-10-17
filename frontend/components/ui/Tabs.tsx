'use client';

import React from 'react';
import { cn } from '@/lib/format';

export interface TabItem {
  id: string;
  label: React.ReactNode;
  icon?: React.ComponentType<{ className?: string }>;
  count?: number;
}

export interface TabsProps {
  tabs: TabItem[];
  activeTab: string;
  onChange: (tabId: string) => void;
  variant?: 'pill' | 'underline' | 'boxed';
  className?: string;
}

export const Tabs = ({
  tabs,
  activeTab,
  onChange,
  variant = 'pill',
  className,
}: TabsProps) => {
  return (
    <div className={cn('w-full overflow-x-auto no-scrollbar', className)}>
      <div
        className={cn(
          'flex items-center gap-1.5 p-1',
          variant === 'boxed' && 'bg-slate-100 rounded-2xl p-1.5 border border-slate-200/60 inline-flex min-w-full sm:min-w-0',
          variant === 'underline' && 'border-b border-slate-200 gap-6'
        )}
        role="tablist"
      >
        {tabs.map((tab) => {
          const isActive = tab.id === activeTab;
          const Icon = tab.icon;

          if (variant === 'underline') {
            return (
              <button
                key={tab.id}
                role="tab"
                aria-selected={isActive}
                onClick={() => onChange(tab.id)}
                className={cn(
                  'flex items-center gap-2 py-3 px-1 text-sm font-semibold border-b-2 transition-all min-h-[44px]',
                  isActive
                    ? 'border-teal-500 text-navy-900'
                    : 'border-transparent text-slate-500 hover:text-navy-900 hover:border-slate-300'
                )}
              >
                {Icon && <Icon className={cn('w-4 h-4', isActive ? 'text-teal-600' : 'text-slate-400')} />}
                <span>{tab.label}</span>
                {typeof tab.count === 'number' && (
                  <span
                    className={cn(
                      'text-xs px-2 py-0.5 rounded-full font-bold',
                      isActive ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-600'
                    )}
                  >
                    {tab.count}
                  </span>
                )}
              </button>
            );
          }

          if (variant === 'boxed') {
            return (
              <button
                key={tab.id}
                role="tab"
                aria-selected={isActive}
                onClick={() => onChange(tab.id)}
                className={cn(
                  'flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all min-h-[40px] flex-1 sm:flex-initial',
                  isActive
                    ? 'bg-white text-navy-900 shadow-sm'
                    : 'text-slate-600 hover:text-navy-900 hover:bg-slate-200/50'
                )}
              >
                {Icon && <Icon className={cn('w-4 h-4', isActive ? 'text-teal-600' : 'text-slate-400')} />}
                <span>{tab.label}</span>
                {typeof tab.count === 'number' && (
                  <span
                    className={cn(
                      'text-xs px-1.5 py-0.2 rounded-full font-bold',
                      isActive ? 'bg-teal-50 text-teal-700' : 'bg-slate-200 text-slate-700'
                    )}
                  >
                    {tab.count}
                  </span>
                )}
              </button>
            );
          }

          // Pill variant (default)
          return (
            <button
              key={tab.id}
              role="tab"
              aria-selected={isActive}
              onClick={() => onChange(tab.id)}
              className={cn(
                'flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all min-h-[44px]',
                isActive
                  ? 'bg-navy-900 text-white shadow-sm'
                  : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'
              )}
            >
              {Icon && <Icon className={cn('w-4 h-4', isActive ? 'text-teal-400' : 'text-slate-400')} />}
              <span>{tab.label}</span>
              {typeof tab.count === 'number' && (
                <span
                  className={cn(
                    'text-xs px-2 py-0.5 rounded-full font-bold',
                    isActive ? 'bg-navy-800 text-teal-400' : 'bg-slate-100 text-slate-600'
                  )}
                >
                  {tab.count}
                </span>
              )}
            </button>
          );
        })}
      </div>
    </div>
  );
};

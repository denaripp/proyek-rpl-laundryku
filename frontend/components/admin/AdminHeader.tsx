import React from 'react';
import { RefreshCw } from 'lucide-react';

interface AdminHeaderProps {
  title: string;
  subtitle?: string;
  onRefresh?: () => void;
  refreshing?: boolean;
  actions?: React.ReactNode;
}

export function AdminHeader({
  title,
  subtitle,
  onRefresh,
  refreshing = false,
  actions,
}: AdminHeaderProps) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 pb-2">
      <div>
        <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">{title}</h1>
        {subtitle && <p className="text-xs sm:text-sm text-slate-500 mt-1">{subtitle}</p>}
      </div>

      <div className="flex items-center gap-2 flex-wrap">
        {onRefresh && (
          <button
            onClick={onRefresh}
            disabled={refreshing}
            aria-label="Segarkan data"
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs disabled:opacity-50 transition-colors cursor-pointer min-h-[38px]"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? 'animate-spin' : ''}`} strokeWidth={1.75} />
            <span>Segarkan</span>
          </button>
        )}
        {actions}
      </div>
    </div>
  );
}

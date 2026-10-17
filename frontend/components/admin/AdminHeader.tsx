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
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6 pb-4 border-b border-slate-200">
      <div>
        <h1 className="text-lg font-semibold text-slate-900 tracking-tight">{title}</h1>
        {subtitle && <p className="text-xs text-slate-500 mt-0.5">{subtitle}</p>}
      </div>

      <div className="flex items-center gap-2">
        {onRefresh && (
          <button
            onClick={onRefresh}
            disabled={refreshing}
            aria-label="Segarkan data"
            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-slate-200 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 transition-colors cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? 'animate-spin' : ''}`} strokeWidth={1.5} />
            <span>Segarkan</span>
          </button>
        )}
        {actions}
      </div>
    </div>
  );
}

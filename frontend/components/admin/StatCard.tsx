import React from 'react';

interface StatCardProps {
  label: string;
  value: string | number;
  subtext?: string;
  tooltip?: string;
  trend?: string;
  className?: string;
}

export function StatCard({ label, value, subtext, tooltip, className = '' }: StatCardProps) {
  return (
    <div
      className={`bg-white border border-slate-200 rounded-lg p-4 relative group ${className}`}
      title={tooltip}
    >
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-slate-500 uppercase tracking-wider">{label}</span>
      </div>
      <div className="mt-2 flex items-baseline">
        <span className="text-2xl font-semibold text-slate-900 tabular-nums tracking-tight">
          {value}
        </span>
      </div>
      {subtext && (
        <div className="mt-1 text-xs text-slate-500 truncate" title={tooltip || subtext}>
          {subtext}
        </div>
      )}
    </div>
  );
}

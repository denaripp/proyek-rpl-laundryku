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
      className={`bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-2xs hover:border-slate-300 transition-colors relative group ${className}`}
      title={tooltip}
    >
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-slate-600">{label}</span>
      </div>
      <div className="mt-2 flex items-baseline">
        <span className="text-2xl font-bold font-mono text-navy-950 tabular-nums tracking-tight">
          {value}
        </span>
      </div>
      {subtext && (
        <div className="mt-1 text-xs text-slate-500 truncate font-medium" title={tooltip || subtext}>
          {subtext}
        </div>
      )}
    </div>
  );
}

import React from 'react';
import { cn } from '@/lib/format';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'default' | 'teal' | 'amber' | 'sky' | 'secondary' | 'outline' | 'success' | 'warning' | 'danger' | 'info';
  size?: 'sm' | 'md' | 'lg';
}

export const Badge = ({
  className,
  variant = 'default',
  size = 'md',
  children,
  ...props
}: BadgeProps) => {
  const variants = {
    default: 'bg-navy-900 text-white',
    teal: 'bg-teal-50 text-teal-800 border border-teal-200 font-semibold',
    amber: 'bg-amber-100 text-navy-900 border border-amber-300 font-semibold',
    sky: 'bg-sky-50 text-navy-800 border border-sky-200',
    secondary: 'bg-slate-100 text-slate-700 border border-slate-200',
    outline: 'border border-slate-300 text-slate-700 bg-transparent',
    success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    warning: 'bg-amber-50 text-amber-800 border border-amber-200',
    danger: 'bg-rose-50 text-rose-700 border border-rose-200',
    info: 'bg-blue-50 text-blue-700 border border-blue-200',
  };

  const sizes = {
    sm: 'text-[11px] px-2 py-0.5 font-medium',
    md: 'text-xs px-2.5 py-1 font-medium',
    lg: 'text-sm px-3.5 py-1.5 font-semibold',
  };

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full whitespace-nowrap transition-colors',
        variants[variant],
        sizes[size],
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
};

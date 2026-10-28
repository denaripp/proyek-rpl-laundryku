import React from 'react';

type StatusType = 'success' | 'warning' | 'danger' | 'neutral' | 'info';

interface StatusTextProps {
  status: string;
  type?: StatusType;
  className?: string;
}

export function StatusText({ status, type, className = '' }: StatusTextProps) {
  // Infer type if not explicitly passed
  let computedType: StatusType = type || 'neutral';
  const s = status.toUpperCase();

  if (!type) {
    if (['ACTIVE', 'APPROVED', 'PAID', 'COMPLETED', 'SUCCESS'].includes(s)) {
      computedType = 'success';
    } else if (['PENDING', 'AWAITING_PAYMENT', 'AWAITING_PRICE_APPROVAL', 'PROCESSING', 'PICKUP_ASSIGNED', 'DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY', 'WEIGHED'].includes(s)) {
      computedType = 'warning';
    } else if (['REJECTED', 'CANCELLED', 'PAYMENT_EXPIRED', 'PAYMENT_FAILED', 'SUSPENDED', 'BANNED', 'FAILED'].includes(s)) {
      computedType = 'danger';
    } else if (['PICKED_UP', 'AT_STORE', 'PRICE_APPROVED', 'READY_FOR_DELIVERY'].includes(s)) {
      computedType = 'info';
    }
  }

  const dotColors: Record<StatusType, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-sky-500',
    neutral: 'bg-slate-400',
  };

  const badgeStyles: Record<StatusType, string> = {
    success: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    warning: 'bg-amber-50 text-amber-800 border-amber-200',
    danger: 'bg-rose-50 text-rose-800 border-rose-200',
    info: 'bg-sky-50 text-sky-800 border-sky-200',
    neutral: 'bg-slate-100 text-slate-700 border-slate-200',
  };

  return (
    <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-semibold border ${badgeStyles[computedType]} ${className}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${dotColors[computedType]}`} />
      <span>{status}</span>
    </span>
  );
}

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
    info: 'bg-blue-500',
    neutral: 'bg-slate-400',
  };

  return (
    <span className={`inline-flex items-center gap-1.5 text-xs font-medium text-slate-700 ${className}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${dotColors[computedType]}`} />
      <span>{status}</span>
    </span>
  );
}

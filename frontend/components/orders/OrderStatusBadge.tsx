'use client';

import React from 'react';
import { OrderStatus, ORDER_STATUS_META } from '@/lib/order';
import { cn } from '@/lib/format';

export interface OrderStatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  status: OrderStatus;
  className?: string;
}

export function getOrderStatusStyles(status: OrderStatus): {
  badgeClass: string;
  dotClass: string;
} {
  switch (status) {
    // Selesai (Hijau Emerald)
    case 'COMPLETED':
      return {
        badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
        dotClass: 'bg-emerald-500',
      };

    // Menunggu / Perhatian / Pembayaran (Amber)
    case 'PENDING_PICKUP':
    case 'PICKUP_ASSIGNED':
    case 'AWAITING_PRICE_APPROVAL':
    case 'AWAITING_PAYMENT':
    case 'PAYMENT_EXPIRED':
      return {
        badgeClass: 'bg-amber-50 text-amber-800 border-amber-200/80',
        dotClass: 'bg-amber-500',
      };

    // Dibatalkan / Gagal (Merah Rose)
    case 'CANCELLED':
    case 'PAYMENT_FAILED':
      return {
        badgeClass: 'bg-rose-50 text-rose-800 border-rose-200/80',
        dotClass: 'bg-rose-500',
      };

    // Sedang diproses / dijemput / diantar (Sky / Navy)
    default:
      return {
        badgeClass: 'bg-sky-50 text-sky-800 border-sky-200/80',
        dotClass: 'bg-sky-500',
      };
  }
}

// Backward-compatibility export
export function getOrderStatusTextColor(status: OrderStatus): string {
  switch (status) {
    case 'COMPLETED':
      return 'text-emerald-700 font-semibold';
    case 'PENDING_PICKUP':
    case 'PICKUP_ASSIGNED':
    case 'AWAITING_PRICE_APPROVAL':
    case 'AWAITING_PAYMENT':
    case 'PAYMENT_EXPIRED':
      return 'text-amber-700 font-semibold';
    case 'CANCELLED':
    case 'PAYMENT_FAILED':
      return 'text-rose-700 font-semibold';
    default:
      return 'text-sky-700 font-semibold';
  }
}

export const OrderStatusBadge: React.FC<OrderStatusBadgeProps> = ({
  status,
  className,
  ...props
}) => {
  const meta = ORDER_STATUS_META[status] || {
    label: status,
  };

  const { badgeClass, dotClass } = getOrderStatusStyles(status);

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-bold border shadow-2xs whitespace-nowrap select-none',
        badgeClass,
        className
      )}
      {...props}
    >
      <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', dotClass)} aria-hidden="true" />
      <span>{meta.label}</span>
    </span>
  );
};

export default OrderStatusBadge;

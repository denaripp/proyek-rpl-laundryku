'use client';

import React from 'react';
import { OrderStatus, ORDER_STATUS_META } from '@/lib/order';
import { cn } from '@/lib/format';

export interface OrderStatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  status: OrderStatus;
  className?: string;
}

export function getOrderStatusTextColor(status: OrderStatus): string {
  switch (status) {
    // Selesai (Hijau)
    case 'COMPLETED':
      return 'text-emerald-700 font-medium';

    // Menunggu / Perhatian (Amber)
    case 'PENDING_PICKUP':
    case 'PICKUP_ASSIGNED':
    case 'AWAITING_PRICE_APPROVAL':
    case 'AWAITING_PAYMENT':
    case 'PAYMENT_EXPIRED':
      return 'text-amber-700 font-medium';

    // Dibatalkan / Gagal (Merah)
    case 'CANCELLED':
    case 'PAYMENT_FAILED':
      return 'text-rose-700 font-medium';

    // Diproses / Berjalan (Slate)
    default:
      return 'text-slate-700 font-medium';
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

  const textColor = getOrderStatusTextColor(status);

  return (
    <span className={cn('text-xs whitespace-nowrap', textColor, className)} {...props}>
      {meta.label}
    </span>
  );
};

export default OrderStatusBadge;

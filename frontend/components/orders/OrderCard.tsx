'use client';

import React, { useState, useEffect, useMemo } from 'react';
import Link from 'next/link';
import { OrderDetail, getOrderCategory } from '@/lib/order';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import { OrderStatusBadge } from './OrderStatusBadge';
import { Clock } from 'lucide-react';

export interface OrderCardProps {
  order: OrderDetail;
  isHighlighted?: boolean;
  className?: string;
}

export const OrderCard: React.FC<OrderCardProps> = ({
  order,
  isHighlighted = false,
  className,
}) => {
  const category = getOrderCategory(order.status);
  const isActive = category === 'active';
  const isAwaitingApproval = order.status === 'AWAITING_PRICE_APPROVAL';
  const isAwaitingPayment = order.status === 'AWAITING_PAYMENT';

  // Live countdown timer for AWAITING_PAYMENT
  const [nowTime, setNowTime] = useState<number>(() => Date.now());

  useEffect(() => {
    if (!isAwaitingPayment || !order.payment_expires_at) {
      return;
    }

    const interval = setInterval(() => {
      setNowTime(Date.now());
    }, 1000);

    return () => clearInterval(interval);
  }, [isAwaitingPayment, order.payment_expires_at]);

  const remainingSeconds = useMemo(() => {
    if (!isAwaitingPayment || !order.payment_expires_at) {
      return null;
    }
    const diff = Math.floor((new Date(order.payment_expires_at).getTime() - nowTime) / 1000);
    return Math.max(0, diff);
  }, [isAwaitingPayment, order.payment_expires_at, nowTime]);

  const isTimerExpired = remainingSeconds !== null && remainingSeconds <= 0;

  const formatCountdown = (seconds: number) => {
    if (seconds <= 0) return 'Kedaluwarsa';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;

    if (hours > 0) {
      return `${hours}j ${minutes}m ${secs}s`;
    }
    return `${minutes}m ${secs}s`;
  };

  // Extract services preview string
  const servicesText =
    order.items && order.items.length > 0
      ? order.items
          .map((item) => {
            const qty = item.quantity_actual || item.quantity_estimated;
            return `${item.service_name} (${qty} ${item.pricing_unit})`;
          })
          .join(', ')
      : 'Layanan Laundry Reguler';

  // Determine weight representation
  const weightText =
    order.actual_weight_kg !== null && order.actual_weight_kg !== undefined
      ? `${order.actual_weight_kg} kg`
      : order.estimated_weight_kg
      ? `${order.estimated_weight_kg} kg (est)`
      : null;

  // Final / estimated amount
  const displayAmount =
    order.final_amount !== null && order.final_amount !== undefined
      ? order.final_amount
      : order.estimated_amount;

  return (
    <div
      id={`order-row-${order.id}`}
      className={cn(
        'p-4 sm:p-5 transition-colors hover:bg-slate-50/70 text-slate-800',
        isHighlighted && 'bg-slate-50',
        className
      )}
    >
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        {/* Left Column: Store Name, Order Number, Date, Services Summary */}
        <div className="space-y-1 min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <Link
              href={`/orders/${order.id}`}
              className="font-medium text-slate-900 hover:text-[#1A3A5C] transition-colors text-sm sm:text-base truncate"
            >
              {order.store_name || 'Outlet Laundry Mitra'}
            </Link>
            <span className="text-slate-300">·</span>
            <span className="font-mono text-xs text-slate-500">
              #{order.order_number}
            </span>
            <span className="text-slate-300">·</span>
            <span className="text-xs text-slate-500">
              {formatDate(order.created_at, 'DD MMM YYYY, HH:mm')}
            </span>
          </div>

          <div className="flex items-center gap-2 text-xs text-slate-600 truncate">
            <span className="truncate" title={servicesText}>
              {servicesText}
            </span>
            {weightText && (
              <>
                <span className="text-slate-300">·</span>
                <span className="shrink-0 text-slate-500">{weightText}</span>
              </>
            )}
          </div>
        </div>

        {/* Right Column: Total Price, Status, and Action Link */}
        <div className="flex items-center justify-between sm:justify-end gap-4 sm:gap-6 shrink-0 pt-1 sm:pt-0">
          <div className="sm:text-right space-y-0.5">
            <div className="text-sm font-medium text-slate-900 tabular-nums">
              {formatRupiah(displayAmount)}
            </div>
            <div>
              <OrderStatusBadge status={order.status} />
            </div>
          </div>

          <div className="flex items-center gap-3">
            <Link
              href={`/orders/${order.id}`}
              className="text-xs sm:text-sm font-medium text-[#1A3A5C] hover:underline whitespace-nowrap focus:outline-hidden"
            >
              {isActive ? 'Lacak →' : 'Detail →'}
            </Link>
          </div>
        </div>
      </div>

      {/* Urgent Action Prompts if needed */}
      {isAwaitingApproval && (
        <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-3 text-xs text-amber-800">
          <span>
            Pihak toko telah menimbang cucian ({order.actual_weight_kg} kg). Menunggu persetujuan harga final.
          </span>
          <Link
            href={`/orders/${order.id}`}
            className="text-xs font-semibold text-amber-900 hover:underline shrink-0"
          >
            Tinjau Harga →
          </Link>
        </div>
      )}

      {isAwaitingPayment && (
        <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-3 text-xs text-amber-800">
          <span className="flex items-center gap-1.5">
            <Clock className="w-3.5 h-3.5 text-amber-600" />
            <span>
              {isTimerExpired
                ? 'Batas waktu pembayaran telah habis.'
                : `Selesaikan pembayaran (Sisa: ${formatCountdown(remainingSeconds || 0)})`}
            </span>
          </span>
          <Link
            href={`/orders/${order.id}`}
            className="text-xs font-semibold text-amber-900 hover:underline shrink-0"
          >
            {isTimerExpired ? 'Lihat Detail →' : 'Bayar Sekarang →'}
          </Link>
        </div>
      )}
    </div>
  );
};

export default OrderCard;

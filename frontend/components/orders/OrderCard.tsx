'use client';

import React, { useState, useEffect, useMemo } from 'react';
import Link from 'next/link';
import { OrderDetail, getOrderCategory } from '@/lib/order';
import { formatRupiah, formatDate, cn } from '@/lib/format';
import { OrderStatusBadge } from './OrderStatusBadge';
import { OrderProgressStepper } from './OrderProgressStepper';
import { Clock, ArrowRight, Store } from 'lucide-react';

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

  // Monogram initials
  const initials = useMemo(() => {
    const name = order.store_name || 'LK';
    return name
      .split(' ')
      .map((w) => w[0])
      .filter(Boolean)
      .slice(0, 2)
      .join('')
      .toUpperCase();
  }, [order.store_name]);

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
        'group bg-white border border-slate-200/90 hover:border-navy-300 hover:shadow-md rounded-3xl p-5 space-y-4 transition-all',
        isHighlighted && 'ring-2 ring-navy-400 bg-navy-50/20',
        className
      )}
    >
      {/* Top Header Row: Store Name, Monogram, Order Number, Date, Status */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3 min-w-0">
          <div
            className="w-10 h-10 min-w-10 min-h-10 rounded-xl bg-navy-50 border border-navy-200/80 flex items-center justify-center font-extrabold text-navy-800 text-sm tracking-wider select-none shrink-0 shadow-2xs"
            aria-hidden="true"
          >
            {initials}
          </div>

          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <Link
                href={`/orders/${order.id}`}
                className="font-bold text-navy-900 group-hover:text-navy-700 transition-colors text-sm sm:text-base truncate"
              >
                {order.store_name || 'Outlet Laundry Mitra'}
              </Link>
              <span className="text-slate-300">·</span>
              <span className="font-mono text-xs font-semibold text-slate-500">
                #{order.order_number}
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              {formatDate(order.created_at, 'DD MMM YYYY, HH:mm')}
            </p>
          </div>
        </div>

        {/* Status Badge in Header */}
        <div className="self-start sm:self-center shrink-0">
          <OrderStatusBadge status={order.status} />
        </div>
      </div>

      {/* Mini Stepper Progress (Active Orders only) */}
      {isActive && (
        <div className="pt-1 pb-1">
          <OrderProgressStepper status={order.status} />
        </div>
      )}

      {/* Urgent Action Prompts if needed */}
      {isAwaitingApproval && (
        <div className="p-3 bg-amber-50/90 border border-amber-200/80 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-amber-900">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-amber-600 shrink-0" strokeWidth={1.75} />
            <span className="font-medium">
              Cucian selesai ditimbang di laundry ({order.actual_weight_kg} kg). Menunggu persetujuan harga Anda.
            </span>
          </div>
          <Link
            href={`/orders/${order.id}`}
            className="inline-flex items-center gap-1 font-bold text-amber-900 hover:text-amber-950 underline shrink-0 cursor-pointer self-end sm:self-center"
          >
            <span>Tinjau harga</span>
            <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
          </Link>
        </div>
      )}

      {isAwaitingPayment && (
        <div className="p-3 bg-amber-50/90 border border-amber-200/80 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-amber-900">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-amber-600 shrink-0" strokeWidth={1.75} />
            <span className="font-medium">
              {isTimerExpired
                ? 'Batas waktu pembayaran telah habis.'
                : `Menunggu pembayaran (Sisa waktu: ${formatCountdown(remainingSeconds || 0)})`}
            </span>
          </div>
          <Link
            href={`/orders/${order.id}`}
            className="inline-flex items-center gap-1 font-bold text-amber-900 hover:text-amber-950 underline shrink-0 cursor-pointer self-end sm:self-center"
          >
            <span>{isTimerExpired ? 'Lihat detail' : 'Bayar sekarang'}</span>
            <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
          </Link>
        </div>
      )}

      {/* Bottom Row: Services summary & Price / Action Link */}
      <div className="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div className="flex items-center gap-2 text-slate-600 truncate flex-1 min-w-0">
          <span className="truncate font-medium text-slate-700" title={servicesText}>
            {servicesText}
          </span>
          {weightText && (
            <>
              <span className="text-slate-300">·</span>
              <span className="shrink-0 text-navy-800 font-semibold bg-navy-50/80 px-2 py-0.5 rounded-md border border-navy-100 text-[11px]">
                {weightText}
              </span>
            </>
          )}
        </div>

        <div className="flex items-center justify-between sm:justify-end gap-4 shrink-0 pt-1 sm:pt-0">
          <div className="text-left sm:text-right">
            <span className="text-[11px] text-slate-400 block sm:hidden">Total pesanan</span>
            <div className="text-base font-extrabold text-navy-900 tabular-nums">
              {formatRupiah(displayAmount)}
            </div>
          </div>

          <Link
            href={`/orders/${order.id}`}
            className={cn(
              'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 shadow-2xs',
              isActive
                ? 'bg-navy-700 hover:bg-navy-800 active:scale-95 text-white'
                : 'bg-white hover:bg-navy-50 text-navy-800 border border-slate-200/90 hover:border-navy-200'
            )}
          >
            <span>{isActive ? 'Lacak pesanan' : 'Lihat detail'}</span>
            <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
          </Link>
        </div>
      </div>
    </div>
  );
};

export default OrderCard;

'use client';

import React from 'react';
import { OrderStatus } from '@/lib/order';
import { cn } from '@/lib/format';
import { Check, Sparkles, Truck, Package, Scale } from 'lucide-react';

export interface OrderProgressStepperProps {
  status: OrderStatus;
  className?: string;
}

interface StepDef {
  key: string;
  label: string;
  sublabel: string;
  icon: React.ComponentType<{ className?: string }>;
  statuses: OrderStatus[];
}

const STEP_DEFINITIONS: StepDef[] = [
  {
    key: 'pickup',
    label: 'Penjemputan',
    sublabel: 'Kurir Jemput',
    icon: Truck,
    statuses: ['PENDING_PICKUP', 'PICKUP_ASSIGNED', 'PICKED_UP'],
  },
  {
    key: 'weigh_pay',
    label: 'Timbang & Bayar',
    sublabel: 'Harga & Bayar',
    icon: Scale,
    statuses: ['AT_STORE', 'WEIGHED', 'AWAITING_PRICE_APPROVAL', 'PRICE_APPROVED', 'AWAITING_PAYMENT', 'PAID'],
  },
  {
    key: 'process',
    label: 'Proses Cuci',
    sublabel: 'Cuci & Setrika',
    icon: Sparkles,
    statuses: ['PROCESSING', 'READY_FOR_DELIVERY'],
  },
  {
    key: 'delivery',
    label: 'Pengantaran',
    sublabel: 'Kurir Antar',
    icon: Package,
    statuses: ['DELIVERY_ASSIGNED', 'OUT_FOR_DELIVERY'],
  },
];

export function getActiveStepIndex(status: OrderStatus): number {
  if (status === 'COMPLETED') return 4;
  if (status === 'CANCELLED' || status === 'PAYMENT_FAILED' || status === 'PAYMENT_EXPIRED') return -1;

  for (let i = 0; i < STEP_DEFINITIONS.length; i++) {
    if (STEP_DEFINITIONS[i].statuses.includes(status)) {
      return i;
    }
  }
  return 0;
}

export const OrderProgressStepper = ({ status, className }: OrderProgressStepperProps) => {
  const currentStepIndex = getActiveStepIndex(status);

  // If cancelled or failed, do not render stepper
  if (currentStepIndex === -1) {
    return null;
  }

  return (
    <div className={cn('w-full py-2.5 px-3 bg-slate-50/80 rounded-2xl border border-slate-100', className)}>
      <div className="grid grid-cols-4 relative">
        {/* Background track bar */}
        <div className="absolute top-3.5 left-[12.5%] right-[12.5%] h-0.5 bg-slate-200 -z-0" />
        
        {/* Progress track bar filled */}
        <div
          className="absolute top-3.5 left-[12.5%] h-0.5 bg-teal-500 transition-all duration-300 -z-0"
          style={{
            width: `${Math.min(100, (Math.max(0, currentStepIndex) / (STEP_DEFINITIONS.length - 1)) * 75)}%`,
          }}
        />

        {STEP_DEFINITIONS.map((step, idx) => {
          const isCompleted = idx < currentStepIndex || status === 'COMPLETED';
          const isCurrent = idx === currentStepIndex && status !== 'COMPLETED';
          const Icon = step.icon;

          return (
            <div key={step.key} className="flex flex-col items-center text-center relative z-10">
              {/* Step Circle */}
              <div
                className={cn(
                  'w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200 text-xs font-bold shadow-xs',
                  isCompleted && 'bg-teal-600 text-white ring-2 ring-teal-100',
                  isCurrent && 'bg-navy-900 text-teal-400 ring-4 ring-teal-100 animate-pulse',
                  !isCompleted && !isCurrent && 'bg-white text-slate-400 border border-slate-200'
                )}
                title={`${step.label} (${isCompleted ? 'Selesai' : isCurrent ? 'Sedang Berlangsung' : 'Menunggu'})`}
              >
                {isCompleted ? (
                  <Check className="w-3.5 h-3.5 stroke-[3]" />
                ) : isCurrent ? (
                  <Icon className="w-3.5 h-3.5 text-teal-400" />
                ) : (
                  <span className="text-[10px] font-semibold">{idx + 1}</span>
                )}
              </div>

              {/* Labels */}
              <span
                className={cn(
                  'text-[11px] font-bold mt-1.5 line-clamp-1 leading-tight',
                  isCurrent && 'text-navy-900',
                  isCompleted && 'text-teal-900 font-semibold',
                  !isCompleted && !isCurrent && 'text-slate-600'
                )}
              >
                {step.label}
              </span>
              <span className="text-[10px] text-slate-600 hidden sm:block leading-tight">
                {step.sublabel}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

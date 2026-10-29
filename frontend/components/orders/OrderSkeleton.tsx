'use client';

import React from 'react';
import { Skeleton } from '@/components/ui/Skeleton';
import { cn } from '@/lib/format';

export interface OrderSkeletonProps {
  className?: string;
}

export const OrderSkeleton: React.FC<OrderSkeletonProps> = ({ className }) => {
  return (
    <div className={cn('space-y-4', className)}>
      {[1, 2, 3].map((i) => (
        <div
          key={i}
          className="bg-white border border-slate-200/90 rounded-3xl p-5 space-y-4 shadow-2xs animate-pulse"
        >
          {/* Top Row */}
          <div className="flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <Skeleton className="w-10 h-10 rounded-xl" />
              <div className="space-y-1.5">
                <Skeleton className="h-4 w-40 rounded-md" />
                <Skeleton className="h-3 w-28 rounded-md" />
              </div>
            </div>
            <Skeleton className="h-6 w-24 rounded-lg" />
          </div>

          {/* Stepper Skeleton placeholder */}
          <Skeleton className="h-12 w-full rounded-2xl" />

          {/* Bottom Row */}
          <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
            <Skeleton className="h-4 w-48 rounded-md" />
            <div className="flex items-center gap-3">
              <Skeleton className="h-5 w-20 rounded-md" />
              <Skeleton className="h-8 w-28 rounded-xl" />
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default OrderSkeleton;

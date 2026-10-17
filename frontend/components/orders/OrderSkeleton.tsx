'use client';

import React from 'react';
import { Skeleton } from '@/components/ui/Skeleton';
import { cn } from '@/lib/format';

export interface OrderSkeletonProps {
  className?: string;
}

export const OrderSkeleton: React.FC<OrderSkeletonProps> = ({ className }) => {
  return (
    <div
      className={cn(
        'divide-y divide-slate-100 border border-slate-200 rounded-lg bg-white overflow-hidden',
        className
      )}
    >
      {[1, 2, 3, 4].map((i) => (
        <div
          key={i}
          className="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
          {/* Left info skeleton */}
          <div className="space-y-2 flex-1 min-w-0">
            <div className="flex items-center gap-2">
              <Skeleton className="h-4 w-36 rounded" />
              <Skeleton className="h-3.5 w-24 rounded" />
              <Skeleton className="h-3.5 w-28 rounded" />
            </div>
            <Skeleton className="h-3.5 w-64 rounded" />
          </div>

          {/* Right info skeleton */}
          <div className="flex items-center justify-between sm:justify-end gap-4 shrink-0">
            <div className="space-y-1 sm:text-right">
              <Skeleton className="h-4 w-20 rounded sm:ml-auto" />
              <Skeleton className="h-3.5 w-16 rounded sm:ml-auto" />
            </div>
            <Skeleton className="h-4 w-14 rounded" />
          </div>
        </div>
      ))}
    </div>
  );
};

export default OrderSkeleton;

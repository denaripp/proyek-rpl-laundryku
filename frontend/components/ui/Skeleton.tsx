import React from 'react';
import { cn } from '@/lib/format';

export interface SkeletonProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'text' | 'circular' | 'rounded' | 'card';
}

export const Skeleton = ({
  className,
  variant = 'rounded',
  ...props
}: SkeletonProps) => {
  const variants = {
    text: 'h-4 w-full rounded-md',
    circular: 'rounded-full',
    rounded: 'rounded-xl',
    card: 'h-36 w-full rounded-2xl',
  };

  return (
    <div
      className={cn(
        'bg-slate-200/80 animate-shimmer relative overflow-hidden',
        variants[variant],
        className
      )}
      {...props}
    />
  );
};

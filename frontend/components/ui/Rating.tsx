'use client';

import React from 'react';
import { Star } from 'lucide-react';
import { cn } from '@/lib/format';

export interface RatingProps {
  value: number; // e.g. 4.8
  max?: number;
  count?: number; // total reviews
  size?: 'sm' | 'md' | 'lg';
  interactive?: boolean;
  onChange?: (val: number) => void;
  showText?: boolean;
  className?: string;
}

export const Rating = ({
  value,
  max = 5,
  count,
  size = 'md',
  interactive = false,
  onChange,
  showText = true,
  className,
}: RatingProps) => {
  const [hoverValue, setHoverValue] = React.useState<number | null>(null);

  const starSizes = {
    sm: 'w-3.5 h-3.5',
    md: 'w-4 h-4',
    lg: 'w-5 h-5',
  };

  const textSizes = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base',
  };

  const displayVal = hoverValue !== null ? hoverValue : value;

  return (
    <div className={cn('inline-flex items-center gap-1.5', className)}>
      <div className="flex items-center gap-0.5" role={interactive ? 'radiogroup' : undefined}>
        {Array.from({ length: max }).map((_, index) => {
          const starNumber = index + 1;
          const isFilled = starNumber <= Math.floor(displayVal);
          const isPartial = !isFilled && starNumber - 0.5 <= displayVal;

          return (
            <button
              key={index}
              type="button"
              disabled={!interactive}
              onClick={() => interactive && onChange?.(starNumber)}
              onMouseEnter={() => interactive && setHoverValue(starNumber)}
              onMouseLeave={() => interactive && setHoverValue(null)}
              className={cn(
                'focus:outline-none transition-transform',
                interactive ? 'cursor-pointer hover:scale-110 p-0.5' : 'cursor-default'
              )}
              aria-label={`${starNumber} dari ${max} bintang`}
            >
              <Star
                className={cn(
                  starSizes[size],
                  isFilled || isPartial
                    ? 'text-amber-400 fill-amber-400'
                    : 'text-slate-200 fill-transparent'
                )}
              />
            </button>
          );
        })}
      </div>

      {showText && (
        <span className={cn('font-bold text-navy-900 tabular-nums ml-0.5', textSizes[size])}>
          {value.toFixed(1)}
          {typeof count === 'number' && (
            <span className="font-normal text-slate-400 text-xs ml-1">
              ({count})
            </span>
          )}
        </span>
      )}
    </div>
  );
};

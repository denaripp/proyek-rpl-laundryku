import React, { useId } from 'react';
import { cn } from '@/lib/format';

export interface ToggleProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: React.ReactNode;
  description?: React.ReactNode;
}

export const Toggle = React.forwardRef<HTMLInputElement, ToggleProps>(
  ({ className, label, description, id, checked, ...props }, ref) => {
    const generatedId = useId();
    const inputId = id || generatedId;

    return (
      <label htmlFor={inputId} className="flex items-center justify-between gap-4 cursor-pointer group min-h-[44px] py-1 select-none">
        {(label || description) && (
          <div className="text-sm">
            {label && <span className="font-semibold text-slate-900 group-hover:text-navy-900">{label}</span>}
            {description && <p className="text-xs text-slate-500 mt-0.5">{description}</p>}
          </div>
        )}
        <div className="relative inline-flex items-center cursor-pointer">
          <input
            type="checkbox"
            id={inputId}
            ref={ref}
            checked={checked}
            className="peer sr-only"
            {...props}
          />
          <div
            className={cn(
              'w-11 h-6 bg-slate-200 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-400 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[""] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-500',
              className
            )}
          />
        </div>
      </label>
    );
  }
);

Toggle.displayName = 'Toggle';

import React, { useId } from 'react';
import { cn } from '@/lib/format';

export interface RadioProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: React.ReactNode;
  description?: React.ReactNode;
}

export const Radio = React.forwardRef<HTMLInputElement, RadioProps>(
  ({ className, label, description, id, checked, ...props }, ref) => {
    const generatedId = useId();
    const inputId = id || generatedId;

    return (
      <label htmlFor={inputId} className="flex items-start gap-3 cursor-pointer group min-h-[44px] py-1 select-none">
        <div className="relative flex items-center justify-center mt-0.5">
          <input
            type="radio"
            id={inputId}
            ref={ref}
            checked={checked}
            className="peer sr-only"
            {...props}
          />
          <div
            className={cn(
              'w-5 h-5 rounded-full border-2 transition-all duration-200 flex items-center justify-center',
              'border-slate-300 bg-white group-hover:border-navy-700',
              'peer-checked:border-teal-500 peer-checked:bg-white',
              'peer-focus-visible:ring-2 peer-focus-visible:ring-teal-400 peer-focus-visible:ring-offset-1',
              'peer-disabled:bg-slate-100 peer-disabled:border-slate-200 peer-disabled:cursor-not-allowed',
              className
            )}
          >
            <div className="w-2.5 h-2.5 rounded-full bg-teal-500 opacity-0 peer-checked:opacity-100 scale-0 peer-checked:scale-100 transition-all duration-200" />
          </div>
        </div>
        {(label || description) && (
          <div className="text-sm leading-tight">
            {label && <span className="font-medium text-slate-900 group-hover:text-navy-900">{label}</span>}
            {description && <p className="text-xs text-slate-500 mt-0.5">{description}</p>}
          </div>
        )}
      </label>
    );
  }
);

Radio.displayName = 'Radio';

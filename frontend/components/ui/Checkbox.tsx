import React, { useId } from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/format';

export interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: React.ReactNode;
  description?: React.ReactNode;
  error?: string;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, label, description, error, id, checked, ...props }, ref) => {
    const generatedId = useId();
    const inputId = id || generatedId;

    return (
      <div className="space-y-1">
        <label htmlFor={inputId} className="flex items-start gap-3 cursor-pointer group min-h-[44px] py-1 select-none">
          <div className="relative flex items-center justify-center mt-0.5">
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
                'w-5 h-5 rounded-md border-2 transition-all duration-200 flex items-center justify-center',
                'border-slate-300 bg-white group-hover:border-navy-700',
                'peer-checked:bg-teal-500 peer-checked:border-teal-500',
                'peer-focus-visible:ring-2 peer-focus-visible:ring-teal-400 peer-focus-visible:ring-offset-1',
                'peer-disabled:bg-slate-100 peer-disabled:border-slate-200 peer-disabled:cursor-not-allowed',
                error && 'border-rose-500',
                className
              )}
            >
              <Check className="w-3.5 h-3.5 text-navy-900 stroke-[3] opacity-0 peer-checked:opacity-100 transition-opacity" />
            </div>
          </div>
          {(label || description) && (
            <div className="text-sm leading-tight">
              {label && <span className="font-medium text-slate-900 group-hover:text-navy-900">{label}</span>}
              {description && <p className="text-xs text-slate-500 mt-0.5">{description}</p>}
            </div>
          )}
        </label>
        {error && <p className="text-xs font-medium text-rose-600 pl-8">{error}</p>}
      </div>
    );
  }
);

Checkbox.displayName = 'Checkbox';

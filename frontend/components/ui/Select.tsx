import React from 'react';
import { cn } from '@/lib/format';
import { ChevronDown } from 'lucide-react';

export interface SelectOption {
  value: string | number;
  label: string;
}

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
  helperText?: string;
  options?: SelectOption[];
  optional?: boolean;
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
  ({ className, label, error, helperText, options, children, optional, id, ...props }, ref) => {
    const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
    const helperId = helperText ? `${selectId}-helper` : undefined;
    const errorId = error ? `${selectId}-error` : undefined;

    return (
      <div className="w-full space-y-1 text-left">
        {label && (
          <label
            htmlFor={selectId}
            className="block text-xs sm:text-sm font-medium text-slate-900 tracking-tight"
          >
            {label}{' '}
            {props.required && (
              <span className="text-slate-500 font-normal text-xs">(wajib)</span>
            )}
            {optional && (
              <span className="text-slate-400 font-normal text-xs">(opsional)</span>
            )}
          </label>
        )}

        <div className="relative flex items-center">
          <select
            id={selectId}
            ref={ref}
            aria-describedby={error ? errorId : helperText ? helperId : undefined}
            aria-invalid={!!error}
            className={cn(
              'w-full h-10 min-h-[40px] rounded-lg bg-white border border-slate-300 text-slate-900 text-xs sm:text-sm px-3 pr-9 appearance-none transition-colors cursor-pointer',
              'focus:outline-hidden focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15',
              'disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed',
              error
                ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-400/20 text-rose-900'
                : 'hover:border-slate-400',
              className
            )}
            {...props}
          >
            {options
              ? options.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))
              : children}
          </select>

          <div className="absolute right-3 pointer-events-none text-slate-400">
            <ChevronDown className="w-4 h-4" strokeWidth={1.5} />
          </div>
        </div>

        {error ? (
          <p id={errorId} className="text-xs text-rose-600 font-medium">
            {error}
          </p>
        ) : helperText ? (
          <p id={helperId} className="text-xs text-slate-500">
            {helperText}
          </p>
        ) : null}
      </div>
    );
  }
);

Select.displayName = 'Select';

import React from 'react';
import { cn } from '@/lib/format';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  helperText?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
  rightElement?: React.ReactNode;
  optional?: boolean;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  (
    {
      className,
      label,
      error,
      helperText,
      leftIcon,
      rightIcon,
      rightElement,
      optional,
      id,
      ...props
    },
    ref
  ) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
    const helperId = helperText ? `${inputId}-helper` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
      <div className="w-full space-y-1 text-left">
        {label && (
          <div className="flex items-center justify-between">
            <label
              htmlFor={inputId}
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
            {rightElement && <div>{rightElement}</div>}
          </div>
        )}

        <div className="relative flex items-center">
          {leftIcon && (
            <div className="absolute left-3 flex items-center pointer-events-none text-slate-400">
              {leftIcon}
            </div>
          )}

          <input
            id={inputId}
            ref={ref}
            aria-describedby={error ? errorId : helperText ? helperId : undefined}
            aria-invalid={!!error}
            className={cn(
              'w-full h-10 min-h-[40px] rounded-lg bg-white border border-slate-300 text-slate-900 text-xs sm:text-sm placeholder:text-slate-400 px-3 transition-colors',
              'focus:outline-hidden focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15 focus:bg-white',
              'disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed',
              leftIcon ? 'pl-9' : '',
              rightIcon ? 'pr-9' : '',
              error
                ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-400/20 text-rose-900'
                : 'hover:border-slate-400',
              className
            )}
            {...props}
          />

          {rightIcon && (
            <div className="absolute right-3 flex items-center text-slate-400">
              {rightIcon}
            </div>
          )}
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

Input.displayName = 'Input';

import React from 'react';
import { cn } from '@/lib/format';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
  helperText?: string;
  optional?: boolean;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ className, label, error, helperText, optional, id, ...props }, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
    const helperId = helperText ? `${inputId}-helper` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
      <div className="w-full space-y-1 text-left">
        {label && (
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
        )}
        <textarea
          id={inputId}
          ref={ref}
          aria-describedby={error ? errorId : helperText ? helperId : undefined}
          aria-invalid={!!error}
          className={cn(
            'w-full px-3 py-2 rounded-lg border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 text-xs sm:text-sm transition-colors resize-y min-h-[80px]',
            'focus:outline-hidden focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15',
            error ? 'border-rose-400 focus:border-rose-500 text-rose-900' : 'hover:border-slate-400',
            props.disabled && 'bg-slate-50 text-slate-400 cursor-not-allowed resize-none',
            className
          )}
          {...props}
        />
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

Textarea.displayName = 'Textarea';

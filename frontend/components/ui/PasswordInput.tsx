import React, { useState } from 'react';
import { Eye, EyeOff, Lock } from 'lucide-react';
import { cn } from '@/lib/format';

export interface PasswordInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
  error?: string;
  helperText?: string;
  showLockIcon?: boolean;
}

export const PasswordInput = React.forwardRef<HTMLInputElement, PasswordInputProps>(
  ({ className, label, error, helperText, showLockIcon = true, id, autoComplete = 'current-password', ...props }, ref) => {
    const [showPassword, setShowPassword] = useState(false);
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    const togglePasswordVisibility = (e: React.MouseEvent<HTMLButtonElement>) => {
      e.preventDefault();
      setShowPassword((prev) => !prev);
    };

    return (
      <div className="w-full space-y-1.5 text-left">
        {label && (
          <label
            htmlFor={inputId}
            className="block text-xs font-semibold text-slate-700 tracking-tight"
          >
            {label}
            {props.required && <span className="text-red-500 ml-1">*</span>}
          </label>
        )}

        <div className="relative flex items-center">
          {showLockIcon && (
            <div className="absolute left-3.5 flex items-center pointer-events-none text-slate-400">
              <Lock className="w-4 h-4" />
            </div>
          )}

          <input
            id={inputId}
            ref={ref}
            type={showPassword ? 'text' : 'password'}
            autoComplete={autoComplete}
            className={cn(
              'w-full h-11 min-h-[44px] rounded-xl bg-white border border-slate-200 text-slate-900 text-sm placeholder:text-slate-400 px-3.5 transition-all duration-150',
              'focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-400/20 focus:bg-white',
              'disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed',
              showLockIcon ? 'pl-10' : '',
              'pr-11',
              error
                ? 'border-red-400 focus:border-red-500 focus:ring-red-400/20 text-red-900'
                : 'hover:border-slate-300',
              className
            )}
            {...props}
          />

          <button
            type="button"
            onClick={togglePasswordVisibility}
            aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            title={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            className="absolute right-2.5 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-teal-500/30 transition-colors"
          >
            {showPassword ? (
              <EyeOff className="w-4 h-4 text-slate-500" aria-hidden="true" />
            ) : (
              <Eye className="w-4 h-4 text-slate-400" aria-hidden="true" />
            )}
          </button>
        </div>

        {error ? (
          <p className="text-xs text-red-600 flex items-center gap-1 font-medium">
            <span>•</span> {error}
          </p>
        ) : helperText ? (
          <p className="text-xs text-slate-500">{helperText}</p>
        ) : null}
      </div>
    );
  }
);

PasswordInput.displayName = 'PasswordInput';

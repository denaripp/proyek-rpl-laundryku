import React from 'react';
import { cn } from '@/lib/format';
import { Loader2 } from 'lucide-react';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'teal';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      className,
      variant = 'primary',
      size = 'md',
      isLoading = false,
      leftIcon,
      rightIcon,
      disabled,
      children,
      ...props
    },
    ref
  ) => {
    const baseStyles =
      'inline-flex items-center justify-center font-medium transition-all duration-200 cursor-pointer select-none rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none active:scale-[0.98]';

    const variants = {
      primary:
        'bg-navy-900 text-white hover:bg-navy-800 active:bg-navy-700 shadow-sm shadow-navy-900/10',
      teal:
        'bg-teal-500 text-navy-900 font-semibold hover:bg-teal-400 active:bg-teal-600 shadow-sm shadow-teal-500/20',
      secondary:
        'bg-navy-50 text-navy-900 hover:bg-navy-100 active:bg-navy-200 border border-navy-100',
      outline:
        'bg-white text-navy-900 border border-slate-200 hover:bg-slate-50 active:bg-slate-100 shadow-xs',
      ghost:
        'bg-transparent text-slate-700 hover:bg-slate-100 hover:text-navy-900',
      danger:
        'bg-red-600 text-white hover:bg-red-700 active:bg-red-800 shadow-sm shadow-red-600/10',
    };

    const sizes = {
      sm: 'text-xs h-9 min-h-[36px] px-3.5 gap-1.5',
      md: 'text-sm h-11 min-h-[44px] px-5 gap-2',
      lg: 'text-base h-12 min-h-[48px] px-6 gap-2.5 font-semibold',
    };

    return (
      <button
        ref={ref}
        disabled={disabled || isLoading}
        className={cn(baseStyles, variants[variant], sizes[size], className)}
        {...props}
      >
        {isLoading ? (
          <>
            <Loader2 className="w-4 h-4 animate-spin text-current" />
            <span>Memproses...</span>
          </>
        ) : (
          <>
            {leftIcon && <span className="inline-flex shrink-0">{leftIcon}</span>}
            {children}
            {rightIcon && <span className="inline-flex shrink-0">{rightIcon}</span>}
          </>
        )}
      </button>
    );
  }
);

Button.displayName = 'Button';

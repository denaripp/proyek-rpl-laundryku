'use client';

import React, { useRef, useEffect } from 'react';
import { cn } from '@/lib/format';

export interface OTPInputProps {
  length?: number;
  value: string;
  onChange: (value: string) => void;
  onComplete?: (value: string) => void;
  error?: string;
  disabled?: boolean;
  autoFocus?: boolean;
  className?: string;
}

export const OTPInput = ({
  length = 6,
  value,
  onChange,
  onComplete,
  error,
  disabled = false,
  autoFocus = true,
  className,
}: OTPInputProps) => {
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

  // Initialize input refs array
  useEffect(() => {
    inputRefs.current = inputRefs.current.slice(0, length);
    if (autoFocus && inputRefs.current[0]) {
      inputRefs.current[0].focus();
    }
  }, [length, autoFocus]);

  const digits = value.split('').slice(0, length);
  while (digits.length < length) {
    digits.push('');
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>, index: number) => {
    const val = e.target.value.replace(/\D/g, '');
    if (!val) return;

    const char = val.slice(-1);
    const newDigits = [...digits];
    newDigits[index] = char;
    const newValue = newDigits.join('');
    onChange(newValue);

    if (newValue.length === length && onComplete) {
      onComplete(newValue);
    }

    // Auto-advance to next input
    if (index < length - 1 && inputRefs.current[index + 1]) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>, index: number) => {
    if (e.key === 'Backspace') {
      if (!digits[index] && index > 0 && inputRefs.current[index - 1]) {
        // If current is already empty, move to previous and clear it
        inputRefs.current[index - 1]?.focus();
        const newDigits = [...digits];
        newDigits[index - 1] = '';
        onChange(newDigits.join(''));
      } else {
        // Clear current
        const newDigits = [...digits];
        newDigits[index] = '';
        onChange(newDigits.join(''));
      }
    } else if (e.key === 'ArrowLeft' && index > 0) {
      e.preventDefault();
      inputRefs.current[index - 1]?.focus();
    } else if (e.key === 'ArrowRight' && index < length - 1) {
      e.preventDefault();
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, length);
    if (pastedData) {
      onChange(pastedData);
      if (pastedData.length === length && onComplete) {
        onComplete(pastedData);
      }
      const nextFocusIndex = Math.min(pastedData.length, length - 1);
      inputRefs.current[nextFocusIndex]?.focus();
    }
  };

  return (
    <div className={cn('w-full space-y-2', className)}>
      <div className="flex items-center justify-between gap-2 sm:gap-3" onPaste={handlePaste}>
        {Array.from({ length }).map((_, index) => {
          const digit = digits[index] || '';
          return (
            <input
              key={index}
              ref={(el) => {
                inputRefs.current[index] = el;
              }}
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              maxLength={1}
              value={digit}
              disabled={disabled}
              onChange={(e) => handleChange(e, index)}
              onKeyDown={(e) => handleKeyDown(e, index)}
              onFocus={(e) => e.target.select()}
              className={cn(
                'w-11 h-13 sm:w-13 sm:h-14 text-center font-heading font-bold text-xl sm:text-2xl tabular-nums rounded-xl border bg-white text-navy-900 transition-all duration-200',
                'focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-teal-500 shadow-sm',
                digit ? 'border-navy-700 bg-sky-50/30' : 'border-slate-200',
                error ? 'border-rose-500 focus:ring-rose-400 bg-rose-50/20' : 'hover:border-slate-300',
                disabled && 'bg-slate-100 text-slate-400 cursor-not-allowed border-slate-200'
              )}
              aria-label={`Digit ${index + 1}`}
            />
          );
        })}
      </div>
      {error && <p className="text-xs font-medium text-rose-600 text-center animate-fadeIn">{error}</p>}
    </div>
  );
};

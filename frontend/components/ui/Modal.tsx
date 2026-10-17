'use client';

import React, { useEffect } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/format';

export interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title?: React.ReactNode;
  description?: React.ReactNode;
  headerExtra?: React.ReactNode;
  headerClassName?: string;
  children: React.ReactNode;
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | 'full';
  showCloseButton?: boolean;
  className?: string;
  containerClassName?: string;
  bodyClassName?: string;
}

export const Modal = ({
  isOpen,
  onClose,
  title,
  description,
  headerExtra,
  headerClassName,
  children,
  maxWidth = 'md',
  showCloseButton = true,
  className,
  containerClassName,
  bodyClassName,
}: ModalProps) => {
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      window.addEventListener('keydown', handleKeyDown);
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const maxWidths = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
    full: 'max-w-[95vw]',
  };

  return (
    <div className={cn("fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto animate-fadeIn", containerClassName)}>
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-navy-900/60 backdrop-blur-sm transition-opacity"
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Modal Dialog Box */}
      <div
        role="dialog"
        aria-modal="true"
        className={cn(
          'relative w-full bg-white z-10 transform transition-all duration-200',
          !className && 'rounded-3xl shadow-2xl border border-slate-100 p-6 sm:p-8 animate-scaleUp',
          maxWidths[maxWidth],
          className
        )}
      >
        <div className={cn("flex items-start justify-between pb-4 border-b border-slate-100", headerClassName)}>
          <div className="flex-1 pr-4">
            {title && (
              <h3 className="font-heading font-bold text-xl text-navy-900 leading-tight">
                {title}
              </h3>
            )}
            {description && (
              <p className="text-sm text-slate-500 mt-1 leading-normal">
                {description}
              </p>
            )}
            {headerExtra && <div className="mt-3">{headerExtra}</div>}
          </div>
          {showCloseButton && (
            <button
              onClick={onClose}
              className="p-2 -mr-2 -mt-2 text-slate-400 hover:text-navy-900 hover:bg-slate-100 rounded-xl transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
              aria-label="Tutup dialog"
            >
              <X className="w-5 h-5" />
            </button>
          )}
        </div>

        <div className={cn("py-4 max-h-[75vh] overflow-y-auto overflow-x-hidden pr-1", bodyClassName)}>
          {children}
        </div>
      </div>
    </div>
  );
};

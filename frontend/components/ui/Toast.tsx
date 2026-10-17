'use client';

import React, { createContext, useContext, useState, useCallback } from 'react';
import { CheckCircle2, AlertCircle, Info, XCircle, X } from 'lucide-react';
import { cn } from '@/lib/format';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastItem {
  id: string;
  type: ToastType;
  title: string;
  message?: string;
  duration?: number;
}

interface ToastContextType {
  toast: (options: { type?: ToastType; title: string; message?: string; duration?: number }) => void;
  success: (title: string, message?: string) => void;
  error: (title: string, message?: string) => void;
  warning: (title: string, message?: string) => void;
  info: (title: string, message?: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export const ToastProvider = ({ children }: { children: React.ReactNode }) => {
  const [toasts, setToasts] = useState<ToastItem[]>([]);

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const addToast = useCallback(
    ({ type = 'info', title, message, duration = 4000 }: { type?: ToastType; title: string; message?: string; duration?: number }) => {
      const id = Math.random().toString(36).substring(2, 9);
      const newToast: ToastItem = { id, type, title, message, duration };
      
      setToasts((prev) => [...prev, newToast]);

      if (duration > 0) {
        setTimeout(() => {
          removeToast(id);
        }, duration);
      }
    },
    [removeToast]
  );

  const success = useCallback((title: string, message?: string) => addToast({ type: 'success', title, message }), [addToast]);
  const error = useCallback((title: string, message?: string) => addToast({ type: 'error', title, message }), [addToast]);
  const warning = useCallback((title: string, message?: string) => addToast({ type: 'warning', title, message }), [addToast]);
  const info = useCallback((title: string, message?: string) => addToast({ type: 'info', title, message }), [addToast]);

  return (
    <ToastContext.Provider value={{ toast: addToast, success, error, warning, info }}>
      {children}
      <div
        className="fixed bottom-4 right-4 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
        aria-live="polite"
      >
        {toasts.map((item) => (
          <ToastCard key={item.id} item={item} onDismiss={() => removeToast(item.id)} />
        ))}
      </div>
    </ToastContext.Provider>
  );
};

const ToastCard = ({ item, onDismiss }: { item: ToastItem; onDismiss: () => void }) => {
  const icons = {
    success: <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />,
    error: <XCircle className="w-5 h-5 text-rose-500 shrink-0" />,
    warning: <AlertCircle className="w-5 h-5 text-amber-500 shrink-0" />,
    info: <Info className="w-5 h-5 text-blue-500 shrink-0" />,
  };

  const borders = {
    success: 'border-emerald-100 bg-white',
    error: 'border-rose-100 bg-white',
    warning: 'border-amber-100 bg-white',
    info: 'border-blue-100 bg-white',
  };

  return (
    <div
      className={cn(
        'pointer-events-auto flex items-start gap-3 p-4 rounded-2xl shadow-lg border transition-all duration-200 animate-slideUp',
        borders[item.type]
      )}
      role="alert"
    >
      <div className="pt-0.5">{icons[item.type]}</div>
      <div className="flex-1 space-y-0.5 pr-2 text-left">
        <p className="text-sm font-semibold text-navy-900 leading-snug">{item.title}</p>
        {item.message && <p className="text-xs text-slate-500 leading-normal">{item.message}</p>}
      </div>
      <button
        onClick={onDismiss}
        className="text-slate-400 hover:text-slate-700 p-1 -mr-1 -mt-1 rounded-lg transition-colors"
        aria-label="Tutup notifikasi"
      >
        <X className="w-4 h-4" />
      </button>
    </div>
  );
};

export const useToast = () => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};

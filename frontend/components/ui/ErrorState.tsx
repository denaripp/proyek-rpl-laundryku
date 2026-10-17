import React from 'react';
import { AlertCircle, RefreshCw } from 'lucide-react';
import { Button } from './Button';
import { cn } from '@/lib/format';

export interface ErrorStateProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  retryText?: string;
  className?: string;
}

export const ErrorState = ({
  title = 'Terjadi Kesalahan',
  message = 'Gagal memuat data. Silakan periksa koneksi internet Anda atau coba lagi.',
  onRetry,
  retryText = 'Coba Lagi',
  className,
}: ErrorStateProps) => {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center text-center p-8 sm:p-12 rounded-2xl bg-rose-50/40 border border-rose-200/80',
        className
      )}
    >
      <div className="w-14 h-14 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mb-4 shadow-sm">
        <AlertCircle className="w-7 h-7" />
      </div>
      <h3 className="font-heading font-bold text-lg text-navy-900 mb-1">
        {title}
      </h3>
      <p className="text-sm text-slate-600 max-w-md mb-6 leading-relaxed">
        {message}
      </p>
      {onRetry && (
        <Button variant="outline" onClick={onRetry} className="border-rose-300 text-rose-700 hover:bg-rose-50">
          <RefreshCw className="w-4 h-4 mr-2" />
          {retryText}
        </Button>
      )}
    </div>
  );
};

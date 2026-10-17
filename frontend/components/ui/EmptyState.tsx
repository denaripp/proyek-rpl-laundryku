import React from 'react';
import { Sparkles, Inbox } from 'lucide-react';
import { Button } from './Button';
import { cn } from '@/lib/format';

export interface EmptyStateProps {
  title: string;
  description: string;
  actionText?: string;
  onAction?: () => void;
  icon?: React.ReactNode;
  illustration?: React.ReactNode;
  className?: string;
}

export const EmptyState = ({
  title,
  description,
  actionText,
  onAction,
  icon,
  illustration,
  className,
}: EmptyStateProps) => {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center text-center p-8 sm:p-12 rounded-2xl bg-sky-50/50 border border-slate-200/80',
        className
      )}
    >
      {illustration ? (
        <div className="mb-6">{illustration}</div>
      ) : (
        <div className="relative mb-5 flex items-center justify-center w-16 h-16 rounded-2xl bg-teal-50 text-teal-600 border border-teal-100 shadow-sm">
          {icon || <Inbox className="w-8 h-8" />}
          <Sparkles className="w-4 h-4 text-amber-400 absolute -top-1 -right-1" />
        </div>
      )}

      <h3 className="font-heading font-bold text-lg text-navy-900 mb-1 max-w-sm">
        {title}
      </h3>
      <p className="text-sm text-slate-500 max-w-md mb-6 leading-relaxed">
        {description}
      </p>

      {actionText && onAction && (
        <Button variant="primary" onClick={onAction}>
          {actionText}
        </Button>
      )}
    </div>
  );
};

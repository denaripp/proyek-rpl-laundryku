import React from 'react';
import { cn } from '@/lib/format';

export interface FormSectionProps {
  title: string;
  description?: string;
  children: React.ReactNode;
  className?: string;
}

export const FormSection: React.FC<FormSectionProps> = ({
  title,
  description,
  children,
  className = '',
}) => {
  return (
    <section className={cn('pt-6 first:pt-0 border-t border-slate-200 first:border-t-0 space-y-4', className)}>
      <div className="space-y-0.5">
        <h2 className="text-sm font-semibold text-slate-900 tracking-tight">{title}</h2>
        {description && <p className="text-xs text-slate-500">{description}</p>}
      </div>
      <div className="space-y-3.5">{children}</div>
    </section>
  );
};

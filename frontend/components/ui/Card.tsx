import React from 'react';
import { cn } from '@/lib/format';

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'default' | 'bento' | 'flat' | 'navy' | 'sky';
  interactive?: boolean;
}

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
  ({ className, variant = 'default', interactive = false, children, ...props }, ref) => {
    const variants = {
      default: 'bg-white border border-slate-200/80 shadow-sm text-slate-900',
      bento: 'bg-white border border-slate-200/80 shadow-md hover:shadow-lg transition-all duration-300 relative overflow-hidden',
      flat: 'bg-white border border-slate-200 text-slate-900',
      navy: 'bg-navy-900 border border-navy-800 text-white shadow-md',
      sky: 'bg-sky-50 border border-slate-200 text-slate-900',
    };

    return (
      <div
        ref={ref}
        className={cn(
          'rounded-2xl p-6 transition-all duration-200',
          variants[variant],
          interactive && 'cursor-pointer hover:-translate-y-1 hover:shadow-md hover:border-slate-300',
          className
        )}
        {...props}
      >
        {children}
      </div>
    );
  }
);

Card.displayName = 'Card';

export const CardHeader = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
  <div className={cn('flex flex-col space-y-1.5 pb-4', className)} {...props} />
);

export const CardTitle = ({ className, ...props }: React.HTMLAttributes<HTMLHeadingElement>) => (
  <h3 className={cn('font-heading font-bold text-lg leading-tight tracking-tight text-navy-900', className)} {...props} />
);

export const CardDescription = ({ className, ...props }: React.HTMLAttributes<HTMLParagraphElement>) => (
  <p className={cn('text-sm text-slate-500', className)} {...props} />
);

export const CardContent = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
  <div className={cn('pt-0', className)} {...props} />
);

export const CardFooter = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
  <div className={cn('flex items-center pt-4 border-t border-slate-100 mt-4', className)} {...props} />
);

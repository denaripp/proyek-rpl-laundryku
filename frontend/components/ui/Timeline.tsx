import React from 'react';
import { Check } from 'lucide-react';
import { cn } from '@/lib/format';

export interface TimelineEvent {
  id: string;
  title: string;
  description?: string;
  time?: string;
  status: 'completed' | 'current' | 'upcoming';
  icon?: React.ReactNode;
}

export interface TimelineProps {
  events: TimelineEvent[];
  className?: string;
}

export const Timeline = ({ events, className }: TimelineProps) => {
  return (
    <div className={cn('relative pl-6 space-y-6', className)}>
      {/* Vertical line */}
      <div className="absolute left-[11px] top-3 bottom-3 w-0.5 bg-slate-200" aria-hidden="true" />

      {events.map((event, index) => {
        const isCompleted = event.status === 'completed';
        const isCurrent = event.status === 'current';

        return (
          <div key={event.id || index} className="relative flex items-start gap-4 group">
            {/* Dot / Indicator */}
            <div
              className={cn(
                'absolute -left-6 flex items-center justify-center w-6 h-6 rounded-full border-2 transition-all duration-200 z-10',
                isCompleted && 'bg-teal-500 border-teal-500 text-navy-900',
                isCurrent && 'bg-white border-teal-500 ring-4 ring-teal-100',
                event.status === 'upcoming' && 'bg-white border-slate-300'
              )}
            >
              {isCompleted ? (
                <Check className="w-3.5 h-3.5 stroke-[3]" />
              ) : isCurrent ? (
                <div className="w-2 h-2 rounded-full bg-teal-500 animate-pulse" />
              ) : (
                <div className="w-1.5 h-1.5 rounded-full bg-slate-300" />
              )}
            </div>

            {/* Content */}
            <div className="flex-1 -mt-0.5">
              <div className="flex items-center justify-between gap-2">
                <h4
                  className={cn(
                    'font-heading font-semibold text-sm',
                    isCurrent ? 'text-teal-700' : 'text-navy-900'
                  )}
                >
                  {event.title}
                </h4>
                {event.time && (
                  <span className="text-xs text-slate-400 tabular-nums">{event.time}</span>
                )}
              </div>
              {event.description && (
                <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                  {event.description}
                </p>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
};

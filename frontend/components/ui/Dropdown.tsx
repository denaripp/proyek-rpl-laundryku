'use client';

import React, { useState, useRef, useEffect } from 'react';
import { cn } from '@/lib/format';

export interface DropdownItem {
  id: string;
  label: React.ReactNode;
  icon?: React.ComponentType<{ className?: string }>;
  onClick?: () => void;
  danger?: boolean;
  disabled?: boolean;
  divider?: boolean;
}

export interface DropdownProps {
  trigger: React.ReactNode;
  items: DropdownItem[];
  align?: 'left' | 'right';
  className?: string;
}

export const Dropdown = ({
  trigger,
  items,
  align = 'right',
  className,
}: DropdownProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  return (
    <div className={cn('relative inline-block text-left', className)} ref={dropdownRef}>
      <div onClick={() => setIsOpen(!isOpen)} className="cursor-pointer">
        {trigger}
      </div>

      {isOpen && (
        <div
          className={cn(
            'absolute z-50 mt-2 w-56 rounded-2xl bg-white shadow-xl border border-slate-100 py-1.5 focus:outline-none animate-scaleUp',
            align === 'right' ? 'right-0' : 'left-0'
          )}
          role="menu"
        >
          {items.map((item, idx) => {
            if (item.divider) {
              return <div key={`div-${idx}`} className="h-px bg-slate-100 my-1" />;
            }

            const Icon = item.icon;
            return (
              <button
                key={item.id || idx}
                role="menuitem"
                disabled={item.disabled}
                onClick={() => {
                  item.onClick?.();
                  setIsOpen(false);
                }}
                className={cn(
                  'flex items-center w-full px-4 py-2.5 text-sm font-medium transition-colors min-h-[44px] text-left',
                  item.danger
                    ? 'text-rose-600 hover:bg-rose-50'
                    : 'text-slate-700 hover:bg-sky-50 hover:text-navy-900',
                  item.disabled && 'opacity-50 cursor-not-allowed'
                )}
              >
                {Icon && <Icon className={cn('w-4 h-4 mr-3 shrink-0', item.danger ? 'text-rose-500' : 'text-slate-400')} />}
                <span className="truncate">{item.label}</span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
};

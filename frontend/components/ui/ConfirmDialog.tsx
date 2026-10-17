'use client';

import React from 'react';
import { AlertTriangle, Info } from 'lucide-react';
import { Modal } from './Modal';
import { Button } from './Button';
import { cn } from '@/lib/format';

export interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  description: string;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'warning' | 'primary';
  isLoading?: boolean;
}

export const ConfirmDialog = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  confirmText = 'Konfirmasi',
  cancelText = 'Batal',
  variant = 'danger',
  isLoading = false,
}: ConfirmDialogProps) => {
  const iconConfig = {
    danger: {
      icon: AlertTriangle,
      bg: 'bg-rose-50 text-rose-600 border border-rose-100',
    },
    warning: {
      icon: AlertTriangle,
      bg: 'bg-amber-50 text-amber-600 border border-amber-100',
    },
    primary: {
      icon: Info,
      bg: 'bg-teal-50 text-teal-600 border border-teal-100',
    },
  };

  const Icon = iconConfig[variant].icon;

  return (
    <Modal isOpen={isOpen} onClose={onClose} maxWidth="sm" showCloseButton={!isLoading}>
      <div className="flex flex-col items-center text-center space-y-4 pt-2">
        <div className={cn('w-14 h-14 rounded-2xl flex items-center justify-center', iconConfig[variant].bg)}>
          <Icon className="w-7 h-7" />
        </div>
        <div className="space-y-1.5">
          <h4 className="font-heading font-bold text-lg text-navy-900">{title}</h4>
          <p className="text-sm text-slate-500 max-w-xs">{description}</p>
        </div>
        <div className="flex items-center gap-3 w-full pt-4">
          <Button
            variant="secondary"
            className="flex-1"
            onClick={onClose}
            disabled={isLoading}
          >
            {cancelText}
          </Button>
          <Button
            variant={variant === 'danger' ? 'danger' : 'primary'}
            className="flex-1"
            onClick={onConfirm}
            isLoading={isLoading}
          >
            {confirmText}
          </Button>
        </div>
      </div>
    </Modal>
  );
};

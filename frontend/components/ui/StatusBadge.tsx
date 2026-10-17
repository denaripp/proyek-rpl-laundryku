import React from 'react';
import { CheckCircle2, Clock, XCircle, Info, HelpCircle } from 'lucide-react';
import { cn } from '@/lib/format';

export type StatusType = 'success' | 'warning' | 'danger' | 'error' | 'info' | 'pending' | 'neutral';

export interface StatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  status?: StatusType | string;
  label?: string;
  detail?: string;
  showIcon?: boolean;
}

export function getStatusConfig(status: string) {
  const s = status.toLowerCase();
  if (['success', 'active', 'verified', 'completed', 'approved', 'ready'].includes(s)) {
    return {
      variant: 'bg-emerald-50 text-emerald-800 border-emerald-200',
      icon: CheckCircle2,
      iconColor: 'text-emerald-600',
      defaultLabel: s === 'active' ? 'Aktif' : s === 'verified' ? 'Terverifikasi' : s === 'completed' ? 'Selesai' : 'Sukses',
    };
  }
  if (['warning', 'pending', 'pending_verification', 'menunggu', 'waiting'].includes(s)) {
    return {
      variant: 'bg-amber-50 text-amber-800 border-amber-200',
      icon: Clock,
      iconColor: 'text-amber-600',
      defaultLabel: s === 'pending_verification' ? 'Menunggu Verifikasi' : 'Menunggu',
    };
  }
  if (['danger', 'error', 'rejected', 'cancelled', 'batal', 'ditolak', 'gagal'].includes(s)) {
    return {
      variant: 'bg-rose-50 text-rose-800 border-rose-200',
      icon: XCircle,
      iconColor: 'text-rose-600',
      defaultLabel: s === 'rejected' ? 'Ditolak' : s === 'cancelled' ? 'Dibatalkan' : 'Gagal',
    };
  }
  if (['info', 'in_progress', 'processing', 'diproses', 'dikirim'].includes(s)) {
    return {
      variant: 'bg-blue-50 text-blue-800 border-blue-200',
      icon: Info,
      iconColor: 'text-blue-600',
      defaultLabel: s === 'in_progress' ? 'Sedang Berjalan' : s === 'processing' ? 'Diproses' : 'Informasi',
    };
  }
  return {
    variant: 'bg-slate-50 text-slate-700 border-slate-200',
    icon: HelpCircle,
    iconColor: 'text-slate-500',
    defaultLabel: status,
  };
}

export const StatusBadge = ({
  status = 'info',
  label,
  detail,
  showIcon = true,
  className,
  ...props
}: StatusBadgeProps) => {
  const config = getStatusConfig(status);
  const Icon = config.icon;
  const displayLabel = label || config.defaultLabel;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-colors',
        config.variant,
        className
      )}
      {...props}
    >
      {showIcon && <Icon className={cn('w-3.5 h-3.5 shrink-0', config.iconColor)} />}
      <span>{displayLabel}</span>
      {detail && <span className="text-[10px] opacity-75 ml-0.5">({detail})</span>}
    </span>
  );
};

export default StatusBadge;

/**
 * LaundryKu Formatting and Localization Helpers
 */

/**
 * Format numerical value into standard Indonesian Rupiah currency format.
 * Example: 18000 -> "Rp18.000"
 */
export function formatRupiah(amount: number | string | null | undefined): string {
  if (amount === null || amount === undefined || isNaN(Number(amount))) {
    return 'Rp0';
  }
  const numeric = Math.round(Number(amount));
  return `Rp${numeric.toLocaleString('id-ID')}`;
}

export const formatCurrency = formatRupiah;

/**
 * Format date string into Indonesian standard localized string (Asia/Jakarta).
 * Example: "2026-09-20T11:00:00Z" -> "20 September 2026, 18:00 WIB"
 */
export function formatTanggal(
  dateInput: string | Date | null | undefined,
  includeTime: boolean = false
): string {
  if (!dateInput) return '-';

  try {
    const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
    if (isNaN(date.getTime())) return '-';

    const options: Intl.DateTimeFormatOptions = {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      ...(includeTime && {
        hour: '2-digit',
        minute: '2-digit',
      }),
    };

    const formatted = new Intl.DateTimeFormat('id-ID', options).format(date);
    return includeTime ? `${formatted} WIB` : formatted;
  } catch {
    return '-';
  }
}

export function formatTanggalWaktu(
  dateInput: string | Date | null | undefined
): string {
  return formatTanggal(dateInput, true);
}

/**
 * Format date alias
 */
export function formatDate(
  dateInput: string | Date | null | undefined,
  _formatStr?: string
): string {
  return formatTanggal(dateInput, true);
}

/**
 * Format daily opening hours for store display.
 * Handles both string ("07:30 - 21:00") and object format ({ open, close, is_closed }).
 * Returns "open - close", or "Tutup" if is_closed is true.
 * Safely handles null or undefined values.
 */
export type OpeningHoursSchedule =
  | string
  | {
      open?: string | null;
      close?: string | null;
      is_closed?: boolean | null;
    }
  | null
  | undefined;

export function formatOpeningHours(hours: OpeningHoursSchedule): string {
  if (!hours) return 'Tutup';
  if (typeof hours === 'string') return hours;
  if (typeof hours === 'object') {
    if (hours.is_closed) return 'Tutup';
    const open = hours.open?.trim();
    const close = hours.close?.trim();
    if (open && close) {
      return `${open} - ${close}`;
    }
    if (open) {
      return open;
    }
    return 'Tutup';
  }
  return 'Tutup';
}

/**
 * Class name combiner helper
 */
export function cn(...classes: (string | undefined | null | false)[]): string {
  return classes.filter(Boolean).join(' ');
}

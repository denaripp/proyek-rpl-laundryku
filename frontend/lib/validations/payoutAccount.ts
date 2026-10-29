import { z } from 'zod';

export const BANK_OPTIONS = [
  'BCA',
  'BNI',
  'BRI',
  'MANDIRI',
  'PERMATA',
  'BSI',
  'CIMB',
  'DANAMON',
  'BTN',
  'DANA',
  'LAINNYA',
] as const;

export type BankCode = (typeof BANK_OPTIONS)[number];

export const payoutAccountSchema = z.object({
  bank_code: z.enum(BANK_OPTIONS, {
    message: 'Bank harus dipilih dari daftar yang tersedia.',
  }),
  account_number: z
    .string()
    .trim()
    .min(1, 'Nomor rekening wajib diisi.')
    .regex(/^\d{8,20}$/, 'Nomor rekening harus berupa angka 8–20 digit.'),
  account_holder_name: z
    .string()
    .trim()
    .min(1, 'Nama pemilik rekening wajib diisi.')
    .max(100, 'Nama pemilik rekening maksimal 100 karakter.')
    .transform((val) => val.toUpperCase()),
});

export type PayoutAccountInput = z.infer<typeof payoutAccountSchema>;

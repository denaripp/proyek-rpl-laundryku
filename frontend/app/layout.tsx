import type { Metadata } from 'next';
import { Plus_Jakarta_Sans, Inter } from 'next/font/google';
import './globals.css';
import { ToastProvider } from '@/components/ui/Toast';
import { AuthProvider } from '@/lib/AuthContext';

const plusJakartaSans = Plus_Jakarta_Sans({
  variable: '--font-heading',
  subsets: ['latin'],
  weight: ['500', '600', '700', '800'],
  display: 'swap',
});

const inter = Inter({
  variable: '--font-sans',
  subsets: ['latin'],
  weight: ['400', '500', '600', '700'],
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'LaundryKu — Marketplace Jasa Laundry Bersih & Terpercaya',
  description: 'Platform marketplace laundry B2C modern, transparan, dan terpercaya. Jemput, cuci, timbang akurat, antar tepat waktu.',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html
      lang="id"
      className={`${plusJakartaSans.variable} ${inter.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col font-sans bg-slate-50 text-slate-900 selection:bg-teal-500 selection:text-white">
        <ToastProvider>
          <AuthProvider>
            {children}
          </AuthProvider>
        </ToastProvider>
      </body>
    </html>
  );
}

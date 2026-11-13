import type { Metadata } from 'next';
import './globals.css';
import { ToastProvider } from '@/components/ui/Toast';
import { AuthProvider } from '@/lib/AuthContext';

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
      className="h-full antialiased"
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

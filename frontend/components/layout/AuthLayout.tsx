'use client';

import React from 'react';
import Link from 'next/link';
import { Sparkles, ArrowLeft } from 'lucide-react';

export interface AuthLayoutProps {
  children: React.ReactNode;
  title?: string;
  subtitle?: string;
}

export const AuthLayout = ({ children, title, subtitle }: AuthLayoutProps) => {
  return (
    <div className="min-h-screen relative flex flex-col justify-center items-center p-4 sm:p-6 lg:p-8 bg-sky-50 overflow-hidden font-sans">
      {/* Background Decorative Subtle SVG Motif */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
        {/* Soft background gradient overlay */}
        <div className="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-teal-200/30 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-blue-200/30 blur-3xl" />
        
        {/* Subtle SVG bubbles */}
        <svg
          className="absolute inset-0 w-full h-full opacity-[0.03] text-navy-900"
          xmlns="http://www.w3.org/2000/svg"
        >
          <pattern id="bubble-pattern" width="60" height="60" patternUnits="userSpaceOnUse">
            <circle cx="30" cy="30" r="12" fill="none" stroke="currentColor" strokeWidth="2" />
            <circle cx="10" cy="10" r="5" fill="none" stroke="currentColor" strokeWidth="1.5" />
            <circle cx="50" cy="50" r="7" fill="none" stroke="currentColor" strokeWidth="1.5" />
          </pattern>
          <rect width="100%" height="100%" fill="url(#bubble-pattern)" />
        </svg>
      </div>

      {/* Back to Home Link */}
      <div className="w-full max-w-md mb-6 z-10">
        <Link
          href="/"
          className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-navy-900 transition-colors p-1"
        >
          <ArrowLeft className="w-4 h-4" />
          Kembali ke Beranda
        </Link>
      </div>

      {/* Main Auth Card */}
      <div className="w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-200/80 z-10 transition-all">
        {/* Logo & Brand */}
        <div className="flex flex-col items-center text-center mb-8">
          <Link href="/" className="inline-flex items-center gap-2 mb-3 group">
            <div className="w-12 h-12 rounded-2xl bg-gradient-to-tr from-navy-900 via-navy-800 to-teal-500 flex items-center justify-center shadow-md group-hover:scale-105 transition-transform">
              <Sparkles className="w-6 h-6 text-teal-400" />
            </div>
          </Link>
          <h2 className="font-heading font-extrabold text-2xl tracking-tight text-navy-900">
            Laundry<span className="text-teal-500">Ku</span>
          </h2>
          {title && <h3 className="font-heading font-bold text-lg text-slate-800 mt-2">{title}</h3>}
          {subtitle && <p className="text-sm text-slate-500 mt-1 max-w-xs">{subtitle}</p>}
        </div>

        {/* Content Slot */}
        {children}
      </div>

      {/* Security Footer Note */}
      <div className="mt-8 text-center text-xs text-slate-500 z-10">
        <p>Dilindungi dengan enkripsi keamanan end-to-end LaundryKu.</p>
      </div>
    </div>
  );
};

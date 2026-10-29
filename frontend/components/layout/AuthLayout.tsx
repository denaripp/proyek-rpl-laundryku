'use client';

import React from 'react';
import Link from 'next/link';
import { ArrowLeft, Truck, Scale, Wallet } from 'lucide-react';

export interface AuthLayoutProps {
  children: React.ReactNode;
  title?: string;
  subtitle?: string;
}

export const AuthLayout = ({ children, title, subtitle }: AuthLayoutProps) => {
  return (
    <div className="min-h-screen w-full flex flex-col lg:flex-row bg-white font-sans">
      {/* 1. Left Panel (Desktop Split-Screen Brand Hero) */}
      <div className="hidden lg:flex lg:w-1/2 bg-navy-900 text-white relative overflow-hidden flex-col justify-between p-12 xl:p-16 select-none">
        {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
        <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
          {/* Bubble 1 (Top Right) */}
          <div className="absolute top-12 right-12 w-28 h-28 rounded-full border border-sky-300/30 bg-sky-400/10 backdrop-blur-[1px] shadow-inner animate-gentle-float">
            <div className="w-3 h-3 rounded-full bg-white/60 ml-4 mt-4" />
          </div>

          {/* Bubble 2 (Center Left) */}
          <div className="absolute top-1/2 left-8 w-20 h-20 rounded-full border border-sky-300/25 bg-sky-300/10 animate-gentle-float-slow">
            <div className="w-2 h-2 rounded-full bg-white/50 ml-3 mt-3" />
          </div>

          {/* Bubble 3 (Bottom Center) */}
          <div className="absolute bottom-20 right-24 w-14 h-14 rounded-full border border-sky-300/20 bg-sky-200/10 animate-gentle-float">
            <div className="w-1.5 h-1.5 rounded-full bg-white/50 ml-2 mt-2" />
          </div>
        </div>

        {/* Top: Logo & Wordmark */}
        <div className="relative z-10">
          <Link href="/" className="inline-flex items-center gap-2.5 group">
            <div className="w-9 h-9 rounded-xl bg-navy-700 flex items-center justify-center text-white font-bold text-sm tracking-wider shadow-xs border border-white/20">
              LK
            </div>
            <span className="font-extrabold text-xl tracking-tight text-white">
              Laundry<span className="text-navy-200">Ku</span>
            </span>
          </Link>
        </div>

        {/* Center: Brand Messaging & Value Props */}
        <div className="relative z-10 space-y-6 max-w-lg my-auto py-8">
          <div className="space-y-3">
            <h2 className="text-3xl xl:text-4xl font-extrabold text-white tracking-tight leading-[1.2]">
              Cucian bersih tanpa repot.
            </h2>
            <p className="text-sm xl:text-base text-navy-200 leading-relaxed">
              Pesan laundry kiloan, satuan, atau dry clean dari outlet terdekat. Kurir siap menjemput dan mengantar cucian Anda sampai rapi.
            </p>
          </div>

          {/* 3 Value Props Matching Homepage */}
          <div className="space-y-3 pt-2">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-navy-800 border border-navy-700/80 flex items-center justify-center text-navy-200 shrink-0 shadow-2xs">
                <Truck className="w-4 h-4" strokeWidth={1.75} />
              </div>
              <span className="text-sm font-semibold text-white">Antar jemput</span>
            </div>

            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-navy-800 border border-navy-700/80 flex items-center justify-center text-navy-200 shrink-0 shadow-2xs">
                <Scale className="w-4 h-4" strokeWidth={1.75} />
              </div>
              <span className="text-sm font-semibold text-white">Harga sesuai timbangan</span>
            </div>

            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-navy-800 border border-navy-700/80 flex items-center justify-center text-navy-200 shrink-0 shadow-2xs">
                <Wallet className="w-4 h-4" strokeWidth={1.75} />
              </div>
              <span className="text-sm font-semibold text-white">Bayar QRIS atau tunai</span>
            </div>
          </div>
        </div>

        {/* Bottom: Wave SVG Accent & Copyright */}
        <div className="relative z-10 pt-4">
          <div className="text-xs text-navy-200/60 font-medium">
            &copy; {new Date().getFullYear()} LaundryKu. Hak Cipta Dilindungi.
          </div>
        </div>
      </div>

      {/* 2. Right Panel (Form Container) */}
      <div className="w-full lg:w-1/2 flex flex-col justify-center items-center p-4 sm:p-8 lg:p-12 xl:p-16 bg-slate-50 min-h-screen relative overflow-y-auto">
        {/* Back to Home Link */}
        <div className="w-full max-w-md mb-4 z-10">
          <Link
            href="/"
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-navy-700 transition-colors py-1"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>Kembali ke beranda</span>
          </Link>
        </div>

        {/* Main Auth Form Card */}
        <div className="w-full max-w-md bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/90 z-10 transition-all">
          {/* Mobile-only Logo */}
          <div className="lg:hidden flex flex-col items-center text-center mb-6">
            <Link href="/" className="inline-flex items-center gap-2 mb-2 group">
              <div className="w-9 h-9 rounded-xl bg-navy-700 flex items-center justify-center text-white font-bold text-sm tracking-wider shadow-xs">
                LK
              </div>
              <span className="font-extrabold text-xl tracking-tight text-navy-900">
                Laundry<span className="text-navy-700">Ku</span>
              </span>
            </Link>
          </div>

          {/* Form Header (Title & Subtitle) */}
          {(title || subtitle) && (
            <div className="text-left mb-6 space-y-1">
              {title && <h2 className="font-extrabold text-2xl tracking-tight text-navy-900">{title}</h2>}
              {subtitle && <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">{subtitle}</p>}
            </div>
          )}

          {/* Form Content Slot */}
          {children}
        </div>
      </div>
    </div>
  );
};

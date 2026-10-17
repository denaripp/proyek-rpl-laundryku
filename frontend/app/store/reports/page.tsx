'use client';

import React from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui';
import { getStoredUser, logout } from '@/lib/auth';
import { BarChart3, LayoutDashboard } from 'lucide-react';

export default function StoreReportsPage() {
  const user = getStoredUser();

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={user?.name || 'Admin Toko'}
      userEmail={user?.email || user?.phone || 'admin@toko.id'}
      onLogout={logout}
    >
      <div className="space-y-6">
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">
            Laporan & Rekap Operasional
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Unduh rekapitulasi penjualan, laporan performa kurir, dan statistik bulanan.
          </p>
        </div>

        <div className="border border-slate-200 rounded-lg p-12 bg-white text-center space-y-3">
          <div className="w-12 h-12 rounded-full bg-slate-100 text-[#1A3A5C] flex items-center justify-center mx-auto">
            <BarChart3 className="w-6 h-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Segera Hadir</h2>
          <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Modul ekspor spreadsheet dan analitik berkala sedang dalam tahap penyempurnaan. Anda dapat melihat ringkasan omset harian langsung melalui halaman Ringkasan.
          </p>
          <div className="pt-2">
            <Link href="/store">
              <Button variant="primary" size="sm" className="bg-[#1A3A5C] text-xs inline-flex items-center gap-1.5">
                <LayoutDashboard className="w-3.5 h-3.5" />
                <span>Kembali ke Dashboard</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

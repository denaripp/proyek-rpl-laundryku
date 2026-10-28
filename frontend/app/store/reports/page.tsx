'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { BarChart3, LayoutDashboard } from 'lucide-react';

export default function StoreReportsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);

  useEffect(() => {
    setCurrentUser(getStoredUser('mitra'));
  }, []);

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
    >
      <div className="space-y-6">
        <div>
          <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
            Laporan & rekap operasional
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Unduh rekapitulasi penjualan, laporan performa kurir, dan statistik bulanan.
          </p>
        </div>

        <div className="border border-slate-200/80 rounded-2xl p-10 sm:p-12 bg-white text-center space-y-3.5 shadow-2xs max-w-2xl mx-auto">
          <div className="w-12 h-12 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center mx-auto">
            <BarChart3 className="w-6 h-6 text-navy-700" strokeWidth={1.75} />
          </div>
          <h2 className="text-base font-bold text-slate-900">Segera hadir</h2>
          <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Modul ekspor spreadsheet dan analitik berkala sedang dalam tahap penyempurnaan. Anda dapat melihat ringkasan omset harian langsung melalui halaman ringkasan.
          </p>
          <div className="pt-2">
            <Link href="/store">
              <Button
                variant="primary"
                size="sm"
                className="bg-navy-900 hover:bg-navy-800 text-white text-xs inline-flex items-center gap-1.5 rounded-xl shadow-2xs min-h-[38px] px-4 font-semibold"
              >
                <LayoutDashboard className="w-3.5 h-3.5 text-sky-300" strokeWidth={1.75} />
                <span>Kembali ke dashboard</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

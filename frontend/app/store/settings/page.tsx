'use client';

import React from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui';
import { getStoredUser, logout } from '@/lib/auth';
import { Settings, User } from 'lucide-react';

export default function StoreSettingsPage() {
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
            Pengaturan Toko
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Konfigurasi jam operasional, radius layanan, dan profil gerai laundry.
          </p>
        </div>

        <div className="border border-slate-200 rounded-lg p-12 bg-white text-center space-y-3">
          <div className="w-12 h-12 rounded-full bg-slate-100 text-[#1A3A5C] flex items-center justify-center mx-auto">
            <Settings className="w-6 h-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Segera Hadir</h2>
          <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Pengaturan lanjutan informasi gerai dan jam operasional dinamis sedang dalam pengembangan. Untuk memperbarui profil akun pemilik, silakan kunjungi menu Profil Akun.
          </p>
          <div className="pt-2">
            <Link href="/profile">
              <Button variant="primary" size="sm" className="bg-[#1A3A5C] text-xs inline-flex items-center gap-1.5">
                <User className="w-3.5 h-3.5" />
                <span>Buka Profil Akun</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { Tag, Sparkles } from 'lucide-react';

export default function StorePricingPage() {
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
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">
            Pengaturan Harga & Diskon
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Atur aturan tarif khusus, promo musiman, dan diskon kuantitas gerai Anda.
          </p>
        </div>

        <div className="border border-slate-200 rounded-lg p-12 bg-white text-center space-y-3">
          <div className="w-12 h-12 rounded-full bg-slate-100 text-[#1A3A5C] flex items-center justify-center mx-auto">
            <Tag className="w-6 h-6" />
          </div>
          <h2 className="text-base font-bold text-slate-900">Segera Hadir</h2>
          <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Modul pengelolaan promosi dan voucher harga dinamis sedang dalam pengembangan. Untuk mengubah tarif dasar per layanan, gunakan menu Katalog Layanan.
          </p>
          <div className="pt-2">
            <Link href="/store/services">
              <Button variant="primary" size="sm" className="bg-[#1A3A5C] text-xs inline-flex items-center gap-1.5">
                <Sparkles className="w-3.5 h-3.5" />
                <span>Buka Katalog Layanan</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

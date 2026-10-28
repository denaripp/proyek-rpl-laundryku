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
          <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
            Pengaturan harga & diskon
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Atur aturan tarif khusus, promo musiman, dan diskon kuantitas gerai Anda.
          </p>
        </div>

        <div className="border border-slate-200/80 rounded-2xl p-10 sm:p-12 bg-white text-center space-y-3.5 shadow-2xs max-w-2xl mx-auto">
          <div className="w-12 h-12 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center mx-auto">
            <Tag className="w-6 h-6 text-navy-700" strokeWidth={1.75} />
          </div>
          <h2 className="text-base font-bold text-slate-900">Segera hadir</h2>
          <p className="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">
            Modul pengelolaan promosi dan voucher harga dinamis sedang dalam pengembangan. Untuk mengubah tarif dasar per layanan, gunakan menu katalog layanan.
          </p>
          <div className="pt-2">
            <Link href="/store/services">
              <Button
                variant="primary"
                size="sm"
                className="bg-navy-900 hover:bg-navy-800 text-white text-xs inline-flex items-center gap-1.5 rounded-xl shadow-2xs min-h-[38px] px-4 font-semibold"
              >
                <Sparkles className="w-3.5 h-3.5 text-sky-300" strokeWidth={1.75} />
                <span>Buka katalog layanan</span>
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

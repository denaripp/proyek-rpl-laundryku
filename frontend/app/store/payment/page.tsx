'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Badge } from '@/components/ui';
import { getStoredUser, getActiveStoreId, AuthUser } from '@/lib/auth';
import {
  getStorePaymentMethods,
  updateStorePaymentMethods,
} from '@/lib/store';
import {
  CreditCard,
  QrCode,
  Truck,
  CheckCircle2,
  AlertCircle,
  Building2,
  ShieldCheck,
  Info,
} from 'lucide-react';
import { cn } from '@/lib/format';

const ALL_VA_BANKS = ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'];

export default function StorePaymentSettingsPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [activeStoreId, setActiveStoreIdState] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Payment method toggles
  const [codEnabled, setCodEnabled] = useState(true);
  const [qrisEnabled, setQrisEnabled] = useState(false);
  const [vaEnabled, setVaEnabled] = useState(false);
  const [selectedBanks, setSelectedBanks] = useState<string[]>(['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA']);
  const [storeName, setStoreName] = useState<string>('Gerai Laundry');

  // Load store payment methods
  const loadData = useCallback(async (storeId?: string) => {
    setLoading(true);
    setErrorMsg(null);
    try {
      const methodsData = await getStorePaymentMethods(storeId || undefined);

      setCodEnabled(Boolean(methodsData.cod_enabled));
      setQrisEnabled(Boolean(methodsData.qris_enabled));
      setVaEnabled(Boolean(methodsData.va_enabled));
      if (Array.isArray(methodsData.va_banks) && methodsData.va_banks.length > 0) {
        setSelectedBanks(methodsData.va_banks);
      }
      if (methodsData.store_name) {
        setStoreName(methodsData.store_name);
      }
    } catch (err: unknown) {
      const e = err as { responseData?: { message?: string }; message?: string };
      setErrorMsg(e.responseData?.message || e.message || 'Gagal memuat pengaturan metode pembayaran.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const user = getStoredUser('mitra');
    setCurrentUser(user);
    const storeId = getActiveStoreId(user);
    setActiveStoreIdState(storeId);
    loadData(storeId || undefined);
  }, [loadData]);

  // Bank toggle helper
  const handleBankToggle = (bank: string) => {
    if (selectedBanks.includes(bank)) {
      if (selectedBanks.length === 1 && vaEnabled) {
        setErrorMsg('Minimal satu bank harus dipilih jika Virtual Account diaktifkan.');
        return;
      }
      setSelectedBanks(selectedBanks.filter((b) => b !== bank));
    } else {
      setSelectedBanks([...selectedBanks, bank]);
    }
  };

  // Submit update payment methods
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSuccessMsg(null);
    setErrorMsg(null);

    // Frontend validation: minimal satu aktif
    if (!codEnabled && !qrisEnabled && !vaEnabled) {
      setErrorMsg('Minimal satu metode pembayaran harus aktif.');
      return;
    }

    if (vaEnabled && selectedBanks.length === 0) {
      setErrorMsg('Pilih minimal satu bank untuk Virtual Account.');
      return;
    }

    setSaving(true);
    try {
      const res = await updateStorePaymentMethods({
        store_id: activeStoreId || undefined,
        cod_enabled: codEnabled,
        qris_enabled: qrisEnabled,
        va_enabled: vaEnabled,
        va_banks: selectedBanks,
      });

      setCodEnabled(Boolean(res.cod_enabled));
      setQrisEnabled(Boolean(res.qris_enabled));
      setVaEnabled(Boolean(res.va_enabled));
      if (Array.isArray(res.va_banks)) {
        setSelectedBanks(res.va_banks);
      }
      setSuccessMsg('Pengaturan metode pembayaran berhasil disimpan.');
    } catch (err: unknown) {
      const e = err as { responseData?: { message?: string }; message?: string };
      setErrorMsg(e.responseData?.message || e.message || 'Gagal menyimpan pengaturan metode pembayaran.');
    } finally {
      setSaving(false);
    }
  };

  const activeCount = (codEnabled ? 1 : 0) + (qrisEnabled ? 1 : 0) + (vaEnabled ? 1 : 0);

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
    >
      <div className="max-w-4xl mx-auto space-y-6">
        {/* Page Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
          <div>
            <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
              Metode pembayaran
            </h1>
            <p className="text-xs sm:text-sm text-slate-500 mt-1">
              Atur kanal pembayaran yang dapat dipilih oleh pelanggan saat checkout di gerai{' '}
              <strong className="text-slate-800">{storeName}</strong>.
            </p>
          </div>
          <span className="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-200 self-start sm:self-auto font-mono tabular-nums">
            {activeCount} metode aktif
          </span>
        </div>

        {/* Feedback Messages */}
        {successMsg && (
          <div className="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-2.5 text-emerald-900 text-xs font-semibold shadow-2xs">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" strokeWidth={2} />
            <span>{successMsg}</span>
          </div>
        )}
        {errorMsg && (
          <div className="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2.5 text-rose-900 text-xs font-semibold shadow-2xs">
            <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" strokeWidth={2} />
            <span>{errorMsg}</span>
          </div>
        )}

        {loading ? (
          <div className="p-12 text-center text-slate-500 text-xs bg-white rounded-2xl border border-slate-200/80 shadow-2xs">
            Memuat pengaturan pembayaran gerai...
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Payment Method 1: COD */}
            <div className={cn(
              'bg-white border rounded-2xl p-5 transition-all shadow-2xs',
              codEnabled ? 'border-navy-900/60 ring-1 ring-navy-900/10' : 'border-slate-200/80'
            )}>
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3.5">
                  <div className={cn(
                    'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border',
                    codEnabled ? 'bg-navy-50 text-navy-800 border-navy-100' : 'bg-slate-100 text-slate-400 border-slate-200'
                  )}>
                    <Truck className="w-5 h-5" strokeWidth={1.75} />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h2 className="font-semibold text-sm text-slate-900">
                        Bayar di tempat (COD)
                      </h2>
                      <span className={cn(
                        'text-[10px] font-semibold px-2 py-0.5 rounded-md border',
                        codEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                      )}>
                        {codEnabled ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Pelanggan membayar secara tunai langsung ke kurir saat pesanan cucian bersih diantarkan.
                      Pesanan dapat langsung diproses setelah pelanggan memilih metode ini.
                    </p>
                  </div>
                </div>

                <label className="relative inline-flex items-center cursor-pointer shrink-0">
                  <input
                    type="checkbox"
                    checked={codEnabled}
                    onChange={(e) => setCodEnabled(e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-navy-900"></div>
                </label>
              </div>
            </div>

            {/* Payment Method 2: QRIS */}
            <div className={cn(
              'bg-white border rounded-2xl p-5 transition-all shadow-2xs',
              qrisEnabled ? 'border-navy-900/60 ring-1 ring-navy-900/10' : 'border-slate-200/80'
            )}>
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3.5">
                  <div className={cn(
                    'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border',
                    qrisEnabled ? 'bg-navy-50 text-navy-800 border-navy-100' : 'bg-slate-100 text-slate-400 border-slate-200'
                  )}>
                    <QrCode className="w-5 h-5" strokeWidth={1.75} />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h2 className="font-semibold text-sm text-slate-900">
                        QRIS (standar pembayaran nasional)
                      </h2>
                      <span className={cn(
                        'text-[10px] font-semibold px-2 py-0.5 rounded-md border',
                        qrisEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                      )}>
                        {qrisEnabled ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Pelanggan membayar instan melalui pemindaian kode QR menggunakan GoPay, OVO, DANA, BCA Mobile, ShopeePay, atau aplikasi perbankan lainnya.
                    </p>
                  </div>
                </div>

                <label className="relative inline-flex items-center cursor-pointer shrink-0">
                  <input
                    type="checkbox"
                    checked={qrisEnabled}
                    onChange={(e) => setQrisEnabled(e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-navy-900"></div>
                </label>
              </div>
            </div>

            {/* Payment Method 3: Virtual Account */}
            <div className={cn(
              'bg-white border rounded-2xl p-5 transition-all shadow-2xs space-y-4',
              vaEnabled ? 'border-navy-900/60 ring-1 ring-navy-900/10' : 'border-slate-200/80'
            )}>
              <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-3.5">
                  <div className={cn(
                    'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border',
                    vaEnabled ? 'bg-navy-50 text-navy-800 border-navy-100' : 'bg-slate-100 text-slate-400 border-slate-200'
                  )}>
                    <CreditCard className="w-5 h-5" strokeWidth={1.75} />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h2 className="font-semibold text-sm text-slate-900">
                        Virtual account (transfer bank otomatis)
                      </h2>
                      <span className={cn(
                        'text-[10px] font-semibold px-2 py-0.5 rounded-md border',
                        vaEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'
                      )}>
                        {vaEnabled ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                    <p className="text-xs text-slate-500 mt-1 leading-relaxed">
                      Pelanggan mentransfer ke nomor Virtual Account unik yang diverifikasi secara otomatis tanpa perlu kirim bukti transfer.
                    </p>
                  </div>
                </div>

                <label className="relative inline-flex items-center cursor-pointer shrink-0">
                  <input
                    type="checkbox"
                    checked={vaEnabled}
                    onChange={(e) => setVaEnabled(e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-navy-900"></div>
                </label>
              </div>

              {/* Bank Selection (When VA enabled) */}
              {vaEnabled && (
                <div className="pt-3 border-t border-slate-100 space-y-2.5">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                      <Building2 className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.75} />
                      Pilih bank yang diterima:
                    </span>
                    <span className="text-[11px] text-slate-500 font-mono">
                      {selectedBanks.length} dari {ALL_VA_BANKS.length} bank aktif
                    </span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                    {ALL_VA_BANKS.map((bank) => {
                      const isSelected = selectedBanks.includes(bank);
                      return (
                        <button
                          key={bank}
                          type="button"
                          onClick={() => handleBankToggle(bank)}
                          className={cn(
                            'p-2.5 rounded-xl border text-center text-xs font-semibold transition-all cursor-pointer flex flex-col items-center justify-center gap-0.5',
                            isSelected
                              ? 'border-navy-900 bg-navy-900 text-white shadow-2xs'
                              : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300 hover:bg-white'
                          )}
                        >
                          <span className="font-mono">{bank}</span>
                          <span className={cn('text-[10px] font-normal', isSelected ? 'text-sky-200' : 'text-slate-400')}>
                            Virtual account
                          </span>
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>

            {/* Security & Operational Notice */}
            <div className="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-start gap-3 text-xs text-slate-700 shadow-2xs">
              <Info className="w-4 h-4 text-navy-800 shrink-0 mt-0.5" strokeWidth={1.75} />
              <div>
                <strong className="block font-semibold text-navy-950">Aturan kebijakan pembayaran:</strong>
                <span className="text-slate-600 mt-0.5 block leading-relaxed">
                  Minimal satu metode pembayaran harus selalu aktif agar pelanggan tetap dapat melakukan checkout.
                  Untuk pesanan dengan metode COD, pesanan dapat langsung dikerjakan dan status pembayaran diverifikasi lunas saat kurir menyerahkan uang tunai ke toko.
                </span>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex items-center justify-end gap-3 pt-2">
              <Button
                type="submit"
                disabled={saving || activeCount === 0}
                className="bg-navy-900 hover:bg-navy-800 text-white font-semibold py-2.5 px-6 rounded-xl text-xs min-h-[44px] shadow-2xs"
              >
                <ShieldCheck className="w-4 h-4 mr-2 text-sky-300" strokeWidth={1.75} />
                <span>{saving ? 'Menyimpan...' : 'Simpan pengaturan pembayaran'}</span>
              </Button>
            </div>
          </form>
        )}
      </div>
    </DashboardLayout>
  );
}

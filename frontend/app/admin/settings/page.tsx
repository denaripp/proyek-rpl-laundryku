'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, PlatformCommission } from '@/lib/admin';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatCard } from '@/components/admin/StatCard';
import { X, Sliders, Info, Clock } from 'lucide-react';

export default function AdminSettingsPage() {
  const [commission, setCommission] = useState<PlatformCommission | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [amount, setAmount] = useState<string>('1000');
  const [description, setDescription] = useState<string>('');
  const [saveLoading, setSaveLoading] = useState<boolean>(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  const fetchCommission = useCallback(async (isBackground = false) => {
    if (!isBackground) setLoading(true);
    else setRefreshing(true);

    try {
      const data = await adminApi.getCommission();
      setCommission(data);
      setAmount(data.amount.toString());
      setDescription(data.description || '');
    } catch {
      // keep existing
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchCommission();
  }, [fetchCommission]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const numAmount = parseInt(amount, 10);
    if (isNaN(numAmount) || numAmount < 0) {
      setFeedback({
        type: 'error',
        message: 'Nominal komisi platform harus berupa angka bulat non-negatif.',
      });
      return;
    }

    setSaveLoading(true);
    setFeedback(null);
    try {
      const res = await adminApi.updateCommission(numAmount, description.trim() || undefined);
      setCommission(res.data);
      setFeedback({
        type: 'success',
        message: 'Besaran komisi platform berhasil diperbarui.',
      });
    } catch (err: any) {
      setFeedback({
        type: 'error',
        message: err.message || 'Gagal memperbarui konfigurasi komisi.',
      });
    } finally {
      setSaveLoading(false);
    }
  };

  const formatRupiah = (val: number | null | undefined) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val || 0);
  };

  return (
    <div className="space-y-6 max-w-3xl">
      <AdminHeader
        title="Komisi platform"
        subtitle="Konfigurasi tarif flat komisi per pesanan laundry yang diselesaikan (snapshot per order)"
        onRefresh={() => fetchCommission(true)}
        refreshing={refreshing}
      />

      {feedback && (
        <div
          className={`p-3.5 rounded-xl text-xs flex items-center justify-between border ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border-rose-200 text-rose-800'
          }`}
        >
          <span className="font-medium">{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-700">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Current Value Bento Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <StatCard
          label="Komisi platform aktif"
          value={loading ? '...' : formatRupiah(commission?.amount)}
          subtext="Berlaku otomatis untuk order baru"
          tooltip="Nominal komisi ini disnapshot ke setiap order yang dibuat saat tarif ini aktif."
        />
        <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex flex-col justify-center">
          <div className="flex items-center gap-1.5 text-xs text-slate-500 font-medium uppercase tracking-wider mb-1">
            <Clock className="w-3.5 h-3.5" />
            <span>Pembaruan terakhir</span>
          </div>
          <div className="mt-1 text-sm font-mono font-semibold text-slate-800">
            {commission?.updated_at
              ? new Date(commission.updated_at).toLocaleString('id-ID', {
                  timeZone: 'Asia/Jakarta',
                  dateStyle: 'medium',
                  timeStyle: 'short',
                }) + ' WIB'
              : '-'}
          </div>
        </div>
      </div>

      {/* Edit Form */}
      <div className="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-2xs space-y-5">
        <div className="flex items-center gap-2.5 pb-4 border-b border-slate-100">
          <div className="w-8 h-8 rounded-lg bg-navy-50 text-navy-700 flex items-center justify-center">
            <Sliders className="w-4 h-4" />
          </div>
          <h2 className="text-sm font-semibold text-slate-900">Perbarui besaran komisi</h2>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Nominal komisi flat (Rupiah)
            </label>
            <div className="relative">
              <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-semibold text-slate-500">
                Rp
              </span>
              <input
                type="number"
                min="0"
                step="100"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
                className="w-full pl-9 pr-3.5 py-2.5 text-xs bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 font-mono tabular-nums text-slate-900 font-semibold transition-all"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1.5">
              Catatan / deskripsi konfigurasi
            </label>
            <textarea
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Misal: Penyesuaian biaya operasional platform 2026."
              className="w-full text-xs p-3 bg-slate-50/70 border border-slate-200 rounded-xl focus:outline-hidden focus:bg-white focus:border-navy-600 focus:ring-2 focus:ring-navy-600/15 text-slate-800 font-medium transition-all"
            />
          </div>

          <div className="p-3.5 rounded-xl bg-navy-50/60 border border-navy-100/80 flex items-start gap-2.5 text-xs text-navy-900">
            <Info className="w-4 h-4 text-navy-600 shrink-0 mt-0.5" />
            <span className="leading-relaxed">
              Perubahan komisi hanya akan diterapkan pada pesanan yang dibuat setelah waktu penyimpanan. Nilai komisi pada pesanan lama tetap tidak berubah (snapshot).
            </span>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={saveLoading}
              className="px-4 py-2.5 bg-navy-900 hover:bg-navy-800 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors cursor-pointer shadow-xs"
            >
              {saveLoading ? 'Menyimpan perubahan...' : 'Simpan pengaturan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { adminApi, PlatformCommission } from '@/lib/admin';
import { AdminHeader } from '@/components/admin/AdminHeader';
import { StatCard } from '@/components/admin/StatCard';
import { X, Sliders, Info } from 'lucide-react';

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
        title="Komisi Platform"
        subtitle="Konfigurasi tarif flat komisi per pesanan laundry yang diselesaikan (Snapshot per order)"
        onRefresh={() => fetchCommission(true)}
        refreshing={refreshing}
      />

      {feedback && (
        <div
          className={`p-3 rounded-md text-xs flex items-center justify-between ${
            feedback.type === 'success'
              ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
              : 'bg-rose-50 border border-rose-200 text-rose-800'
          }`}
        >
          <span>{feedback.message}</span>
          <button onClick={() => setFeedback(null)} className="text-slate-400 hover:text-slate-700">
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* Current Value Card */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <StatCard
          label="Komisi Platform Aktif"
          value={loading ? '...' : formatRupiah(commission?.amount)}
          subtext="Berlaku otomatis untuk order baru"
          tooltip="Nominal komisi ini disnapshot ke setiap order yang dibuat saat tarif ini aktif."
        />
        <div className="bg-white border border-slate-200 rounded-lg p-4 flex flex-col justify-center">
          <div className="text-xs text-slate-500 font-medium uppercase tracking-wider">Pembaruan Terakhir</div>
          <div className="mt-1 text-sm font-mono text-slate-800">
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
      <div className="bg-white border border-slate-200 rounded-lg p-5">
        <div className="flex items-center gap-2 pb-3 mb-4 border-b border-slate-100">
          <Sliders className="w-4 h-4 text-slate-600" strokeWidth={1.5} />
          <h2 className="text-sm font-semibold text-slate-900">Perbarui Besaran Komisi</h2>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">
              Nominal Komisi Flat (Rupiah)
            </label>
            <div className="relative">
              <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-medium text-slate-500">
                Rp
              </span>
              <input
                type="number"
                min="0"
                step="100"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                required
                className="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400 font-mono tabular-nums"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-700 mb-1">
              Catatan / Deskripsi Konfigurasi
            </label>
            <textarea
              rows={2}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Misal: Penyesuaian biaya operasional platform 2026."
              className="w-full text-xs p-2.5 bg-slate-50 border border-slate-300 rounded-md focus:outline-hidden focus:ring-1 focus:ring-slate-400"
            />
          </div>

          <div className="p-3 rounded-md bg-slate-50 border border-slate-200 flex items-start gap-2 text-xs text-slate-600">
            <Info className="w-4 h-4 text-slate-500 shrink-0 mt-0.5" strokeWidth={1.5} />
            <span>
              Perubahan komisi hanya akan diterapkan pada pesanan yang dibuat setelah waktu penyimpanan. Nilai komisi pada pesanan lama tetap tidak berubah (snapshot).
            </span>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={saveLoading}
              className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium rounded-md disabled:opacity-50 transition-colors cursor-pointer"
            >
              {saveLoading ? 'Menyimpan Perubahan...' : 'Simpan Pengaturan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

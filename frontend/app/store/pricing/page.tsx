'use client';

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, Badge, Toggle, Skeleton } from '@/components/ui';
import { getStoredUser, AuthUser } from '@/lib/auth';
import {
  getStoreAdminServices,
  updateStoreAdminService,
  toggleStoreAdminServiceStatus,
  getStorePricingRules,
  updateStorePricingRules,
  StoreServiceItem,
  StorePricingRules,
} from '@/lib/store';
import { formatRupiah, cn } from '@/lib/format';
import {
  Tag,
  Sparkles,
  CheckCircle2,
  AlertCircle,
  RefreshCw,
  Save,
  Percent,
  Scale,
  Wallet,
  Clock,
  Layers,
  Sliders,
} from 'lucide-react';

export default function StorePricingPage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [activeTab, setActiveTab] = useState<'rates' | 'rules'>('rates');

  // Services rates state
  const [services, setServices] = useState<StoreServiceItem[]>([]);
  const [loadingServices, setLoadingServices] = useState(true);
  const [serviceError, setServiceError] = useState<string | null>(null);
  const [editingPriceMap, setEditingPriceMap] = useState<Record<string, { price: number; minCharge: number }>>({});
  const [savingServiceId, setSavingServiceId] = useState<string | null>(null);
  const [serviceSuccessMsg, setServiceSuccessMsg] = useState<string | null>(null);

  // Pricing rules state
  const [rules, setRules] = useState<StorePricingRules>({
    min_weight_discount_kg: 5,
    min_weight_discount_percent: 0,
    minimum_order_amount: 15000,
    express_surcharge_amount: 5000,
    is_promo_active: false,
  });
  const [loadingRules, setLoadingRules] = useState(true);
  const [savingRules, setSavingRules] = useState(false);
  const [rulesSuccessMsg, setRulesSuccessMsg] = useState<string | null>(null);
  const [rulesErrorMsg, setRulesErrorMsg] = useState<string | null>(null);

  useEffect(() => {
    setCurrentUser(getStoredUser('mitra'));
  }, []);

  // Fetch Services List
  const fetchServices = useCallback(async () => {
    setLoadingServices(true);
    setServiceError(null);
    try {
      const res = await getStoreAdminServices();
      const items = res?.services || [];
      setServices(items);

      // Initialize editing map
      const map: Record<string, { price: number; minCharge: number }> = {};
      items.forEach((item) => {
        map[item.id] = {
          price: item.price_per_unit || 0,
          minCharge: item.minimum_charge || 0,
        };
      });
      setEditingPriceMap(map);
    } catch {
      setServiceError('Gagal memuat tarif layanan toko.');
    } finally {
      setLoadingServices(false);
    }
  }, []);

  // Fetch Pricing Rules
  const fetchRules = useCallback(async () => {
    setLoadingRules(true);
    setRulesErrorMsg(null);
    try {
      const data = await getStorePricingRules();
      if (data) {
        setRules(data);
      }
    } catch {
      // Use fallback defaults
    } finally {
      setLoadingRules(false);
    }
  }, []);

  useEffect(() => {
    fetchServices();
    fetchRules();
  }, [fetchServices, fetchRules]);

  // Handle Quick Save Rate per Service
  const handleSaveRate = async (serviceId: string) => {
    const editData = editingPriceMap[serviceId];
    if (!editData) return;

    if (editData.price < 0 || isNaN(editData.price)) {
      setServiceError('Tarif per satuan harus bernilai positif.');
      return;
    }

    setSavingServiceId(serviceId);
    setServiceError(null);
    setServiceSuccessMsg(null);

    try {
      await updateStoreAdminService(serviceId, {
        price_per_unit: editData.price,
        minimum_charge: editData.minCharge,
      });

      setServiceSuccessMsg('Tarif layanan berhasil diperbarui.');
      // Refresh local list state
      setServices((prev) =>
        prev.map((s) =>
          s.id === serviceId
            ? { ...s, price_per_unit: editData.price, minimum_charge: editData.minCharge }
            : s
        )
      );
      setTimeout(() => setServiceSuccessMsg(null), 3000);
    } catch {
      setServiceError('Gagal menyimpan perubahan tarif.');
    } finally {
      setSavingServiceId(null);
    }
  };

  // Toggle active status
  const handleToggleStatus = async (serviceId: string, currentStatus: boolean) => {
    try {
      const updated = await toggleStoreAdminServiceStatus(serviceId, !currentStatus);
      setServices((prev) =>
        prev.map((s) => (s.id === serviceId ? { ...s, is_active: updated.is_active } : s))
      );
    } catch {
      setServiceError('Gagal mengubah status layanan.');
    }
  };

  // Handle Save Rules Form
  const handleSaveRules = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingRules(true);
    setRulesSuccessMsg(null);
    setRulesErrorMsg(null);

    try {
      await updateStorePricingRules(rules);
      setRulesSuccessMsg('Aturan promosi dan diskon toko berhasil disimpan.');
      setTimeout(() => setRulesSuccessMsg(null), 4000);
    } catch {
      setRulesErrorMsg('Gagal menyimpan aturan promosi toko.');
    } finally {
      setSavingRules(false);
    }
  };

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
    >
      <div className="space-y-6 max-w-5xl">
        {/* Header Section */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div className="space-y-1">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center">
                <Tag className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
              </div>
              <h1 className="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                Pengaturan harga & diskon
              </h1>
            </div>
            <p className="text-xs sm:text-sm text-slate-500">
              Kelola tarif dasar layanan dan terapkan aturan diskon kuantitas untuk pelanggan gerai Anda.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start sm:self-auto">
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                fetchServices();
                fetchRules();
              }}
              className="text-xs rounded-xl border-slate-200 text-slate-700 inline-flex items-center gap-1.5 min-h-[38px] px-3.5 hover:bg-slate-50"
            >
              <RefreshCw className="w-3.5 h-3.5 text-slate-500" />
              <span>Segarkan</span>
            </Button>
          </div>
        </div>

        {/* Tab Controls */}
        <div className="flex items-center gap-2 p-1 bg-slate-100 rounded-2xl max-w-md">
          <button
            type="button"
            onClick={() => setActiveTab('rates')}
            className={cn(
              'flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              activeTab === 'rates'
                ? 'bg-white text-navy-900 shadow-2xs font-bold'
                : 'text-slate-500 hover:text-slate-800'
            )}
          >
            <Layers className="w-3.5 h-3.5" />
            <span>Tarif Layanan</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('rules')}
            className={cn(
              'flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              activeTab === 'rules'
                ? 'bg-white text-navy-900 shadow-2xs font-bold'
                : 'text-slate-500 hover:text-slate-800'
            )}
          >
            <Sliders className="w-3.5 h-3.5" />
            <span>Aturan Diskon & Promo</span>
          </button>
        </div>

        {/* TAB 1: TARIF DASAR LAYANAN */}
        {activeTab === 'rates' && (
          <div className="space-y-4">
            {serviceSuccessMsg && (
              <div className="p-3.5 bg-emerald-50 border border-emerald-200/90 rounded-2xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" strokeWidth={2} />
                <span>{serviceSuccessMsg}</span>
              </div>
            )}

            {serviceError && (
              <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
                <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
                <span>{serviceError}</span>
              </div>
            )}

            <div className="bg-white border border-slate-200/80 rounded-2xl shadow-2xs overflow-hidden">
              <div className="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                  <h2 className="text-sm font-bold text-slate-900">Penyesuaian Cepat Tarif</h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Ubah harga per satuan atau minimum charge langsung di tabel tanpa membuka formulir edit penuh.
                  </p>
                </div>
                <Link href="/store/services">
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-xs rounded-xl border-slate-200 text-navy-700 font-semibold inline-flex items-center gap-1.5"
                  >
                    <Sparkles className="w-3.5 h-3.5 text-navy-600" />
                    <span>Kelola Katalog Lengkap</span>
                  </Button>
                </Link>
              </div>

              {loadingServices ? (
                <div className="p-6 space-y-3">
                  <Skeleton className="h-10 w-full rounded-xl" />
                  <Skeleton className="h-10 w-full rounded-xl" />
                  <Skeleton className="h-10 w-full rounded-xl" />
                </div>
              ) : services.length === 0 ? (
                <div className="p-10 text-center space-y-3">
                  <p className="text-xs text-slate-500">Belum ada layanan aktif di gerai Anda.</p>
                  <Link href="/store/services">
                    <Button variant="primary" size="sm" className="bg-navy-900 text-white text-xs rounded-xl">
                      Tambah Layanan Pertama
                    </Button>
                  </Link>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead>
                      <tr className="bg-slate-50/80 text-slate-500 font-bold border-b border-slate-100">
                        <th className="py-3 px-4 sm:px-6">Layanan</th>
                        <th className="py-3 px-3">Satuan</th>
                        <th className="py-3 px-3 w-40">Tarif / Satuan (Rp)</th>
                        <th className="py-3 px-3 w-40">Min. Charge (Rp)</th>
                        <th className="py-3 px-3 text-center">Status</th>
                        <th className="py-3 px-4 sm:px-6 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 font-medium">
                      {services.map((item) => {
                        const edit = editingPriceMap[item.id] || {
                          price: item.price_per_unit || 0,
                          minCharge: item.minimum_charge || 0,
                        };
                        const isSaving = savingServiceId === item.id;
                        const isDirty =
                          edit.price !== (item.price_per_unit || 0) ||
                          edit.minCharge !== (item.minimum_charge || 0);

                        return (
                          <tr key={item.id} className="hover:bg-slate-50/50 transition-colors">
                            <td className="py-3.5 px-4 sm:px-6">
                              <div className="font-bold text-slate-900">{item.name}</div>
                              <div className="text-[11px] text-slate-400">
                                {item.category_name || 'Layanan Reguler'}
                              </div>
                            </td>
                            <td className="py-3.5 px-3">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 font-semibold uppercase text-[10px]">
                                {item.pricing_unit || 'KG'}
                              </span>
                            </td>
                            <td className="py-3.5 px-3">
                              <Input
                                type="number"
                                min={0}
                                step={500}
                                value={edit.price}
                                onChange={(e) =>
                                  setEditingPriceMap((prev) => ({
                                    ...prev,
                                    [item.id]: {
                                      ...prev[item.id],
                                      price: parseFloat(e.target.value) || 0,
                                    },
                                  }))
                                }
                                className="h-8 text-xs font-semibold rounded-lg border-slate-200"
                              />
                            </td>
                            <td className="py-3.5 px-3">
                              <Input
                                type="number"
                                min={0}
                                step={1000}
                                value={edit.minCharge}
                                onChange={(e) =>
                                  setEditingPriceMap((prev) => ({
                                    ...prev,
                                    [item.id]: {
                                      ...prev[item.id],
                                      minCharge: parseFloat(e.target.value) || 0,
                                    },
                                  }))
                                }
                                className="h-8 text-xs font-semibold rounded-lg border-slate-200"
                              />
                            </td>
                            <td className="py-3.5 px-3 text-center">
                              <div className="flex items-center justify-center">
                                <Toggle
                                  checked={Boolean(item.is_active)}
                                  onChange={() => handleToggleStatus(item.id, Boolean(item.is_active))}
                                />
                              </div>
                            </td>
                            <td className="py-3.5 px-4 sm:px-6 text-right">
                              <Button
                                variant="primary"
                                size="sm"
                                disabled={!isDirty || isSaving}
                                onClick={() => handleSaveRate(item.id)}
                                className={cn(
                                  'h-8 px-3 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-all',
                                  isDirty
                                    ? 'bg-navy-700 hover:bg-navy-800 text-white shadow-2xs'
                                    : 'bg-slate-100 text-slate-400 cursor-not-allowed border-none'
                                )}
                              >
                                <Save className="w-3 h-3" />
                                <span>{isSaving ? 'Menyimpan...' : 'Simpan'}</span>
                              </Button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        )}

        {/* TAB 2: ATURAN DISKON & PROMOSI */}
        {activeTab === 'rules' && (
          <form onSubmit={handleSaveRules} className="space-y-5">
            {rulesSuccessMsg && (
              <div className="p-3.5 bg-emerald-50 border border-emerald-200/90 rounded-2xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" strokeWidth={2} />
                <span>{rulesSuccessMsg}</span>
              </div>
            )}

            {rulesErrorMsg && (
              <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
                <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
                <span>{rulesErrorMsg}</span>
              </div>
            )}

            {/* Toggle Status Promo Global */}
            <div className="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-2xs flex items-center justify-between gap-4">
              <div className="space-y-0.5">
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-bold text-slate-900">Aktifkan Promosi Toko</h3>
                  <Badge variant={rules.is_promo_active ? 'success' : 'secondary'} size="sm">
                    {rules.is_promo_active ? 'Promo Aktif' : 'Nonaktif'}
                  </Badge>
                </div>
                <p className="text-xs text-slate-500">
                  Ketika aktif, pelanggan yang memenuhi syarat akan otomatis mendapatkan potongan harga saat checkout.
                </p>
              </div>

              <Toggle
                checked={rules.is_promo_active}
                onChange={(e) => setRules((prev) => ({ ...prev, is_promo_active: e.target.checked }))}
              />
            </div>

            {/* Rules Grid */}
            <div className="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-2xs space-y-5">
              <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
                <Percent className="w-4 h-4 text-navy-700" />
                <h3 className="text-sm font-bold text-slate-900">Diskon Kuantitas Cucian Kiloan</h3>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="block text-xs font-semibold text-slate-700">
                    Minimal Berat Cucian (kg)
                  </label>
                  <div className="relative flex items-center">
                    <Input
                      type="number"
                      min={1}
                      max={50}
                      step={0.5}
                      value={rules.min_weight_discount_kg}
                      onChange={(e) =>
                        setRules((prev) => ({
                          ...prev,
                          min_weight_discount_kg: parseFloat(e.target.value) || 0,
                        }))
                      }
                      className="rounded-xl border-slate-200 text-xs pr-12 font-semibold"
                    />
                    <span className="absolute right-3.5 text-xs text-slate-400 font-bold pointer-events-none">
                      KG
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Contoh: 5 kg untuk memicu diskon kuantitas.
                  </p>
                </div>

                <div className="space-y-1.5">
                  <label className="block text-xs font-semibold text-slate-700">
                    Besaran Diskon (%)
                  </label>
                  <div className="relative flex items-center">
                    <Input
                      type="number"
                      min={0}
                      max={50}
                      step={1}
                      value={rules.min_weight_discount_percent}
                      onChange={(e) =>
                        setRules((prev) => ({
                          ...prev,
                          min_weight_discount_percent: parseFloat(e.target.value) || 0,
                        }))
                      }
                      className="rounded-xl border-slate-200 text-xs pr-12 font-semibold"
                    />
                    <span className="absolute right-3.5 text-xs text-slate-400 font-bold pointer-events-none">
                      %
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Maksimal 50% untuk mencegah kerugian operasional gerai.
                  </p>
                </div>
              </div>
            </div>

            {/* Threshold & Surcharges */}
            <div className="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-2xs space-y-5">
              <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
                <Wallet className="w-4 h-4 text-navy-700" />
                <h3 className="text-sm font-bold text-slate-900">Ketentuan Transaksi & Biaya Tambahan</h3>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="block text-xs font-semibold text-slate-700">
                    Minimal Nilai Pesanan (Rp)
                  </label>
                  <div className="relative flex items-center">
                    <Input
                      type="number"
                      min={0}
                      step={1000}
                      value={rules.minimum_order_amount}
                      onChange={(e) =>
                        setRules((prev) => ({
                          ...prev,
                          minimum_order_amount: parseFloat(e.target.value) || 0,
                        }))
                      }
                      className="rounded-xl border-slate-200 text-xs pr-14 font-semibold"
                    />
                    <span className="absolute right-3.5 text-xs text-slate-400 font-bold pointer-events-none">
                      IDR
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Nilai minimum yang harus dibayar customer jika total cucian di bawah ambang batas.
                  </p>
                </div>

                <div className="space-y-1.5">
                  <label className="block text-xs font-semibold text-slate-700">
                    Biaya Tambahan Layanan Kilat / Express (Rp)
                  </label>
                  <div className="relative flex items-center">
                    <Input
                      type="number"
                      min={0}
                      step={1000}
                      value={rules.express_surcharge_amount}
                      onChange={(e) =>
                        setRules((prev) => ({
                          ...prev,
                          express_surcharge_amount: parseFloat(e.target.value) || 0,
                        }))
                      }
                      className="rounded-xl border-slate-200 text-xs pr-14 font-semibold"
                    />
                    <span className="absolute right-3.5 text-xs text-slate-400 font-bold pointer-events-none">
                      IDR
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400">
                    Surcharge per pesanan jika pelanggan memilih pengerjaan express (&lt; 24 jam).
                  </p>
                </div>
              </div>
            </div>

            {/* Action Submit */}
            <div className="flex justify-end pt-2">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                disabled={savingRules}
                className="bg-navy-700 hover:bg-navy-800 text-white rounded-xl text-xs font-bold px-6 py-2.5 shadow-2xs"
              >
                {savingRules ? 'Menyimpan Aturan...' : 'Simpan Semua Aturan Harga'}
              </Button>
            </div>
          </form>
        )}
      </div>
    </DashboardLayout>
  );
}

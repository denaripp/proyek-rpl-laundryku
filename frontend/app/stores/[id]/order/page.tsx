'use client';

import React, { useEffect, useState, use } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Card,
  Button,
  Input,
  Textarea,
  Badge,
  Skeleton,
  ErrorState,
} from '@/components/ui';
import { getStore, StoreItem, StoreServiceItem } from '@/lib/store';
import { createOrder, AddressSnapshot } from '@/lib/order';
import { getAddresses, AddressItem } from '@/lib/profile';
import { formatRupiah, cn } from '@/lib/format';
import {
  ArrowLeft,
  ShoppingBag,
  MapPin,
  Plus,
  Minus,
  AlertCircle,
  CheckCircle2,
  FileText,
  ShieldCheck,
  Building2,
  Clock,
} from 'lucide-react';

export default function OrderPlacementPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const resolvedParams = use(params);
  const storeId = resolvedParams.id;
  const searchParams = useSearchParams();
  const preselectedServiceId = searchParams.get('service');
  const router = useRouter();

  const [store, setStore] = useState<StoreItem | null>(null);
  const [loadingStore, setLoadingStore] = useState<boolean>(true);
  const [savedAddresses, setSavedAddresses] = useState<AddressItem[]>([]);
  const [selectedAddressId, setSelectedAddressId] = useState<string>('custom');

  // Order state
  const [quantities, setQuantities] = useState<Record<string, number>>({});
  const [pickupAddress, setPickupAddress] = useState<AddressSnapshot>({
    address_text: '',
    latitude: -6.8850,
    longitude: 107.6140,
    label: 'Rumah',
  });
  const [customerNotes, setCustomerNotes] = useState<string>('');

  const [submitting, setSubmitting] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Load store and saved addresses
  useEffect(() => {
    async function init() {
      setLoadingStore(true);
      try {
        const storeData = await getStore(storeId);
        setStore(storeData);

        // Pre-select service quantity
        if (storeData.services && storeData.services.length > 0) {
          const initQty: Record<string, number> = {};
          storeData.services.forEach((s) => {
            initQty[s.id] = s.id === preselectedServiceId ? (s.pricing_unit === 'kg' ? 3 : 1) : 0;
          });
          setQuantities(initQty);
        }

        // Try load customer saved addresses
        try {
          const addrList = await getAddresses();
          setSavedAddresses(addrList);
          const defaultAddr = addrList.find((a) => a.is_default) || addrList[0];
          if (defaultAddr) {
            setSelectedAddressId(defaultAddr.id);
            setPickupAddress({
              address_text: defaultAddr.address_text,
              latitude: Number(defaultAddr.latitude) || -6.8850,
              longitude: Number(defaultAddr.longitude) || 107.6140,
              label: defaultAddr.label,
              recipient_name: (defaultAddr as unknown as { recipient_name?: string }).recipient_name,
              recipient_phone: (defaultAddr as unknown as { recipient_phone?: string }).recipient_phone,
            });
          }
        } catch {
          // Unauthenticated or no profile address
        }
      } catch (err) {
        setErrorMsg(err instanceof Error ? err.message : 'Gagal memuat detail toko laundry.');
      } finally {
        setLoadingStore(false);
      }
    }
    init();
  }, [storeId, preselectedServiceId]);

  // Handle address preset selection
  const handleAddressSelect = (addrId: string) => {
    setSelectedAddressId(addrId);
    if (addrId === 'custom') {
      setPickupAddress({
        address_text: '',
        latitude: store?.latitude || -6.8850,
        longitude: store?.longitude || 107.6140,
        label: 'Alamat Baru',
      });
    } else {
      const selected = savedAddresses.find((a) => a.id === addrId);
      if (selected) {
        setPickupAddress({
          address_text: selected.address_text,
          latitude: Number(selected.latitude) || -6.8850,
          longitude: Number(selected.longitude) || 107.6140,
          label: selected.label,
          recipient_name: (selected as unknown as { recipient_name?: string }).recipient_name,
          recipient_phone: (selected as unknown as { recipient_phone?: string }).recipient_phone,
        });
      }
    }
  };

  // Update quantities
  const updateQuantity = (serviceId: string, delta: number, unit: string) => {
    const step = unit === 'kg' ? 0.5 : 1;
    const current = quantities[serviceId] || 0;
    const next = Math.max(0, Math.round((current + delta * step) * 10) / 10);
    setQuantities((prev) => ({ ...prev, [serviceId]: next }));
  };

  // Calculation breakdown
  const selectedItems = (store?.services || []).filter((s) => (quantities[s.id] || 0) > 0);

  const priceBreakdown = selectedItems.map((s) => {
    const qty = quantities[s.id] || 0;
    const rawLine = Math.round(qty * s.price_per_unit);
    const hasMinCharge = s.minimum_charge > 0 && rawLine < s.minimum_charge;
    const lineTotal = hasMinCharge ? s.minimum_charge : rawLine;
    return {
      service: s,
      quantity: qty,
      rawLine,
      hasMinCharge,
      lineTotal,
    };
  });

  const totalServicesEstimated = priceBreakdown.reduce((sum, item) => sum + item.lineTotal, 0);
  const platformFee = 1000;
  const grandTotalEstimated = totalServicesEstimated > 0 ? totalServicesEstimated + platformFee : 0;

  // Submit Order
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (selectedItems.length === 0) {
      setErrorMsg('Pilih minimal satu layanan dengan kuantitas lebih dari 0.');
      return;
    }
    if (!pickupAddress.address_text.trim()) {
      setErrorMsg('Alamat penjemputan lengkap wajib diisi.');
      return;
    }

    setSubmitting(true);
    setErrorMsg(null);

    try {
      const payload = {
        store_id: storeId,
        pickup_address: pickupAddress,
        delivery_address: pickupAddress,
        customer_notes: customerNotes.trim() || undefined,
        items: selectedItems.map((s) => ({
          service_id: s.id,
          quantity: quantities[s.id] || 1,
        })),
      };

      const orderResult = await createOrder(payload);
      if (orderResult?.id) {
        router.push(`/orders/${orderResult.id}`);
      } else {
        router.push('/orders');
      }
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      const msg =
        errObj.response?.data?.error?.message ||
        errObj.message ||
        'Gagal membuat pesanan. Pastikan Anda telah login dan berada dalam radius toko.';
      setErrorMsg(msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PublicLayout>
      <div className="bg-gradient-to-b from-sky-50/60 via-white to-sky-50/40 min-h-[calc(100vh-5rem)] py-8">
        <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Back Navigation */}
          <div className="mb-6">
            <Link
              href={`/stores/${storeId}`}
              className="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-navy-900 transition-colors min-h-[44px]"
            >
              <ArrowLeft className="w-4 h-4" />
              <span>Kembali ke Detail Toko</span>
            </Link>
          </div>

          {loadingStore ? (
            <div className="space-y-6">
              <Skeleton className="h-10 w-2/3" />
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-2 space-y-6">
                  <Skeleton className="h-48 rounded-3xl" />
                  <Skeleton className="h-64 rounded-3xl" />
                </div>
                <Skeleton className="h-96 rounded-3xl" />
              </div>
            </div>
          ) : errorMsg && !store ? (
            <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
              <ErrorState
                title="Gagal Memuat Toko"
                message={errorMsg}
                retryText="Kembali ke Marketplace"
                onRetry={() => router.push('/stores')}
              />
            </Card>
          ) : store && (
            <div>
              {/* Header */}
              <div className="mb-8">
                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-3 py-1 rounded-full border border-teal-200/60 w-fit mb-2">
                  <ShoppingBag className="w-3.5 h-3.5" />
                  <span>Form Pemesanan Laundry</span>
                </div>
                <h1 className="font-heading font-extrabold text-2xl sm:text-3xl text-navy-900 mb-2">
                  Buat Pesanan Laundry
                </h1>
                <p className="text-sm text-slate-600 flex items-center gap-2">
                  <Building2 className="w-4 h-4 text-teal-600" />
                  <span>Outlet: <strong className="text-navy-900">{store.name}</strong> ({store.address_text})</span>
                </p>
              </div>

              {errorMsg && (
                <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-3 text-red-800 text-sm">
                  <AlertCircle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                  <div>
                    <strong className="block font-bold">Terjadi Kendala:</strong>
                    <span>{errorMsg}</span>
                  </div>
                </div>
              )}

              <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Left Column: Services Selection & Address Form */}
                <div className="lg:col-span-2 space-y-6">
                  {/* 1. Services Selection */}
                  <Card variant="bento" className="p-6">
                    <div className="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                      <div>
                        <h2 className="font-heading font-bold text-lg text-navy-900">
                          1. Pilih Layanan & Estimasi Kuantitas
                        </h2>
                        <p className="text-xs text-slate-500">
                          Tentukan estimasi berat/jumlah cucian. Penimbangan final dilakukan di toko.
                        </p>
                      </div>
                      <Badge variant="secondary">{store.services?.length || 0} Layanan</Badge>
                    </div>

                    <div className="space-y-4">
                      {store.services?.map((service: StoreServiceItem) => {
                        const qty = quantities[service.id] || 0;
                        const isSelected = qty > 0;

                        return (
                          <div
                            key={service.id}
                            className={cn(
                              'p-4 rounded-2xl border transition-all duration-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4',
                              isSelected
                                ? 'bg-sky-50/50 border-teal-300 ring-2 ring-teal-100'
                                : 'bg-white border-slate-200 hover:border-slate-300'
                            )}
                          >
                            <div className="flex-1">
                              <div className="flex items-center gap-2 mb-1">
                                <h3 className="font-heading font-bold text-sm text-navy-900">
                                  {service.name}
                                </h3>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2 py-0.5 rounded border border-teal-200/60">
                                  /{service.pricing_unit}
                                </span>
                              </div>
                              <p className="text-xs text-slate-500 flex items-center gap-2">
                                <span className="font-bold text-teal-600">{formatRupiah(service.price_per_unit)}</span>
                                {service.minimum_charge > 0 && (
                                  <span>• Min. {formatRupiah(service.minimum_charge)}</span>
                                )}
                                <span>• Estimasi {service.estimated_duration_hours} Jam</span>
                              </p>
                            </div>

                            {/* Quantity Controls */}
                            <div className="flex items-center gap-3">
                              <button
                                type="button"
                                onClick={() => updateQuantity(service.id, -1, service.pricing_unit)}
                                disabled={qty <= 0}
                                className="w-9 h-9 rounded-xl border border-slate-200 bg-white flex items-center justify-center text-slate-700 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all active:scale-95 min-h-[36px]"
                                aria-label="Kurangi kuantitas"
                              >
                                <Minus className="w-4 h-4" />
                              </button>
                              <div className="text-center min-w-[3.5rem]">
                                <span className="font-heading font-bold text-base text-navy-900 tabular-nums">
                                  {qty}
                                </span>
                                <span className="text-[11px] text-slate-500 block -mt-1 font-medium">
                                  {service.pricing_unit}
                                </span>
                              </div>
                              <button
                                type="button"
                                onClick={() => updateQuantity(service.id, 1, service.pricing_unit)}
                                className="w-9 h-9 rounded-xl border border-teal-300 bg-teal-50 flex items-center justify-center text-teal-700 hover:bg-teal-100 transition-all active:scale-95 min-h-[36px]"
                                aria-label="Tambah kuantitas"
                              >
                                <Plus className="w-4 h-4" />
                              </button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </Card>

                  {/* 2. Pickup Address */}
                  <Card variant="bento" className="p-6">
                    <div className="mb-4 border-b border-slate-100 pb-3">
                      <h2 className="font-heading font-bold text-lg text-navy-900">
                        2. Alamat Penjemputan & Pengantaran
                      </h2>
                      <p className="text-xs text-slate-500">
                        Kurir akan menjemput dan mengantar cucian kembali ke alamat ini (Maks. radius {store.service_radius_km} km).
                      </p>
                    </div>

                    {/* Saved Addresses Options */}
                    {savedAddresses.length > 0 && (
                      <div className="mb-4">
                        <label className="block text-xs font-bold text-slate-700 mb-2">
                          Gunakan Alamat Tersimpan:
                        </label>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          {savedAddresses.map((addr) => (
                            <button
                              key={addr.id}
                              type="button"
                              onClick={() => handleAddressSelect(addr.id)}
                              className={cn(
                                'p-3 rounded-xl text-left border transition-all text-xs flex flex-col justify-between min-h-[44px]',
                                selectedAddressId === addr.id
                                  ? 'bg-sky-50 border-teal-400 ring-2 ring-teal-100 text-navy-900 font-semibold'
                                  : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300'
                              )}
                            >
                              <div className="flex items-center justify-between mb-1">
                                <span className="font-bold">{addr.label}</span>
                                {addr.is_default && (
                                  <span className="text-[10px] bg-teal-100 text-teal-800 px-1.5 py-0.5 rounded">Utama</span>
                                )}
                              </div>
                              <span className="text-slate-500 truncate block">{addr.address_text}</span>
                            </button>
                          ))}
                          <button
                            type="button"
                            onClick={() => handleAddressSelect('custom')}
                            className={cn(
                              'p-3 rounded-xl text-left border transition-all text-xs flex items-center gap-2 min-h-[44px]',
                              selectedAddressId === 'custom'
                                ? 'bg-sky-50 border-teal-400 ring-2 ring-teal-100 text-navy-900 font-semibold'
                                : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300'
                            )}
                          >
                            <Plus className="w-4 h-4 text-teal-600" />
                            <span>Ketik Alamat Lainnya</span>
                          </button>
                        </div>
                      </div>
                    )}

                    <div className="space-y-4 mt-4">
                      <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                          Alamat Lengkap Penjemputan *
                        </label>
                        <Textarea
                          required
                          value={pickupAddress.address_text}
                          onChange={(e) => setPickupAddress({ ...pickupAddress, address_text: e.target.value })}
                          placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, patokan pagar/warung..."
                          rows={3}
                          className="text-xs"
                        />
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-xs font-bold text-slate-700 mb-1.5">
                            Nama Penerima / Kontak
                          </label>
                          <Input
                            type="text"
                            value={pickupAddress.recipient_name || ''}
                            onChange={(e) => setPickupAddress({ ...pickupAddress, recipient_name: e.target.value })}
                            placeholder="Nama Lengkap"
                            className="text-xs"
                          />
                        </div>
                        <div>
                          <label className="block text-xs font-bold text-slate-700 mb-1.5">
                            Nomor Telepon / WhatsApp
                          </label>
                          <Input
                            type="tel"
                            value={pickupAddress.recipient_phone || ''}
                            onChange={(e) => setPickupAddress({ ...pickupAddress, recipient_phone: e.target.value })}
                            placeholder="0812xxxxxxx"
                            className="text-xs"
                          />
                        </div>
                      </div>
                    </div>
                  </Card>

                  {/* 3. Customer Notes */}
                  <Card variant="bento" className="p-6">
                    <div className="mb-3 border-b border-slate-100 pb-2">
                      <h2 className="font-heading font-bold text-base text-navy-900">
                        3. Catatan Tambahan (Opsional)
                      </h2>
                    </div>
                    <Textarea
                      value={customerNotes}
                      onChange={(e) => setCustomerNotes(e.target.value)}
                      placeholder="Contoh: Jangan pakai pewangi mawar, pisahkan kemeja putih, dll."
                      rows={2}
                      className="text-xs"
                    />
                  </Card>
                </div>

                {/* Right Column: Order Summary & Checkout Card */}
                <div className="space-y-6">
                  <div className="sticky top-24">
                    <Card variant="default" className="p-6 bg-white rounded-3xl border border-slate-200 shadow-sm space-y-5">
                      <div className="border-b border-slate-100 pb-3">
                        <h3 className="font-heading font-bold text-lg text-navy-900">
                          Ringkasan Estimasi
                        </h3>
                        <p className="text-xs text-slate-500">Harga snapshot awal</p>
                      </div>

                      {/* Selected Items Breakdown */}
                      {priceBreakdown.length === 0 ? (
                        <p className="text-xs text-slate-400 italic py-4 text-center">
                          Belum ada layanan yang dipilih.
                        </p>
                      ) : (
                        <div className="space-y-3">
                          {priceBreakdown.map((item) => (
                            <div key={item.service.id} className="text-xs flex justify-between items-start gap-2">
                              <div>
                                <span className="font-bold text-navy-900 block">{item.service.name}</span>
                                <span className="text-slate-500">
                                  {item.quantity} {item.service.pricing_unit} x {formatRupiah(item.service.price_per_unit)}
                                </span>
                                {item.hasMinCharge && (
                                  <span className="block text-[10px] text-amber-700 font-semibold">
                                    (Min. charge diterapkan)
                                  </span>
                                )}
                              </div>
                              <span className="font-bold text-navy-900 tabular-nums">
                                {formatRupiah(item.lineTotal)}
                              </span>
                            </div>
                          ))}
                        </div>
                      )}

                      <div className="border-t border-slate-100 pt-3 space-y-2 text-xs">
                        <div className="flex justify-between text-slate-600">
                          <span>Subtotal Layanan:</span>
                          <span className="font-bold tabular-nums text-navy-900">
                            {formatRupiah(totalServicesEstimated)}
                          </span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                          <span>Biaya Platform (Flat):</span>
                          <span className="font-bold tabular-nums text-navy-900">
                            {totalServicesEstimated > 0 ? formatRupiah(platformFee) : 'Rp 0'}
                          </span>
                        </div>
                        <div className="flex justify-between text-slate-600">
                          <span>Biaya Jemput & Antar:</span>
                          <span className="font-bold text-teal-600">Gratis (Termasuk)</span>
                        </div>
                      </div>

                      <div className="border-t border-slate-200 pt-3 flex justify-between items-baseline">
                        <div>
                          <span className="font-heading font-extrabold text-sm text-navy-900 block">
                            Total Estimasi:
                          </span>
                          <span className="text-[11px] text-slate-500">
                            *Dikonfirmasi setelah ditimbang
                          </span>
                        </div>
                        <span className="font-heading font-extrabold text-2xl text-teal-600 tabular-nums">
                          {formatRupiah(grandTotalEstimated)}
                        </span>
                      </div>

                      {/* Info Callout */}
                      <div className="p-3 bg-sky-50 rounded-2xl border border-sky-100 flex items-start gap-2.5 text-[11px] text-slate-600">
                        <ShieldCheck className="w-4 h-4 text-teal-600 shrink-0 mt-0.5" />
                        <span>
                          Harga snapshot tersimpan aman. Jika berat cucian berbeda, Anda akan menerima permintaan persetujuan harga final sebelum cuci diproses.
                        </span>
                      </div>

                      {/* Submit Button */}
                      <Button
                        type="submit"
                        disabled={submitting || selectedItems.length === 0}
                        className="w-full justify-center py-3.5 text-sm font-bold min-h-[48px] rounded-2xl"
                      >
                        {submitting ? 'Membuat Pesanan...' : 'Konfirmasi & Buat Pesanan'}
                      </Button>
                    </Card>
                  </div>
                </div>
              </form>
            </div>
          )}
        </div>
      </div>
    </PublicLayout>
  );
}

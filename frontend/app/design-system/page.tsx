'use client';

import React, { useState } from 'react';
import {
  Sparkles,
  ShieldCheck,
  Search,
  Clock,
  MoreVertical,
  Trash2,
} from 'lucide-react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { Checkbox } from '@/components/ui/Checkbox';
import { Radio } from '@/components/ui/Radio';
import { Toggle } from '@/components/ui/Toggle';
import { Card, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { Modal } from '@/components/ui/Modal';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { useToast } from '@/components/ui/Toast';
import { Tabs } from '@/components/ui/Tabs';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/components/ui/Table';
import { Pagination } from '@/components/ui/Pagination';
import { Skeleton } from '@/components/ui/Skeleton';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorState } from '@/components/ui/ErrorState';
import { Timeline } from '@/components/ui/Timeline';
import { Rating } from '@/components/ui/Rating';
import { Avatar } from '@/components/ui/Avatar';
import { Dropdown } from '@/components/ui/Dropdown';
import { OTPInput } from '@/components/ui/OTPInput';
import { formatRupiah, formatTanggal } from '@/lib/format';

export default function DesignSystemPage() {
  const { success, error: toastError, warning, info } = useToast();

  // Interactive UI Showcase States
  const [modalOpen, setModalOpen] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [activeTab1, setActiveTab1] = useState('tab1');
  const [activeTab2, setActiveTab2] = useState('tab1');
  const [currentPage, setCurrentPage] = useState(2);
  const [otpVal, setOtpVal] = useState('123456');
  const [ratingVal, setRatingVal] = useState(4.5);
  const [checkboxVal, setCheckboxVal] = useState(true);
  const [radioVal, setRadioVal] = useState('opt1');
  const [toggleVal, setToggleVal] = useState(true);

  return (
    <PublicLayout>
      {/* Header Title */}
      <div className="bg-gradient-to-b from-navy-900 to-navy-800 text-white py-12 border-b border-navy-700">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-semibold">
            <Sparkles className="w-3.5 h-3.5 text-amber-400" />
            LaundryKu Design System v2.0
          </div>
          <h1 className="font-heading font-extrabold text-3xl sm:text-4xl text-white tracking-tight">
            Design Tokens &amp; Component Library
          </h1>
          <p className="text-slate-300 text-sm sm:text-base max-w-2xl leading-relaxed">
            Panduan visual lengkap komponen antarmuka, token warna, skala tipografi, dan interaksi interaktif untuk ekosistem LaundryKu.
          </p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-16">
        {/* 1. COLOR PALETTE */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">1. Palet Warna (Color Tokens)</h2>
            <p className="text-sm text-slate-500">
              Komposisi 60% Putih/Sky-50, 30% Navy, 10% Teal + Amber. Kontras teks minimal 4,5:1.
            </p>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            {/* Navy */}
            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-navy-900 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #0B1F3A
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">navy-900</p>
                <p className="text-[11px] text-slate-500">Judul, Sidebar, Footer</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-navy-800 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #12305A
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">navy-800</p>
                <p className="text-[11px] text-slate-500">Primer, Hero bg</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-navy-700 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #1B4079
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">navy-700</p>
                <p className="text-[11px] text-slate-500">Hover / Pressed</p>
              </div>
            </div>

            {/* Teal */}
            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-teal-500 flex items-end p-3 text-navy-900 shadow-sm font-bold text-xs">
                #14B8A6
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">teal-500</p>
                <p className="text-[11px] text-slate-500">Aksen Utama</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-teal-400 flex items-end p-3 text-navy-900 shadow-sm font-bold text-xs">
                #2DD4BF
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">teal-400</p>
                <p className="text-[11px] text-slate-500">Focus Ring, Indikator</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-teal-50 border border-teal-200 flex items-end p-3 text-teal-800 shadow-sm font-bold text-xs">
                #F0FDFA
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">teal-50</p>
                <p className="text-[11px] text-slate-500">Latar Lembut / Card</p>
              </div>
            </div>

            {/* Amber */}
            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-amber-400 flex items-end p-3 text-navy-900 shadow-sm font-bold text-xs">
                #FBBF24
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">amber-400</p>
                <p className="text-[11px] text-slate-500">Highlight kecil / Rating</p>
              </div>
            </div>

            {/* Sky */}
            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-sky-50 border border-slate-200 flex items-end p-3 text-navy-900 shadow-sm font-bold text-xs">
                #F0F7FF
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">sky-50</p>
                <p className="text-[11px] text-slate-500">Latar Section</p>
              </div>
            </div>

            {/* Semantics */}
            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-emerald-600 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #16A34A
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">Success</p>
                <p className="text-[11px] text-slate-500">Status Selesai / Aktif</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-amber-500 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #F59E0B
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">Warning</p>
                <p className="text-[11px] text-slate-500">Status Menunggu</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-rose-600 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #DC2626
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">Danger</p>
                <p className="text-[11px] text-slate-500">Error / Ditolak / Hapus</p>
              </div>
            </div>

            <div className="space-y-2">
              <div className="h-20 rounded-2xl bg-blue-600 flex items-end p-3 text-white shadow-sm font-bold text-xs">
                #2563EB
              </div>
              <div>
                <p className="font-bold text-xs text-navy-900">Info</p>
                <p className="text-[11px] text-slate-500">Sedang Proses</p>
              </div>
            </div>
          </div>
        </section>

        {/* 2. TYPOGRAPHY */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">2. Tipografi (Typography)</h2>
            <p className="text-sm text-slate-500">
              Heading: Plus Jakarta Sans (600-800). Body/Form/Tabel: Inter (400-600). Nominal: Tabular Nums.
            </p>
          </div>

          <Card className="space-y-6">
            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">H1 — 36px / 40px (Plus Jakarta Sans Extrabold)</span>
              <h1 className="font-heading font-extrabold text-3xl sm:text-4xl text-navy-900">
                Pakaian Bersih Tanpa Repot, Dijemput Cepat
              </h1>
            </div>

            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">H2 — 28px (Plus Jakarta Sans Bold)</span>
              <h2 className="font-heading font-bold text-2xl sm:text-3xl text-navy-900">
                Layanan Laundry Terintegrasi &amp; Terpercaya
              </h2>
            </div>

            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">H3 — 20px (Plus Jakarta Sans Bold)</span>
              <h3 className="font-heading font-bold text-xl text-navy-900">
                Ringkasan Transaksi &amp; Penjemputan
              </h3>
            </div>

            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">Body Text — 16px (Inter Regular, Line-height 1.5)</span>
              <p className="text-base text-slate-700 leading-relaxed max-w-3xl">
                LaundryKu menghubungkan pelanggan dengan outlet laundry pilihan terdekat dan kurir profesional. Nikmati layanan antar-jemput higienis dengan jaminan tepat waktu.
              </p>
            </div>

            <div className="space-y-1">
              <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">Nominal Monospace Tabular (Tabular Nums)</span>
              <p className="text-2xl font-bold font-heading text-teal-700 tabular-nums">
                {formatRupiah(185000)} &bull; {formatTanggal(new Date().toISOString())}
              </p>
            </div>
          </Card>
        </section>

        {/* 3. BUTTONS */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">3. Tombol (Buttons)</h2>
            <p className="text-sm text-slate-500">
              Varian: Primary (Navy), Teal, Secondary, Ghost, Danger, Outline, dan Loading Spinner. Minimum touch target 44px.
            </p>
          </div>

          <Card className="space-y-6">
            <div className="flex flex-wrap gap-4 items-center">
              <Button variant="primary">Primary (Navy)</Button>
              <Button variant="teal">Teal Accent</Button>
              <Button variant="secondary">Secondary</Button>
              <Button variant="outline">Outline</Button>
              <Button variant="ghost">Ghost</Button>
              <Button variant="danger">Danger</Button>
              <Button variant="primary" isLoading>Memproses...</Button>
            </div>

            <div className="border-t border-slate-100 pt-4 flex flex-wrap gap-4 items-center">
              <Button size="sm" variant="primary">Small (sm)</Button>
              <Button size="md" variant="primary">Medium (md)</Button>
              <Button size="lg" variant="primary">Large (lg)</Button>
              <Button variant="teal">
                <Sparkles className="w-4 h-4 mr-2" />
                Dengan Ikon
              </Button>
            </div>
          </Card>
        </section>

        {/* 4. FORM CONTROLS */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">4. Formulir &amp; Input (Form Controls)</h2>
            <p className="text-sm text-slate-500">
              Input, Select, Textarea, Checkbox, Radio, Toggle, dan OTP Input (6 kotak, auto-focus &amp; paste).
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <Card className="space-y-4">
              <CardTitle>Input, Select &amp; Textarea</CardTitle>
              <Input
                label="Nama Lengkap"
                placeholder="Masukkan nama sesuai identitas"
                helperText="Nama ini akan tampil di profil dan pesanan."
              />
              <Input
                label="Input dengan Error"
                defaultValue="invalid-email"
                error="Format alamat email tidak valid."
              />
              <Select
                label="Pilihan Paket Laundry"
                options={[
                  { value: 'kiloan', label: 'Cuci Kiloan Reguler (2 Hari)' },
                  { value: 'express', label: 'Cuci Kiloan Express (3 Jam)' },
                  { value: 'dryclean', label: 'Dry Clean Satuan Jas/Gaun' },
                ]}
              />
              <Textarea
                label="Catatan Tambahan untuk Toko"
                placeholder="Contoh: Pisahkan pakaian putih dan gunakan detergen hipoalergenik."
                rows={2}
              />
            </Card>

            <Card className="space-y-6">
              <CardTitle>Checkbox, Radio, Toggle &amp; OTP</CardTitle>
              <div className="space-y-2">
                <Checkbox
                  label="Simpan sebagai alamat utama"
                  description="Gunakan alamat ini otomatis untuk pemesanan berikutnya."
                  checked={checkboxVal}
                  onChange={(e) => setCheckboxVal(e.target.checked)}
                />
              </div>

              <div className="space-y-2 border-t border-slate-100 pt-4">
                <span className="text-xs font-bold text-navy-900 block">Pilihan Pembayaran (Radio)</span>
                <div className="grid grid-cols-2 gap-2">
                  <Radio
                    name="pay_opt"
                    label="QRIS / E-Wallet"
                    checked={radioVal === 'opt1'}
                    onChange={() => setRadioVal('opt1')}
                  />
                  <Radio
                    name="pay_opt"
                    label="Tunai (COD)"
                    checked={radioVal === 'opt2'}
                    onChange={() => setRadioVal('opt2')}
                  />
                </div>
              </div>

              <div className="border-t border-slate-100 pt-4">
                <Toggle
                  label="Notifikasi WhatsApp"
                  description="Terima pembaruan status laundry langsung via chat WA"
                  checked={toggleVal}
                  onChange={(e) => setToggleVal(e.target.checked)}
                />
              </div>

              <div className="border-t border-slate-100 pt-4 space-y-2">
                <span className="text-xs font-bold text-navy-900 block">OTP Input (6 Boxes)</span>
                <OTPInput
                  value={otpVal}
                  onChange={setOtpVal}
                  onComplete={(val) => success('OTP Lengkap', `Kode ${val} siap diverifikasi`)}
                />
              </div>
            </Card>
          </div>
        </section>

        {/* 5. CARDS & BENTO GRID */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">5. Kartu (Cards &amp; Bento Grid)</h2>
            <p className="text-sm text-slate-500">
              Varian: Default, Bento (Modern Shadow + Hover), Navy, dan Sky.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card variant="default">
              <CardTitle>Card Default</CardTitle>
              <CardDescription>Kartu standar dengan border tipis dan latar putih.</CardDescription>
              <p className="text-sm text-slate-600 mt-4">
                Cocok untuk formulir pengaturan, detail pesanan, dan kontainer umum.
              </p>
            </Card>

            <Card variant="bento" interactive>
              <div className="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center mb-3">
                <Sparkles className="w-5 h-5" />
              </div>
              <CardTitle>Card Bento Interactive</CardTitle>
              <CardDescription>Hover untuk efek lift-up dan bayangan bertingkat.</CardDescription>
              <p className="text-sm text-slate-600 mt-4">
                Gunakan untuk highlight fitur, ringkasan statistik, atau opsi paket.
              </p>
            </Card>

            <Card variant="navy">
              <div className="w-10 h-10 rounded-xl bg-teal-500/20 text-teal-400 flex items-center justify-center mb-3">
                <ShieldCheck className="w-5 h-5" />
              </div>
              <h3 className="font-heading font-bold text-lg text-white">Card Navy Accent</h3>
              <p className="text-xs text-slate-400 mt-1">Latar gelap navy-900 yang elegan.</p>
              <p className="text-sm text-slate-300 mt-4">
                Ideal untuk banner promosi, CTA utama, atau card penekanan khusus.
              </p>
            </Card>
          </div>
        </section>

        {/* 6. BADGES & STATUS BADGES */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">6. Badges &amp; Status Badges</h2>
            <p className="text-sm text-slate-500">
              StatusBadge selalu menyertakan teks deskriptif dan ikon semantik (tidak hanya mengandalkan warna).
            </p>
          </div>

          <Card className="space-y-6">
            <div className="space-y-2">
              <h4 className="text-xs font-bold text-navy-900 uppercase">Pill Badges Standar</h4>
              <div className="flex flex-wrap gap-2">
                <Badge variant="default">Navy Default</Badge>
                <Badge variant="teal">Teal Soft</Badge>
                <Badge variant="amber">Amber Promo</Badge>
                <Badge variant="sky">Sky Blue</Badge>
                <Badge variant="secondary">Secondary</Badge>
                <Badge variant="outline">Outline</Badge>
              </div>
            </div>

            <div className="space-y-2 border-t border-slate-100 pt-4">
              <h4 className="text-xs font-bold text-navy-900 uppercase">Status Badges (Icon + Teks Semantik)</h4>
              <div className="flex flex-wrap gap-3">
                <StatusBadge status="active" label="Aktif" />
                <StatusBadge status="completed" label="Selesai" />
                <StatusBadge status="pending" label="Menunggu Verifikasi" />
                <StatusBadge status="in_progress" label="Sedang Dicuci" />
                <StatusBadge status="rejected" label="Pengajuan Ditolak" />
                <StatusBadge status="cancelled" label="Dibatalkan" />
              </div>
            </div>
          </Card>
        </section>

        {/* 7. DIALOGS, MODALS & TOASTS */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">7. Dialog, Modal &amp; Toast Notifikasi</h2>
            <p className="text-sm text-slate-500">
              Uji interaktivitas modal radius 24px, konfirmasi tindakan destruktif, dan toast 4 varian.
            </p>
          </div>

          <Card className="space-y-6">
            <div className="flex flex-wrap gap-4 items-center">
              <Button variant="primary" onClick={() => setModalOpen(true)}>
                Buka Modal Contoh
              </Button>
              <Button variant="danger" onClick={() => setConfirmOpen(true)}>
                Buka Confirm Dialog
              </Button>
            </div>

            <div className="border-t border-slate-100 pt-4 space-y-3">
              <h4 className="text-xs font-bold text-navy-900 uppercase">Pemicu Toast Notifikasi</h4>
              <div className="flex flex-wrap gap-3">
                <Button size="sm" variant="secondary" onClick={() => success('Pesanan Berhasil', 'Kurir sedang menuju ke lokasi Anda.')}>
                  Toast Success
                </Button>
                <Button size="sm" variant="secondary" onClick={() => toastError('Gagal Menyimpan', 'Periksa kembali koneksi internet Anda.')}>
                  Toast Error
                </Button>
                <Button size="sm" variant="secondary" onClick={() => warning('Peringatan Kuota', 'Radius pengantaran maksimal 10 km.')}>
                  Toast Warning
                </Button>
                <Button size="sm" variant="secondary" onClick={() => info('Pembaruan Sistem', 'Fitur pembayaran QRIS telah aktif.')}>
                  Toast Info
                </Button>
              </div>
            </div>
          </Card>
        </section>

        {/* 8. TABS, TABLES & PAGINATION */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">8. Tabs, Tabel Responsif &amp; Pagination</h2>
            <p className="text-sm text-slate-500">
              Struktur tabel rapi dengan font Inter, nominal tabular, dan navigasi halaman.
            </p>
          </div>

          <div className="space-y-6">
            <div className="space-y-3">
              <h4 className="text-xs font-bold text-navy-900 uppercase">2 Varian Tabs (Pill &amp; Boxed)</h4>
              <Tabs
                tabs={[
                  { id: 'tab1', label: 'Semua Pesanan', count: 12 },
                  { id: 'tab2', label: 'Dalam Proses', count: 3 },
                  { id: 'tab3', label: 'Selesai', count: 9 },
                ]}
                activeTab={activeTab1}
                onChange={setActiveTab1}
                variant="pill"
              />
              <Tabs
                tabs={[
                  { id: 'tab1', label: 'Harian' },
                  { id: 'tab2', label: 'Mingguan' },
                  { id: 'tab3', label: 'Bulanan' },
                ]}
                activeTab={activeTab2}
                onChange={setActiveTab2}
                variant="boxed"
              />
            </div>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>No. Resi</TableHead>
                  <TableHead>Pelanggan</TableHead>
                  <TableHead>Layanan</TableHead>
                  <TableHead>Total Biaya</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow>
                  <TableCell className="font-mono font-bold text-navy-900 text-xs">#LK-9821</TableCell>
                  <TableCell className="font-semibold">Budi Santoso</TableCell>
                  <TableCell>Cuci Kiloan (4 kg)</TableCell>
                  <TableCell className="font-bold tabular-nums text-teal-700">{formatRupiah(28000)}</TableCell>
                  <TableCell><StatusBadge status="completed" label="Selesai" /></TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-mono font-bold text-navy-900 text-xs">#LK-9822</TableCell>
                  <TableCell className="font-semibold">Siti Aminah</TableCell>
                  <TableCell>Express 3 Jam (2 kg)</TableCell>
                  <TableCell className="font-bold tabular-nums text-teal-700">{formatRupiah(30000)}</TableCell>
                  <TableCell><StatusBadge status="in_progress" label="Dicuci" /></TableCell>
                </TableRow>
                <TableRow>
                  <TableCell className="font-mono font-bold text-navy-900 text-xs">#LK-9823</TableCell>
                  <TableCell className="font-semibold">Rian Pratama</TableCell>
                  <TableCell>Cuci Sepatu Premium</TableCell>
                  <TableCell className="font-bold tabular-nums text-teal-700">{formatRupiah(50000)}</TableCell>
                  <TableCell><StatusBadge status="pending" label="Menunggu Jemput" /></TableCell>
                </TableRow>
              </TableBody>
            </Table>

            <Pagination
              currentPage={currentPage}
              totalPages={5}
              onPageChange={setCurrentPage}
            />
          </div>
        </section>

        {/* 9. TIMELINE, RATING, AVATAR & DROPDOWN */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">9. Timeline, Rating, Avatar &amp; Dropdown</h2>
            <p className="text-sm text-slate-500">
              Pelengkap visual alur operasional dan identitas akun.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card className="space-y-4">
              <CardTitle>Status Timeline</CardTitle>
              <Timeline
                events={[
                  { id: '1', title: 'Pesanan Dibuat', time: '08:30 WIB', status: 'completed' },
                  { id: '2', title: 'Kurir Menjemput Cucian', time: '09:15 WIB', status: 'completed' },
                  { id: '3', title: 'Proses Pencucian di Outlet', time: '10:00 WIB', status: 'current' },
                  { id: '4', title: 'Pengantaran Kembali', status: 'upcoming' },
                ]}
              />
            </Card>

            <Card className="space-y-6">
              <div className="space-y-2">
                <CardTitle>Rating Bintang (Amber-400)</CardTitle>
                <CardDescription>Bintang interaktif dengan tabular-nums</CardDescription>
                <div className="pt-2">
                  <Rating
                    value={ratingVal}
                    count={142}
                    size="lg"
                    interactive
                    onChange={setRatingVal}
                  />
                </div>
              </div>

              <div className="space-y-2 border-t border-slate-100 pt-4">
                <CardTitle>Avatar &amp; Status Dot</CardTitle>
                <div className="flex items-center gap-3">
                  <Avatar name="Budi Santoso" size="lg" status="online" />
                  <Avatar name="Siti Rahma" size="md" status="busy" />
                  <Avatar name="Admin Laundry" size="sm" status="away" />
                </div>
              </div>
            </Card>

            <Card className="space-y-4">
              <CardTitle>Dropdown Popover Menu</CardTitle>
              <CardDescription>Menu opsi aksi dengan ikon &amp; pemisah</CardDescription>
              <div className="pt-2">
                <Dropdown
                  trigger={
                    <Button variant="secondary">
                      <span>Pilihan Tindakan</span>
                      <MoreVertical className="w-4 h-4 ml-2" />
                    </Button>
                  }
                  items={[
                    { id: 'view', label: 'Lihat Rincian Resi', icon: Search, onClick: () => info('Membuka detail resi') },
                    { id: 'edit', label: 'Ubah Jadwal Antar', icon: Clock, onClick: () => info('Ubah jadwal') },
                    { id: 'div-1', label: '', divider: true },
                    { id: 'del', label: 'Batalkan Pesanan', icon: Trash2, danger: true, onClick: () => setConfirmOpen(true) },
                  ]}
                />
              </div>
            </Card>
          </div>
        </section>

        {/* 10. EMPTY STATE, ERROR STATE & SKELETON */}
        <section className="space-y-6">
          <div className="border-b border-slate-200 pb-3">
            <h2 className="font-heading font-bold text-2xl text-navy-900">10. State Tampilan (Empty, Error, Skeleton)</h2>
            <p className="text-sm text-slate-500">
              Setiap halaman wajib memiliki state loading shimmer, data kosong, dan pesan error ramah.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <EmptyState
              title="Belum Ada Pesanan"
              description="Anda belum memiliki pesanan aktif saat ini. Mulai pesan cucian pertama Anda sekarang."
              actionText="Pesan Laundry Sekarang"
              onAction={() => success('Navigasi Pemesanan', 'Mengarahkan ke form pesanan')}
            />

            <ErrorState
              title="Gagal Memuat Data"
              message="Terjadi gangguan saat mengambil data dari server. Silakan coba kembali."
              onRetry={() => success('Mencoba Ulang', 'Memuat kembali data...')}
            />

            <Card className="space-y-3">
              <CardTitle>Skeleton Loader Shimmer</CardTitle>
              <Skeleton className="h-6 w-3/4" />
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-5/6" />
              <div className="pt-4 flex gap-2">
                <Skeleton className="h-10 w-24" />
                <Skeleton className="h-10 w-32" />
              </div>
            </Card>
          </div>
        </section>
      </div>

      {/* Interactive Modal Demo */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title="Contoh Dialog Modal LaundryKu"
        description="Modal dengan radius 24px, backdrop blur, dan animasi fade-in."
      >
        <div className="space-y-4">
          <p className="text-sm text-slate-600 leading-relaxed">
            Ini adalah implementasi komponen Modal yang sesuai dengan pedoman desain: latar putih bersih, shadow lembut berlapis, dan kontrol tombol yang mudah dijangkau di layar sentuh ponsel.
          </p>
          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <Button variant="secondary" onClick={() => setModalOpen(false)}>
              Tutup
            </Button>
            <Button variant="primary" onClick={() => { setModalOpen(false); success('Tersimpan', 'Aksi modal berhasil dieksekusi.'); }}>
              Konfirmasi Selesai
            </Button>
          </div>
        </div>
      </Modal>

      {/* Interactive Confirm Dialog Demo */}
      <ConfirmDialog
        isOpen={confirmOpen}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => {
          setConfirmOpen(false);
          toastError('Dihapus', 'Contoh item berhasil dihapus.');
        }}
        title="Batalkan Pesanan Ini?"
        description="Apakah Anda yakin ingin membatalkan pesanan laundry ini? Tindakan ini tidak dapat dibatalkan."
        confirmText="Ya, Batalkan"
        cancelText="Kembali"
        variant="danger"
      />
    </PublicLayout>
  );
}

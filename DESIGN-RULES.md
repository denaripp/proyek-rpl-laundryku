# DESIGN-RULES.md — Pedoman Sistem Desain Frontend LaundryKu

Dokumen ini adalah aturan wajib (*mandatory guidelines*) untuk setiap pengembangan antarmuka (frontend) di ekosistem LaundryKu. Semua kontributor, pengembang, dan modul baru wajib mematuhi panduan ini.

---

## 1. Komponen & Arsitektur UI
- **Gunakan Komponen Reusable**: Selalu gunakan komponen siap pakai dari `@/components/ui/` (`Button`, `Input`, `Select`, `Textarea`, `Checkbox`, `Radio`, `Toggle`, `Card`, `Badge`, `StatusBadge`, `Modal`, `ConfirmDialog`, `Toast`, `Tabs`, `Table`, `Pagination`, `Skeleton`, `EmptyState`, `ErrorState`, `Timeline`, `Rating`, `Avatar`, `Dropdown`, `OTPInput`).
- **Dilarang Duplikasi**: Jangan membuat ulang styling tombol, input, badge, atau modal baru secara ad-hoc bila sudah tersedia variannya di `components/ui/`.
- **Layout Standar**: Gunakan `@/components/layout/` yang sesuai:
  - `PublicLayout`: Halaman landing, katalog, tentang kami (Navbar desktop, Footer, Bottom Nav mobile).
  - `AuthLayout`: Halaman login/daftar/verifikasi OTP (Centered card pada latar `sky-50` dengan aksen SVG lembut).
  - `DashboardLayout`: Dashboard internal role-based (Sidebar `navy-900` collapsible + Topbar + Avatar dropdown).

---

## 2. Token Warna & Larangan Hardcoding
- **Dilarang Hardcode Hex/Font**: Jangan menulis nilai hex warna manual (misal `style={{ color: '#0B1F3A' }}`) atau font inline pada komponen. Gunakan class token Tailwind yang telah didefinisikan di `@theme` (`globals.css`):
  - **Navy**: `bg-navy-900` (`#0B1F3A`), `bg-navy-800` (`#12305A`), `bg-navy-700` (`#1B4079`) — *Untuk judul, sidebar, footer, tombol utama*.
  - **Teal**: `bg-teal-500` (`#14B8A6`), `text-teal-400` (`#2DD4BF`), `bg-teal-50` (`#F0FDFA`) — *Untuk aksen utama, focus ring, indikator aktif, background lembut*.
  - **Amber**: `bg-amber-400` (`#FBBF24`), `text-amber-400` — *Hanya untuk highlight kecil (bintang rating, 1 badge promo per layar)*.
  - **Sky & Neutral**: `bg-sky-50` (`#F0F7FF`), `bg-white`, `border-slate-200`, `text-slate-500`, `text-slate-900`.
  - **Semantik**: Success (`#16A34A`), Warning (`#F59E0B`), Danger (`#DC2626`), Info (`#2563EB`).
- **Komposisi 60-30-10**: Sekitar 60% Putih/Sky-50, 30% Navy, 10% Teal + Amber.
- **Aturan Kontras (WCAG AA Minimal 4,5:1)**:
  - Teks putih hanya digunakan di atas latar gelap (`navy-900` / `navy-800`).
  - Teks di atas latar `teal-500` dan `amber-400` wajib menggunakan `text-navy-900` (gelap).
- **Penggunaan Amber Hemat**: Amber hanya boleh muncul 1-2 elemen kecil per viewport (misalnya rating bintang atau 1 pill promo). Dilarang menjadikan amber sebagai warna background blok besar.

---

## 3. Tipografi
- **Headings**: `Plus Jakarta Sans` (font-weight 600-800) melalui class `font-heading`.
- **Body, Form & Tabel**: `Inter` (font-weight 400-600) melalui class `font-sans`.
- **Mata Uang & Angka**: Nominal rupiah dan jam wajib menggunakan angka tabular via class `tabular-nums` dan helper `formatRupiah()` / `formatTanggal()`.
- **Hierarki Ukuran**:
  - H1: 32px – 40px (`text-3xl sm:text-4xl lg:text-5xl font-extrabold`)
  - H2: 24px – 28px (`text-2xl sm:text-3xl font-bold`)
  - H3: 20px (`text-xl font-bold`)
  - Body: 16px (`text-base leading-relaxed`)
  - Small / Subtext: 12px – 14px (`text-xs` / `text-sm text-slate-500`)

---

## 4. Bentuk, Sentuhan & Aksesibilitas
- **Radius Sudut Standar**:
  - Tombol & Input/Select/Textarea: `rounded-xl` (12px)
  - Kartu & Bento Grid: `rounded-2xl` / `rounded-3xl` (16px – 24px)
  - Modal Dialog: `rounded-3xl` (24px)
  - Badges & Pills: `rounded-full`
- **Target Sentuh Minimum 44px (Mobile-First)**: Semua elemen interaktif (tombol, input, link navigasi, radio, checkbox, pagination) wajib memiliki area sentuh minimal 44x44 pixel (`min-h-[44px]`).
- **Focus Ring**: Elemen interaktif harus memiliki focus state jelas dengan ring teal (`focus:ring-2 focus:ring-teal-400 focus:outline-none`).
- **Tindakan Destruktif**: Setiap penghapusan permanen atau pembatalan wajib memunculkan `ConfirmDialog` sebelum mengeksekusi request backend.
- **StatusBadge**: Status badge wajib memiliki teks penjelasan yang jelas dan ikon semantik (jangan hanya mengandalkan bulatan warna saja demi aksesibilitas tunawarna).

---

## 5. State Tampilan (Loading, Empty, Error)
Setiap halaman data atau daftar wajib menangani dan menampilkan 3 state:
1. **Loading State**: Gunakan skeleton shimmer loader (`<Skeleton />`), bukan spinner liar atau layar kosong.
2. **Empty State**: Tampilkan `<EmptyState />` dengan pesan yang ramah, ikon/slot ilustrasi, dan tombol tindakan berikutnya (call-to-action).
3. **Error State**: Tampilkan `<ErrorState />` dengan penjelasan kendala dan tombol *"Coba Lagi"* (`onRetry`).

---

## 6. Efek Visual & Animasi
- **Kreativitas Terkontrol**: Gradient navy->teal hanya diterapkan pada Hero dan Header tertentu. Motif gelembung SVG hanya sebagai aksen tipis (opacity 3-5%) tanpa mengganggu keterbacaan teks konten.
- **Durasi Transisi**: 150ms – 250ms untuk hover kartu, tab switch, dan dialog.
- **Reduced Motion**: Selalu menghormati preferensi pengguna dengan `motion-reduce:transition-none` dan `motion-reduce:animate-none`.

---

*LaundryKu Frontend Design Guidelines — Dibuat untuk konsistensi pengalaman pengguna yang bersih, segar, hangat, dan terpercaya.*

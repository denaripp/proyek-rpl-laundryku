# PRD v2 — Laundryku: Marketplace Laundry + AI Chat

> Pembaruan spesifikasi berdasarkan request terbaru. File lama tetap ada di `prd.md`.
> Perubahan besar vs v1: stack frontend React + Tailwind, database PostgreSQL,
> pembayaran online via Xendit (QRIS), tracking kurir via Google Maps.

---

## 1. Ringkasan Produk

Laundryku adalah marketplace laundry: pelanggan memilih toko laundry, kurir
menjemput ke rumah, pelanggan melacak posisi kurir secara live, laundry
ditimbang di toko, lalu pelanggan membayar (cash atau QRIS online). Dilengkapi
chat AI untuk bantuan pelanggan dan sistem keamanan kelas produksi.

## 2. Tujuan & Metrik Sukses

- Order pertama selesai < 5 menit dari pendaftaran.
- 100% order online-payment menampilkan QRIS dengan deadline 1 jam.
- Order tidak dibayar dalam 1 jam otomatis masuk proses pengembalian.
- Zero unauthorized access antar role (audit keamanan lulus).

## 3. Pengguna & Role (RBAC)

| Role | Deskripsi | Akses utama |
|---|---|---|
| Owner (platform) | Pemilik platform Laundryku | Kelola verifikasi toko, keuangan global, kebijakan |
| Admin owner | Staf yang ditunjuk owner platform | Verifikasi toko, monitoring order, support |
| Owner toko | Pemilik toko laundry | Kelola toko, layanan, harga, staf, laporan toko |
| Admin toko | Staf operasional toko | Update status order, timbang, input hasil |
| Jasa antar (kurir) | Kurir pickup & delivery | Terima job, update lokasi, selesaikan antar/jemput |
| Pembeli (customer) | Pelanggan akhir | Order, tracking, bayar, chat AI, riwayat |

Semua otorisasi via policy RBAC di backend — setiap endpoint memverifikasi role
dan kepemilikan resource (contoh: admin toko A tidak bisa menyentuh order toko B).

## 4. Skema Pendaftaran

### 4.1 Customer (pembeli)
1. Klik "Daftar dengan Google" → OAuth 2.0 (Laravel Socialite + Google API).
2. Akun dibuat otomatis dari profil Google (email, nama, foto).
3. Saat pertama kali order, WAJIB melengkapi data:
   - Nama lengkap
   - Alamat lengkap
   - RT / RW
   - Kelurahan/Kecamatan & kota
   - Nomor telepon (verifikasi OTP)
   - Titik lokasi di peta (Google Maps picker) untuk akurasi penjemputan

### 4.2 Owner toko (jawaban pertanyaan "skema daftar untuk owner?")
1. Registrasi via email + password ATAU Google Sign-In.
2. Isi formulir pengajuan toko:
   - Nama toko, alamat, nomor telepon, jam operasional
   - Foto tempat usaha, KTP pemilik, (opsional) NIB
   - Daftar layanan awal & harga per kg
3. Status akun: `pending_verification`.
4. Admin owner / owner platform memverifikasi (approve / reject + alasan).
5. Jika approved: toko aktif, owner toko bisa:
   - mengelola layanan & harga
   - mengundang admin toko (staf)
   - melihat laporan keuangan toko
6. Jika rejected: notifikasi email + alasan, boleh ajukan ulang.

## 5. Alur Order End-to-End

```
Customer pilih toko & layanan
  → isi/confirm data alamat (first order)
  → order dibuat (status: pending)
  → kurir diberi job pickup
  → kurir jemput, customer tracking live di peta
  → laundry sampai toko → ditimbang oleh admin toko
  → notifikasi ke customer: berat + total + QRIS (jika online)
  → bayar (QRIS ≤ 1 jam ATAU cash)
  → proses cuci → selesai → kurir delivery
  → customer terima → order done → rating
```

Status order: `pending → assigned → picking_up → arrived_at_store →
weighing → awaiting_payment → paid/washing → drying → ironing →
ready_delivery → delivering → completed` (+ `expired_unpaid → returning`,
`cancelled`).

## 6. Tracking Kurir (Google Maps)

- Customer melihat posisi kurir real-time di peta (Google Maps JavaScript API).
- Rute & ETA via Directions API.
- Kurir mengirim update lokasi via app (interval ~10 detik saat aktif).
- Backend menyimpan riwayat titik per job (tabel `courier_locations`).
- Notifikasi otomatis: "kurir berangkat", "kurir dekat", "kurir tiba".

## 7. Pembayaran Online (Xendit — QRIS)

1. Setelah timbang selesai, sistem membuat invoice Xendit
   (QRIS, `external_id` = order id).
2. QRIS ditampilkan ke customer (halaman order + notifikasi).
3. Batas waktu: 60 menit. Countdown tampil di UI.
4. Xendit webhook (`payment_callback`) → validasi signature/token →
   tandai `paid`, lanjutkan proses cuci.
5. Jika 60 menit tidak dibayar:
   - status `expired_unpaid`, QRIS dibatalkan
   - otomatis masuk proses pengembalian (kurir antar balik laundry)
   - notifikasi ke customer & toko.
6. Refund (mis. berat final berubah / kompromi): via Xendit Refund API,
   dicatat di tabel `refunds` dengan alasan + bukti.

Pembayaran cash: customer bayar langsung (saat pickup/delivery sesuai kebijakan
toko), admin toko menandai `paid_cash`.

## 8. Chat AI

- Widget chat untuk customer: tanya harga, status order, FAQ, cara bayar.
- AI diberi konteks (read-only) data order milik user yang sedang login.
- Eskalasi ke admin toko / support jika AI tidak bisa jawab.
- Semua percakapan disimpan (tabel `chat_sessions`, `chat_messages`).

## 9. Notifikasi

- In-app + email (dan opsional WhatsApp) untuk event:
  order dibuat, kurir berangkat, laundry sampai & ditimbang (dengan QRIS),
  pembayaran sukses, pembayaran expired, laundry dikirim balik, order selesai.

## 10. Keamanan (Expert)

- Password: Argon2id (untuk yang daftar email); akun Google tanpa password.
- Autentikasi API: Sanctum token + refresh strategy; session cookie HttpOnly,
  Secure, SameSite=Lax untuk web.
- Otorisasi: RBAC + Policy di setiap endpoint (bukan cuma middleware role).
- Rate limiting: login, OTP, pembuatan order, chat AI.
- Webhook Xendit: validasi callback token + idempotency key.
- CSRF protection di semua form; validasi & sanitasi semua input (Form Request).
- Enkripsi data sensitif at-rest (nomor telepon, alamat) via Laravel cast encrypted.
- Audit log semua aksi sensitif (ubah harga, refund, verifikasi toko, login).
- 2FA TOTP opsional untuk owner platform & owner toko.
- HTTPS wajib; security headers (CSP, HSTS, X-Content-Type-Options).
- Backup DB terjadwal + uji restore.

## 11. Tech Stack & Arsitektur

- Frontend: React + Tailwind CSS (SPA, consume REST API).
- Backend: PHP + Laravel, arsitektur MVC + Service layer
  (Controller → Service → Model/Repository).
- Database: PostgreSQL.  ⚠ Catatan: project saat ini pakai MySQL 8 di Laragon —
  perlu keputusan migrasi atau install PostgreSQL lokal.
- Queue: Laravel queue (database/redis) untuk notifikasi, webhook handling,
  job scheduler expiry QRIS.
- Realtime: Laravel Broadcasting (Reverb) untuk tracking & chat.

## 12. Model Data (garis besar, PostgreSQL)

- users (id, name, email, google_id nullable, phone, role, email_verified_at)
- customer_profiles (user_id, full_name, address, rt, rw, kelurahan, kecamatan,
  city, lat, lng)
- stores (id, owner_id, name, address, lat, lng, phone, status
  [pending/active/rejected], rejection_reason, opening_hours)
- store_staff (store_id, user_id, role [owner_toko/admin_toko])
- services (store_id, name, price_per_kg, unit, active)
- orders (customer_id, store_id, courier_id, status, weight_kg, total,
  payment_method [cash/online], payment_status, paid_at, qr_expired_at)
- order_items (order_id, service_id, qty, subtotal)
- order_status_histories (order_id, status, changed_by, note, created_at)
- courier_locations (courier_id, job_id, lat, lng, recorded_at)
- payments (order_id, xendit_invoice_id, amount, status, paid_at, expired_at)
- refunds (order_id/payment_id, amount, reason, xendit_refund_id, status)
- notifications (user_id, type, payload, read_at)
- chat_sessions / chat_messages
- audit_logs (user_id, action, model, model_id, old, new, ip)

## 13. Integrasi Pihak Ketiga

| Layanan | Kegunaan | Paket/SDK |
|---|---|---|
| Google OAuth | Login customer & owner | Laravel Socialite |
| Google Maps JS + Directions | Tracking kurir, ETA, alamat picker | @googlemaps/js-api-loader |
| Xendit | QRIS invoice, webhook, refund | xendit/xendit-php atau REST |

## 14. Tahapan Pengerjaan (roadmap)

1. Fondasi: setup Laravel + PostgreSQL, auth Google, RBAC 6 role.
2. Manajemen toko: registrasi owner + verifikasi admin.
3. Katalog & order: layanan, order, data alamat customer.
4. Kurir & tracking: job assignment, lokasi live, Google Maps UI.
5. Pembayaran: Xendit QRIS + expiry 1 jam + scheduler pengembalian.
6. Cash flow + refund.
7. Chat AI.
8. Notifikasi & audit log.
9. Hardening keamanan + uji beban + deploy.

## 15. Non-Functional

- Mobile-first (customer & kurir mayoritas HP).
- Response API < 300ms (p95) untuk endpoint order.
- Uptime target 99.5%.
- Bahasa UI: Indonesia.
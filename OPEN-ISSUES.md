# OPEN ISSUES & DAFTAR KEPUTUSAN TERTUNDA (LAUNDRYKU)

Dokumen ini merangkum seluruh catatan integrasi eksternal, keputusan bisnis tertunda, dan item TODO/FIXME yang dicatat selama pengembangan sistem **LaundryKu (Fase 1 hingga Fase 14)** sesuai spesifikasi PRD.

---

## 1. Integrasi Provider Eksternal (External Integrations)

### 1.1 WhatsApp Gateway Provider (FR-NOTIF & FR-AUTH) - Fonnte Terintegrasi
- **Status Saat Ini:** Fonnte WhatsApp Gateway telah terintegrasi penuh melalui `FontneService.php` dan `OtpService.php` (Fase 15B). Konfigurasi API Token (`FONNTE_API_TOKEN`) dan Base URL (`https://api.fonnte.com`) telah disediakan di `config/services.php` dan `.env`.
- **Tindakan Lanjutan:** 
  1. Daftarkan nomor device WhatsApp operasional di dashboard Fonnte (https://fonnte.com) dan scan QR code.
  2. Masukkan token resmi dari Fonnte Dashboard ke environment variable `FONNTE_API_TOKEN` pada file `.env`.
  3. Konfigurasi `FONNTE_WEBHOOK_TOKEN` jika menggunakan webhook interaktif dari Fonnte.

### 1.2 Production Email Provider (FR-NOTIF & PRD 8.14)
- **Status Saat Ini:** Menggunakan mail driver Laravel `log` / `smtp` standar.
- **Tindakan Lanjutan:** 
  1. Sambungkan transactional email provider (contoh: SendGrid, Mailgun, Postmark, atau AWS SES) untuk pengiriman email transaksional berskala tinggi.
  2. Konfigurasikan SPF, DKIM, dan DMARC pada domain pengirim (`noreply@laundryku.com`) agar email notifikasi tidak masuk ke folder spam.

### 1.3 Xendit Payment Gateway Live Production Credentials (FR-PAY & TEST-05)
- **Status Saat Ini:** Menggunakan sandbox credential dan mock webhook simulation (`/api/v1/orders/{id}/payment/simulate-success` dan header `x-callback-token`).
- **Tindakan Lanjutan:** 
  1. Lakukan KYC dan aktivasi akun Live Merchant di Xendit Dashboard.
  2. Masukkan `XENDIT_SECRET_KEY` dan `XENDIT_WEBHOOK_TOKEN` live ke environment produksi.
  3. Daftarkan URL webhook resmi dengan HTTPS (`https://api.laundryku.com/api/v1/webhooks/xendit`) pada dashboard Xendit.

### 1.4 Production AI Chatbot Provider (FR-AI & PRD 8.10)
- **Status Saat Ini:** Menggunakan FAQ fallback engine dan mock response engine cerdas pada `ChatbotController.php` dan `ChatbotService.php`.
- **Tindakan Lanjutan:** 
  1. Masukkan API Key live (misal Google Gemini API / OpenAI API) pada variabel `GEMINI_API_KEY` / `OPENAI_API_KEY`.
  2. Pasang rate-limiter khusus pada endpoint `/api/v1/chatbot/guest/ask` dan per-user chatbot quota untuk mengontrol biaya token LLM.

---

## 2. Keputusan Arsitektur & Optimasi Skala (Architecture & Optimization)

### 2.1 Live GPS Streaming Kurir via WebSocket (FR-COURIER & PRD 8.10)
- **Status Saat Ini:** Posisi kurir dan status pesanan dilacak melalui timeline status (`/api/v1/orders/{id}/tracking`) dan polling interval.
- **Tindakan Lanjutan:** 
  1. Aktifkan Laravel Reverb atau Pusher WebSocket pada aplikasi produksi.
  2. Implementasikan event `CourierLocationUpdated` untuk pembaruan marker peta secara live (sub-detik) di aplikasi pelanggan.

### 2.2 Integrasi Distance Matrix & Routing Real-Time (FR-MKT & FR-ORDER)
- **Status Saat Ini:** Perhitungan jarak radius layanan dan estimasi jarak menggunakan rumus matematis Haversine distance (akurasi garis lurus bumi).
- **Tindakan Lanjutan:** 
  1. Pasang provider routing jalan (contoh: Open Source Routing Machine / OSRM, Mapbox Matrix API, atau Google Maps Distance Matrix API) untuk perhitungan rute jalan raya dan estimasi waktu tempuh berdasarkan kondisi lalu lintas nyata.

---

## 3. Keputusan Bisnis yang Dipertahankan (Business Decisions Log)

### 3.1 Penanganan Komplain & Pengembalian Dana Manual (FR-COM / PRD 8.12)
- **Keputusan:** Sesuai arahan PRD Fase 11, sistem **TIDAK menyediakan endpoint refund otomatis**. 
- **Alasan:** Menghindari risiko fraud dan memberikan fleksibilitas kepada Admin Toko untuk menyelesaikan komplain secara kekeluargaan (misal: pemberian voucher cuci gratis, cuci ulang, atau transfer manual).
- **Status:** Dipertahankan. Catatan penyelesaian (`resolution_note`) wajib diisi oleh Admin Toko saat status komplain diubah ke `RESOLVED` atau `REJECTED`.

### 3.2 Isolasi Toko & Multi-Tenancy (PRD 8.13 & NFR-SEC-01)
- **Keputusan:** Akses data operasional toko (layanan, pesanan, kurir, catatan buku kas/ledger, dokumen legal) sepenuhnya diisolasi per ID pemilik toko (`owner_user_id`).
- **Status:** Diuji secara menyeluruh pada `Phase14Test.php` (`TEST-04`), mencegah Store Admin toko A mengakses atau memodifikasi data toko B.

---

## 4. Ringkasan Status Penyelesaian

| Komponen | Status Implementasi | Status Live Provider |
|---|---|---|
| Autentikasi & OTP | Selesai & Teruji | Menunggu WA/SMS Gateway Live |
| Marketplace & Katalog | Selesai & Teruji | Siap Produksi |
| Manajemen Pesanan & State Machine | Selesai & Teruji | Siap Produksi |
| Notifikasi Multi-Kanal | Selesai & Teruji | Menunggu Akun WA/Email Live |
| Pembayaran Xendit & Ledger | Selesai & Teruji | Menunggu Kredensial Xendit Live |
| Chat & CS Bot | Selesai & Teruji | Menunggu API Key LLM Live |
| Komplain & Rating | Selesai & Teruji | Siap Produksi |
| Super Admin Portal | Selesai & Teruji | Siap Produksi |
| Security Hardening & Isolation | Selesai & Teruji | Siap Produksi (HTTPS-Ready) |

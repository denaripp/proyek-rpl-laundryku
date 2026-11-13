# LaundryKu (LANDURYKU) — Marketplace Laundry Multi-Tenant & AI Assistant

[![CI/CD Pipeline](https://github.com/denaripp/proyek-rpl-laundryku/actions/workflows/ci.yml/badge.svg)](https://github.com/denaripp/proyek-rpl-laundryku/actions/workflows/ci.yml)
[![Automated Tests](https://img.shields.io/badge/Tests-631%20Passed%20%7C%200%20Failed-success?style=flat&logo=php)](https://github.com/denaripp/proyek-rpl-laundryku)
[![Security Score](https://img.shields.io/badge/Security-98%2F100%20(Grade%20A%2B)-emerald?style=flat&logo=shield)](https://github.com/denaripp/proyek-rpl-laundryku)
[![Next.js 16](https://img.shields.io/badge/Frontend-Next.js%2016%20Turbopack-black?style=flat&logo=next.js)](https://nextjs.org)
[![Laravel 12](https://img.shields.io/badge/Backend-Laravel%2012%20REST%20API-red?style=flat&logo=laravel)](https://laravel.com)

> **Proyek Akhir Mandiri Rekayasa Perangkat Lunak (TIKB2503021)**  
> **Program Studi S1 Teknik Informatika — STMIK IKMI Cirebon**  
> **Dosen Pengampu**: Yudhistira Arie Wijaya, M.Kom., CDS.

---

## 📌 Identitas Proyek & Pengembang

| Atribut | Keterangan |
| :--- | :--- |
| **Nama Pengembang** | **Muhammad Najwa Syarif** |
| **NIM** | **41257212** |
| **Kelas / Program Studi** | S1 Teknik Informatika (Rekayasa Perangkat Lunak) |
| **Kelompok** | `Laundry AI` |
| **Tema Proyek** | *Sistem Informasi Berbasis Web untuk Pemberdayaan UMKM Cerdas* |
| **Nama Aplikasi** | `LaundryKu (LANDURYKU)` |
| **Tautan Repositori GitHub** | [https://github.com/denaripp/proyek-rpl-laundryku](https://github.com/denaripp/proyek-rpl-laundryku) |

---

## 📖 Deskripsi Singkat Sistem

**LaundryKu** adalah platform marketplace laundry *on-demand* berbasis multi-tenant yang dirancang untuk memberdayakan UMKM binatu lokal melalui transformasi digital yang terstandarisasi, aman, dan cerdas. Dilengkapi dengan asisten AI percakapan real-time (**LANDY AI**) berbasis arsitektur hybrid 3-layer, sistem ini mengintegrasikan 4 peran pengguna independen (*Customer*, *Store Admin / Mitra*, *Kurir*, dan *Platform Super Admin*) dalam satu ekosistem terpadu.

Aplikasi dibangun dengan prinsip **Clean Code & SOLID**, mematuhi standar *Zero Trust & Least Privilege*, serta dilindungi oleh audit keamanan ketat (51 celah kerentanan berhasil diremediasi hingga meraih skor 98/100 Grade A+).

---

## 🏗️ Arsitektur Teknologi & Tech Stack

```
┌────────────────────────────────────────────────────────┐
│             FRONTEND: Next.js 16.3.5 (Turbopack)       │
│  - React 19 + Tailwind CSS v4 + TypeScript             │
│  - 45 Pages (Static & Dynamic SSR/BFF)                 │
│  - Real-Time AI Chatbot Widget (AiChatWidget.tsx)      │
└───────────────────────────┬────────────────────────────┘
                            │ REST API / JSON / SSE
┌───────────────────────────▼────────────────────────────┐
│              BACKEND: Laravel 12 (PHP 8.3)             │
│  - Multi-Guard Scoped Authentication (Sanctum + TOTP)  │
│  - Service Layer Pattern (Thin Controllers)            │
│  - State Machine Order Transitions & Haversine Dispatch│
│  - LANDY AI: 3-Layer Hybrid Intent Router & Tools      │
└───────────────────────────┬────────────────────────────┘
                            │ SQL Queries / Eloquent
┌───────────────────────────▼────────────────────────────┐
│              DATABASE: MySQL 8.0 (58 Tabel)            │
│  - Strict Tenant Isolation & Audit Trail Immutable     │
│  - Spatial/Haversine Coordinate Geocoding Indexes      │
└────────────────────────────────────────────────────────┘
```

- **Frontend**: Next.js 16.3.5 (App Router, Turbopack, React 19, Tailwind CSS v4, Lucide Icons).
- **Backend**: Laravel 12 (PHP 8.3), REST API endpoints, Service Layer, Form Requests, Policy Gates.
- **Database**: MySQL 8.0 (58 tabel relasional, InnoDB, isolasi multi-tenant).
- **AI Engine**: LANDY 3-Layer (Intent Router + Function Calling Tool Registry + OpenRouter LLM Fallback).
- **Payment Gateway**: Xendit (Virtual Account, E-Wallet, QRIS, COD) dengan verifikasi tanda tangan webhook idempoten.
- **CI/CD**: GitHub Actions (`.github/workflows/ci.yml`) dengan runner Ubuntu, container MySQL 8.0, dan matrix PHP 8.3 & Node.js 20.

---

## 🛡️ Multi-Guard Authentication & Isolasi Peran Keras

Sistem mengimplementasikan **Strict Role Isolation** dengan 4 gerbang masuk terpisah tanpa kemungkinan eskalasi privilese horizontal maupun vertikal:

| Role | Pintu Masuk URL | Guard | Eloquent Model | Default Landing | Autentikasi Tambahan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Customer** | `/login`, `/register` | `web` | `App\Models\CustomerUser` | `/orders` | OTP via WhatsApp / Email |
| **Store Admin (Mitra)** | `/mitra/login`, `/mitra/register` | `mitra` | `App\Models\MitraUser` | `/mitra/dashboard` | KYC & Persetujuan Dokumen |
| **Courier (Kurir)** | `/kurir/login` | `kurir` | `App\Models\CourierUser` | `/kurir/dashboard` | Rekrutmen Toko & Verifikasi Kurir |
| **Super Admin** | `/admin/login` | `admin` | `App\Models\AdminUser` | `/admin/dashboard` | **Wajib 2FA TOTP (Google Authenticator)** |

---

## 🎬 Tautan Dokumentasi & Video Presentasi

Sesuai ketentuan Panduan Proyek Akhir Mandiri RPL OBE:

- **Tahap 1 (PRD & Spesifikasi Kebutuhan)**:
  - Berkas: [`project akhir/01-tahap-1-prd/`](file:///c:/laragon/www/laundryku/project%20akhir/01-tahap-1-prd/)
  - Outline Video Progres 1 (3–5 Menit): [`project akhir/01-tahap-1-prd/video-progres-1-outline.md`](file:///c:/laragon/www/laundryku/project%20akhir/01-tahap-1-prd/video-progres-1-outline.md)
  - Tautan Video Progres 1 (YouTube/Drive): `https://youtu.be/placeholder-progres-1`
- **Tahap 2 (SDD, UML, ERD & UI Design)**:
  - Berkas: [`project akhir/02-tahap-2-sdd-uml-erd-figma/`](file:///c:/laragon/www/laundryku/project%20akhir/02-tahap-2-sdd-uml-erd-figma/)
  - Outline Video Progres 2 (5–7 Menit): [`project akhir/02-tahap-2-sdd-uml-erd-figma/video-progres-2-outline.md`](file:///c:/laragon/www/laundryku/project%20akhir/02-tahap-2-sdd-uml-erd-figma/video-progres-2-outline.md)
  - Tautan Video Progres 2 (YouTube/Drive): `https://youtu.be/placeholder-progres-2`
- **Tahap 3 & 4 (Implementasi, Pengujian, CI/CD & Final Demo)**:
  - Berkas: [`project akhir/03-tahap-3-implementasi-pengujian-ci-cd/`](file:///c:/laragon/www/laundryku/project%20akhir/03-tahap-3-implementasi-pengujian-ci-cd/)
  - Tautan Video Final Demo & Testing (8–12 Menit): `https://youtu.be/placeholder-final-demo`

---

## 🛠️ Panduan Instalasi & Menjalankan Aplikasi

### 1. Prasyarat Sistem
- **PHP**: versi `8.3.x` (ekstensi: `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `curl`, `openssl`).
- **Composer**: versi `2.x`.
- **Node.js**: versi `20.x` LTS atau lebih tinggi (`npm` versi `10.x`).
- **MySQL Server**: versi `8.0.x`.
- **Git**: versi `2.x`.

---

### 2. Setup Backend (Laravel 12)

1. Clone repositori ke mesin lokal:
   ```bash
   git clone https://github.com/denaripp/proyek-rpl-laundryku.git
   cd proyek-rpl-laundryku
   ```

2. Pasang dependensi Composer:
   ```bash
   composer install
   ```

3. Salin konfigurasi environment dan generate application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Sesuaikan konfigurasi database pada `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=laundryku
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Jalankan migrasi database dan pengisian data awal (seeder):
   ```bash
   php artisan migrate --seed
   ```

6. Jalankan server development Laravel:
   ```bash
   php artisan serve --port=8000
   ```
   *Backend REST API aktif pada: `http://127.0.0.1:8000`*

---

### 3. Setup Frontend (Next.js 16)

1. Masuk ke direktori `frontend`:
   ```bash
   cd frontend
   ```

2. Pasang dependensi Node.js:
   ```bash
   npm install
   ```

3. Buat file `.env.local` dari contoh:
   ```bash
   cp .env.example .env.local
   ```

4. Jalankan development server Next.js:
   ```bash
   npm run dev
   ```
   *Frontend aplikasi web aktif pada: `http://localhost:3000`*

---

## 🧪 Menjalankan Automated Testing

Proyek ini memiliki **631 automated unit & feature tests** yang mencakup 100% alur kerja kritis bisnis.

### 1. Menjalankan Seluruh Test Suite Backend
```bash
# Pastikan database testing 'laundryku_test' tersedia di MySQL
php artisan test
```
*Hasil: `Tests: 631 passed (3724 assertions) — 0 failed`*

### 2. Menjalankan Pengujian Spesifik Modul
```bash
# Uji Asisten AI & Security Guardrail
php artisan test tests/Feature/AiAssistantTest.php
php artisan test tests/Feature/AiRoleFunctionCallingTest.php

# Uji Transaksi & Transisi Status Pesanan
php artisan test tests/Feature/OrderServiceTest.php

# Uji Pembayaran & Idempotensi Webhook
php artisan test tests/Feature/PaymentTest.php

# Uji Isolasi Multi-Tenant & RBAC
php artisan test tests/Feature/RbacTest.php
php artisan test tests/Feature/Integration/TenantIsolationTest.php
```

### 3. Uji Build & Typecheck Frontend
```bash
cd frontend
npm run build
```
*Hasil: `✓ Generating static pages (45/45) — 0 TypeScript Error`*

---

## 📄 Lisensi & Hak Cipta

Proyek ini dikembangkan secara mandiri untuk memenuhi luaran akademis Mata Kuliah **Rekayasa Perangkat Lunak (TIKB2503021)** di **STMIK IKMI Cirebon**. Seluruh kode sumber dilindungi di bawah lisensi terbuka [MIT](LICENSE).

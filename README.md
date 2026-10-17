# LANDURYKU (Laundryku) — Marketplace Laundry Multi-Tenant & AI Assistant

Aplikasi marketplace laundry on-demand multi-tenant dengan AI Chat Assistant, dibangun menggunakan **Laravel 12**, **Blade + Bootstrap 5 + Vanilla JS**, **MySQL 8**, dan **Redis**.

---

## 🚀 Tech Stack & Environment

- **Framework**: Laravel 12 (MVC, Service Layer, Form Requests, Policies, Jobs, Events)
- **PHP**: `8.3.30` (Laragon: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`)
- **Database**: MySQL 8.0 (Laragon: `C:/laragon/bin/mysql/mysql-8.0.30-winx64`)
- **Cache & Session**: Redis / Database
- **Queue**: Database / Redis (`QUEUE_CONNECTION=database` untuk dev)
- **Frontend**: Blade Components + Bootstrap 5.3 + Vanilla JS (No SPA framework)
- **Brand Identity**:
  - Primary Navy: `#1A3A5C`
  - Secondary Blue: `#4A90D9`
  - Accent Green: `#2ECC71`
  - Light Background: `#F5F7FA`
  - Dark Charcoal: `#2C3E50`

---

## 🛡️ Multi-Guard Scoped Authentication & Role Isolation

Sistem menerapkan **Strict Role Isolation** dengan 4 pintu masuk terpisah dan independent guards:

| Role | Pintu Masuk URL | Guard | Eloquent Provider Model | Default Landing Route | Catatan Registrasi |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Customer** | `/login`, `/register` | `web` | `App\Models\CustomerUser` | `/orders` | Publik terbuka |
| **Store Admin (Mitra)** | `/mitra/login`, `/mitra/register` | `mitra` | `App\Models\MitraUser` | `/mitra/dashboard` | Pendaftaran mitra baru |
| **Courier (Kurir)** | `/kurir/login` | `kurir` | `App\Models\CourierUser` | `/kurir/dashboard` | Diundang/dibuat oleh Mitra/Admin |
| **Super Admin** | `/admin/login` | `admin` | `App\Models\AdminUser` | `/admin/dashboard` | Wajib 2FA TOTP (Google Authenticator) |

> ⚠️ **Aturan Isolasi Keras**:
> - Fitur switch-role **DILARANG KERAS**. Satu sesi login hanya berlaku untuk satu peran.
> - Setiap layout dashboard (`layouts/app`, `layouts/mitra`, `layouts/kurir`, `layouts/dashboard`) sepenuhnya terisolasi tanpa tautan silang antar-peran.
> - Upaya login silang pintu (misal kurir mencoba login di `/admin/login` atau admin mencoba login di `/login`) ditolak secara otomatis dengan pesan error scoped.

---

## 🛠️ Setup & Instalasi Lokal

### 1. Prasyarat Laragon
Pastikan MySQL dan PHP 8.3 berjalan:
```powershell
# Jalankan MySQL daemon jika belum aktif
C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqld.exe --defaults-file=C:/laragon/bin/mysql/mysql-8.0.30-winx64/my.ini --console
```

### 2. Environment Configuration
Salin konfigurasi environment:
```powershell
cp .env.example .env
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan key:generate
```

Pastikan variabel database pada `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laundryku
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Migrasi Database & Seeding
Jalankan migrasi fondasi dan seeder:
```powershell
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan migrate
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan db:seed
```

Seeder akan membuat akun default:
- **Super Admin**:
  - Email: `admin@laundryku.com`
  - Password: `Password123!`
  - TOTP Secret: `JBSWY3DPEHPK3PXP` (gunakan Google Authenticator / Authenticator app)
- **Tabel Settings**: seeded dengan konfigurasi sistem dasar dan tema warna.

### 4. Menjalankan Server Development
```powershell
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan serve
```
Aplikasi dapat diakses di `http://127.0.0.1:8000` atau melalui vhost Laragon `http://laundryku.test`.

---

## 🧪 Menjalankan Automated Tests

Seluruh fitur dilengkapi dengan Feature Test yang ketat:
```powershell
# Jalankan seluruh test suite
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan test

# Jalankan khusus test fondasi auth dan isolasi peran (Fase 1)
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan test tests/Feature/Phase1FoundationAuthTest.php
C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan test tests/Feature/AuthTest.php
```

---

## 📂 Struktur Modul Fase 1

- `database/migrations/2026_10_17_000001_phase1_foundation_schema_update.php`: Penambahan kolom `role`, indeks `(email, role)`, tabel `settings`, dan tabel `cod_collections`.
- `app/Models/User.php`: Base User model dengan role constants dan relasi.
- `app/Models/{CustomerUser, MitraUser, CourierUser, AdminUser}.php`: Scoped Eloquent models untuk masing-masing guard provider.
- `app/Models/Setting.php`: Key-value configuration engine dengan Redis caching terotomasi.
- `app/Models/CodCollection.php`: Ledger pencatatan COD tunai kurir.
- `app/Http/Controllers/Auth/`:
  - `AuthController.php` (Customer door `/login`, `/register`)
  - `MitraAuthController.php` (Mitra door `/mitra/login`, `/mitra/register`)
  - `CourierAuthController.php` (Kurir door `/kurir/login`)
  - `AdminAuthController.php` (Admin door `/admin/login`, `/admin/2fa`)
- `resources/views/layouts/`:
  - `app.blade.php` (Customer layout)
  - `mitra.blade.php` (Mitra dashboard layout)
  - `kurir.blade.php` (Kurir mobile-first layout)
  - `dashboard.blade.php` (Super Admin layout)

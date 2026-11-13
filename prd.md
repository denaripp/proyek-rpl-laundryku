# PRODUCT REQUIREMENTS DOCUMENT (PRD) & SOFTWARE REQUIREMENTS SPECIFICATION (SRS)
# LaundryKu — Marketplace Laundry Multi-Tenant & Asisten AI Hibrida

---

## Kontrol Dokumen & Identitas Akademik

| Atribut | Keterangan |
| :--- | :--- |
| **Nama Produk / Sistem** | **LaundryKu** (Marketplace Layanan Laundry On-Demand Multi-Tenant + LANDY AI Assistant) |
| **Versi Dokumen** | **3.0** (Sinkronisasi Penuh dengan Implementasi Aktual & Standar IEEE 830 / ISO 29148) |
| **Tanggal Pembaruan** | 12 November 2026 |
| **Mata Kuliah** | Rekayasa Perangkat Lunak (`TIKB2503021` — Semester 3, 4 SKS) |
| **Dosen Pengampu** | Yudhistira Arie Wijaya, M.Kom., CDS. |
| **Institusi** | STMIK IKMI Cirebon |
| **Kelompok** | **Laundry AI** |
| **Tema Kelompok** | **Laundryku** |
| **Kontak Dukungan Resmi (CS)** | `cs@laundryku.test` |
| **Standar Acuan** | IEEE Std 830-1998 & ISO/IEC/IEEE 29148:2018 |

### Riwayat Perubahan Dokumen

| Versi | Tanggal | Ringkasan Perubahan |
| :---: | :--- | :--- |
| `1.0` | 29 Agustus 2026 | Draf spesifikasi produk awal (masih memakai arsitektur monolith server-rendered, OAuth eksternal, dan placeholder bisnis terbuka). |
| `2.0` | 3 September 2026 | Eksperimen draf ringkas (`prd-v2.md`) dengan usulan database alternatif; tidak pernah dijadikan standar implementasi utama. |
| **`3.0`** | **12 November 2026** | **Perombakan penuh berstandar IEEE 830 / ISO 29148 yang disinkronkan 100% dengan implementasi nyata aplikasi (Next.js BFF + Laravel 12 Sanctum API + MySQL 8, registrasi OTP WhatsApp/Email, Leaflet + Nominatim OSM, 19 status order, Xendit + COD, LANDY AI 3-Layer + Model Switcher, User Stories + Acceptance Criteria, NFR terukur, dan Traceability Matrix).** |

---

## Daftar Isi

1. [Bab 1 — Pendahuluan](#bab-1--pendahuluan)
2. [Bab 2 — Deskripsi Umum Sistem](#bab-2--deskripsi-umum-sistem)
3. [Bab 3 — Kebutuhan Fungsional, User Stories & Acceptance Criteria](#bab-3--kebutuhan-fungsional-user-stories--acceptance-criteria)
4. [Bab 4 — Spesifikasi Fitur AI Utama: LANDY Hybrid Assistant](#bab-4--spesifikasi-fitur-ai-utama-landy-hybrid-assistant)
5. [Bab 5 — Kebutuhan Non-Fungsional (Terukur)](#bab-5--kebutuhan-non-fungsional-terukur)
6. [Bab 6 — Sistem Desain UI/UX, Peta Route & Model Data Sistem](#bab-6--sistem-desain-uiux-peta-route--model-data-sistem)
7. [Bab 7 — Matriks Keterlacakan & Daftar Utang Teknis Terkendali](#bab-7--matriks-keterlacakan--daftar-utang-teknis-terkendali)

---

## Bab 1 — Pendahuluan

### 1.1 Tujuan Dokumen

Dokumen Product Requirements Document (PRD) dan Software Requirements Specification (SRS) ini mendefinisikan secara lengkap dan terukur kebutuhan fungsional, kebutuhan non-fungsional, arsitektur integrasi, serta spesifikasi kecerdasan buatan (AI) sistem **LaundryKu**. Dokumen ini berfungsi sebagai:
1. **Sumber Kebenaran Tunggal (*Single Source of Truth*)** bagi tim pengembang **Laundry AI** dalam menjaga kesesuaian kode backend, frontend, pengujian otomatis, dan antarmuka pengguna.
2. **Dokumen Spesifikasi Tahap 1** proyek akhir mata kuliah Rekayasa Perangkat Lunak (`TIKB2503021`) sebagai dasar perancangan sistem, pengujian (*verification & validation*), serta presentasi Video Progres 1.

### 1.2 Latar Belakang Masalah

Industri jasa binatu (*laundry*) UMKM merupakan kebutuhan harian masyarakat urban, mahasiswa, dan keluarga pekerja, tetapi operasionalnya masih menghadapi lima masalah utama:
1. **Ketidakpastian Berat & Harga Awal:** Pelanggan umumnya tidak mengetahui berat pasti cucian saat memesan jemput, sehingga estimasi awal dan tagihan final setelah ditimbang di toko sering memicu perselisihan bila tidak dicatat terpisah dan disetujui transparan.
2. **Minimnya Pelacakan Status Terperinci:** Pelanggan tidak mendapat visibilitas tahap pengerjaan cucian (penjemputan kurir, timbang, cuci, kering, setrika, pengantaran) secara terstruktur.
3. **Pengelolaan Mitra & Kurir Terfragmentasi:** Banyak gerai laundry lokal tidak memiliki sistem pembukuan komisi platform, manajemen kurir toko, pengaturan aturan diskon berat/promo, maupun mekanisme onboarding terverifikasi.
4. **Risiko Keamanan Akun Lintas Peran:** Pada platform multi-peran (*Customer, Mitra Toko, Kurir, Super Admin*), sesi login yang longgar atau fitur ganti peran dalam satu sesi membuka celah eskalasi hak akses (*privilege escalation*) dan kebocoran data antar-toko (*tenant leak*).
5. **Beban Layanan Pelanggan Berulang & Biaya Token LLM:** Pertanyaan rutin pengguna (*"cara pesan"*, *"cek status cucian ke berapa"*, *"total pengeluaran bulan ini"*, *"aturan batas COD"*) membebani admin secara manual, sementara penggunaan model bahasa besar (*LLM*) untuk setiap pesan tanpa penyaringan menyebabkan latensi tinggi dan pemborosan kuota token API.

### 1.3 Relevansi dengan Tema Kelompok (`Laundryku`)

Tema kelompok **"Laundryku"** berfokus pada transformasi digital ekosistem layanan binatu lokal menjadi **marketplace multi-tenant terpadu yang aman, transparan, dan dibantu asisten AI cerdas**. Keterkaitan spesifik fitur sistem dengan tema **Laundryku** meliputi:
- **Marketplace Multi-Tenant Terisolasi:** Menghubungkan pelanggan dengan banyak mitra toko terverifikasi melalui pencarian geografis berbasis koordinat & pemeringkatan kualitas layanan.
- **Transparansi Transaksi Berat & Pembayaran:** Menyimpan berat estimasi vs berat aktual secara terpisah, mendukung pembayaran digital otomatis (Xendit QRIS/Invoice) maupun tunai terverifikasi (COD dengan batas risiko), serta pembukuan komisi dan periode settlement yang diaudit.
- **Integrasi AI Berdampak Nyata (LANDY):** Menyediakan asisten AI **read-only** lintas empat peran dengan **arsitektur routing 3 lapisan** (*3-Layer Intent Routing*) yang menyelesaikan panduan operasional serta kueri data terstruktur pada **0 token LLM di Layer 1**, menjaga privasi melalui penyamaran data pribadi (*PII masking*), dan menekan biaya operasional melalui pemilihan model dinamis (*Free Tier* vs *Commercial Tier*) oleh Super Admin.

### 1.4 Ruang Lingkup Produk

#### 1.4.1 Cakupan Sistem Aktif

| Domain | Cakupan Fungsional |
| :--- | :--- |
| **Portal Pelanggan (`Customer`)** | Landing page, registrasi & verifikasi OTP (WhatsApp/Email) + login password, profil & manajemen alamat berbasis GPS/Nominatim, pencarian toko & peta interaktif Leaflet, pembuatan pesanan multi-layanan, pelacakan status 19-tahap, pembayaran Xendit/COD, obrolan langsung dengan toko (*Human Chat*), notifikasi multi-kanal, ulasan/rating, pengajuan komplain, serta pendaftaran mitra toko/kurir. |
| **Portal Mitra Toko (`Store Admin`)** | Login terisolasi (+ 2FA opsional), dashboard metrik toko, pengelolaan pesanan masuk & penimbangan berat aktual, manajemen layanan & harga dasar, pengaturan aturan diskon/promo (`/store/pricing`), manajemen kurir gerai, buku kas/ledger & rekening pencairan dana, laporan analitik periode & ekspor CSV (`/store/reports`), penanganan komplain pelanggan, balas Human Chat, serta pengaturan profil & jam buka gerai. |
| **Portal Kurir (`Courier`)** | Login terisolasi kurir terverifikasi, ringkasan tugas & penghasilan, klaim tugas jemput/antar tersedia, pembaruan status perjalanan 7-tahap, pencatatan koordinat lokasi saat tugas aktif, serta konfirmasi penagihan tunai COD. |
| **Portal Super Admin (`Platform`)** | Login terisolasi dengan **wajib 2FA TOTP**, 9 modul tata kelola: Dashboard metrik lintas platform, monitoring pesanan, monitoring transaksi pembayaran, persetujuan/penolakan pendaftaran toko, manajemen status pengguna, CRUD kategori layanan, konfigurasi komisi platform, manajemen model AI dinamis (`/admin/settings/ai`), serta audit log aktivitas sensitif. |
| **LANDY AI Assistant** | Layanan chat AI read-only untuk tamu (FAQ) dan 4 peran terotentikasi menggunakan arsitektur 3-layer (`AiIntentRouter`), 21 topik basis pengetahuan prosedural, 33 definisi tool terotorisasi, dan guardrail anti-halusinasi/mutasi. |

#### 1.4.2 Batasan di Luar Cakupan (*Out of Scope*) Rilis Saat Ini

1. Aplikasi mobile native terpisah (Android/iOS APK/IPA); antarmuka saat ini berbasis web responsif *mobile-first*.
2. Transfer dana otomatis ke rekening mitra toko (*auto-payout* bank real-time); pencairan dana dicatat sebagai periode settlement teraudit untuk verifikasi keuangan manual.
3. Pengembalian dana (*refund*) otomatis ke kartu/e-wallet pelanggan dari portal mitra; penyelesaian komplain diproses melalui verifikasi manusia dengan `resolution_note` dan pencatatan refund terkontrol.
4. Kalkulasi jarak rute jalan raya berbayar berbasis lalu lintas langsung; pencarian jarak toko menggunakan formula matematis **Haversine**.
5. Streaming lokasi GPS sub-detik lewat server WebSocket aktif; pembaruan posisi dan status antarmuka menggunakan mekanisme *HTTP polling* periodik (`15 detik`).

### 1.5 Definisi, Akronim, & Istilah Teknis

| Istilah / Akronim | Definisi dalam Sistem LaundryKu |
| :--- | :--- |
| **BFF (*Backend-for-Frontend*)** | Lapisan proxy server Next.js (`/api/proxy/[...path]`, `/api/auth/[portal]/[action]`) yang meneruskan permintaan API ke Laravel dan menyimpan token Sanctum dalam cookie `httpOnly` terisolasi per portal. |
| **Sanctum** | Paket autentikasi API Laravel (`laravel/sanctum`) berbasis token akses personal (`personal_access_tokens`) dengan pengecekan hak akses (*ability*) secara *real-time*. |
| **OTP (*One-Time Password*)** | Kode verifikasi numerik 6 digit sekali pakai yang dikirim lewat WhatsApp (gateway Fonnte) atau Email (SMTP) dengan masa berlaku 5 menit dan tersimpan dalam bentuk hash SHA-256. |
| **TOTP 2FA** | *Time-based One-Time Password* (`pragmarx/google2fa-laravel`) menggunakan aplikasi autentikator (mis. Google Authenticator), diwajibkan pada login Super Admin. |
| **RBAC (*Role-Based Access Control*)** | Pengendalian akses berbasis peran dan izin yang ditegakkan melalui middleware, Sanctum token abilities, dan Laravel Policies (*Least Privilege*). |
| **Strict Role Isolation** | Kebijakan keamanan yang memisahkan 4 pintu masuk login dan cookie token antar-peran serta menonaktifkan fitur perpindahan peran dalam satu sesi (`/switch-role` menghasilkan HTTP 404). |
| **QRIS & Invoice Xendit** | Kanal pembayaran digital melalui gateway Xendit berupa kode QR dinamis (`qr_codes`, masa berlaku 2 menit) maupun tautan invoice multifungsi (`v2/invoices`, kedaluwarsa 60 menit). |
| **COD (*Cash on Delivery*)** | Pembayaran tunai di tempat saat penjemputan/pengantaran dengan batas nominal default `Rp500.000` yang dilacak di tabel `cod_collections`. |
| **Nominatim OSM** | Layanan *geocoding* dan *reverse geocoding* berbasis peta terbuka OpenStreetMap dengan pembatasan 1 request/detik dan caching koordinat. |
| **PII Masking** | Penyensoran data pribadi (email dan nomor telepon) sebelum dicatat ke log AI maupun saat dikembalikan ke keluaran alat analitik AI. |
| **Hold-Out Dataset** | Kumpulan 165 kalimat uji independen (`storage/holdout_dataset.php`) yang tidak pernah dimasukkan ke aturan latih, dipakai mengukur akurasi objektif engine AI LANDY. |

### 1.6 Dokumen Referensi

1. IEEE Std 830-1998 — *IEEE Recommended Practice for Software Requirements Specifications*.
2. ISO/IEC/IEEE 29148:2018 — *Systems and software engineering — Life cycle processes — Requirements engineering*.
3. Modul Panduan Proyek Akhir Mata Kuliah Rekayasa Perangkat Lunak (`TIKB2503021`), STMIK IKMI Cirebon.
4. Dokumen internal repositori LaundryKu: [AGENTS.md](file:///c:/laragon/www/laundryku/AGENTS.md), [DESIGN-RULES.md](file:///c:/laragon/www/laundryku/DESIGN-RULES.md), [DESIGN.md](file:///c:/laragon/www/laundryku/DESIGN.md), [OPEN-ISSUES.md](file:///c:/laragon/www/laundryku/OPEN-ISSUES.md), dan [docs/technical-notes.md](file:///c:/laragon/www/laundryku/docs/technical-notes.md).

---

## Bab 2 — Deskripsi Umum Sistem

### 2.1 Perspektif Produk & Arsitektur Sistem Nyata

LaundryKu beroperasi menggunakan arsitektur terpisah (*decoupled hybrid architecture*) antara lapisan presentasi antarmuka modern **Next.js (`16.3.5`) + React (`19.2.8`) + Tailwind CSS v4** dengan lapisan layanan bisnis dan REST API **Laravel 12 (PHP `8.3.30`)** serta basis data **MySQL 8.0**. Komunikasi dari browser pengguna ke backend API tidak mengekspos bearer token ke JavaScript klien secara langsung, melainkan melewati **Next.js BFF Proxy** yang menyuntikkan header `Authorization: Bearer` dari cookie `httpOnly` khusus per portal.

```text
+------------------------------------------------------------------------------+
|                        BROWSER PENGGUNA (Mobile / Desktop)                   |
|   Next.js 16.3.5 App Router + React 19.2.8 + Tailwind v4 + Leaflet 1.9.4    |
+--------------------------------------+---------------------------------------+
                                       | HTTP + Cookie httpOnly per Portal
                                       v
+------------------------------------------------------------------------------+
|                      NEXT.JS BFF LAYER (frontend/app/api)                    |
|  - /api/auth/[portal]/[action] (login, verify-2fa, register, logout, me)     |
|  - /api/proxy/[...path]        (CSRF Origin check + token injection)         |
|  - /api/chat                   (forward guest & authenticated AI messages)   |
+--------------------------------------+---------------------------------------+
                                       | Server-to-Server REST (Bearer Sanctum)
                                       v
+------------------------------------------------------------------------------+
|                   LARAVEL 12 REST API BACKEND (PHP 8.3.30)                   |
|  Auth Guards (web, mitra, kurir, admin) + Laravel Sanctum v4 + Policies      |
|  Controllers (/api/v1/*) -> Business Services -> Models -> MySQL 8 (58 Tabel)|
|                                       |                                      |
|                     Background Queue (QUEUE_CONNECTION=database)             |
+----+-------------------+--------------------+-------------------+------------+
     |                   |                    |                   |
     v                   v                    v                   v
+---------+       +--------------+      +-----------+      +---------------+
| Fonnte  |       | SMTP Mailer  |      |  Xendit   |      |  OpenRouter   |
|  (WA)   |       |  (OTP/Notif) |      | (QRIS/VA) |      |  (LLM API)    |
+---------+       +--------------+      +-----------+      +---------------+
                                                                  ^
                                                                  |
                                                       +----------+----------+
                                                       | Nominatim OSM API   |
                                                       | (Forward/Reverse Geo)|
                                                       +---------------------+
```

*(Catatan teknis repo: beberapa view Blade server-rendered peninggalan fase awal di `resources/views/` tetap tersedia untuk utilitas internal seperti widget `/api/ai/chat`, namun antarmuka operasional utama seluruh 4 portal berjalan di Next.js App Router pada direktori `frontend/app/`).*

### 2.2 Aktor, Isolasi Guard & Matriks Peran (RBAC)

Sistem memisahkan pintu masuk, guard autentikasi (`config/auth.php`), model provider Eloquent, serta cookie BFF (`frontend/lib/bff.ts`) secara ketat agar satu sesi login hanya berlaku untuk satu peran:

| Aktor / Peran | Portal URL Masuk | Auth Guard & Provider | Nama Cookie Token BFF (`httpOnly`) | Kemampuan & Ruang Lingkup Utama |
| :--- | :--- | :--- | :--- | :--- |
| **Customer** (`customer`) | `/auth` | `web` (`CustomerUser`) | `laundryku_customer_token` | Daftar/login OTP & password, kelola alamat GPS, cari toko, buat order, bayar Xendit/COD, tracking, Human Chat, review, komplain, ajukan upgrade mitra/kurir, chat LANDY. |
| **Mitra / Store Admin** (`store_admin`) | `/mitra/login`, `/mitra/register` | `mitra` (`MitraUser`) | `laundryku_mitra_token` (+ `laundryku_mitra_temp_token` jika 2FA) | Kelola pesanan toko sendiri (*tenant-isolated*), input berat riil, kelola layanan & aturan harga/diskon, rekrut/kelola kurir toko, ledger, rekening payout, laporan CSV, komplain, balas Human Chat, chat LANDY toko. |
| **Courier / Kurir** (`courier`) | `/courier/login` | `kurir` (`CourierUser`) | `laundryku_courier_token` | Wajib terverifikasi admin/mitra (`is_verified`), lihat & klaim tugas pickup/delivery, update 7-status tugas, konfirmasi penerimaan COD tunai, chat LANDY kurir. |
| **Super Admin** (`super_admin`) | `/admin/login` | `admin` (`AdminUser`) | `laundryku_admin_token` + `laundryku_admin_temp_token` (wajib 2FA) | Login email+password + **wajib 2FA TOTP**, pantau metrik platform, kelola pesanan & pembayaran global, approve/reject toko, aktif/nonaktif pengguna, CRUD kategori layanan, atur komisi platform, kelola model AI (`/admin/settings/ai`), audit log, chat LANDY platform. |

**Aturan Isolasi Keras & Catatan Dual-Role (Utang Teknis `T011`):**
1. Fitur pindah peran dalam satu sesi dinonaktifkan penuh; rute `/switch-role` selalu mengembalikan HTTP 404.
2. Pada lapisan database saat ini, otorisasi membaca kolom datar `users.role` (`customer`, `store_admin`, `courier`, `super_admin`) dan tabel relasional RBAC (`roles`, `permissions`, `user_roles`, `role_permissions`) yang mendukung 6 peran rinci (`customer`, `courier`, `store_owner`, `store_staff`, `platform_admin`, `platform_owner`). Metode `User::tokenCan()` melakukan pengecekan peran *real-time* terhadap database setiap request Sanctum agar perubahan status peran langsung efektif.

### 2.3 Alur Bisnis End-to-End

1. **Pendaftaran & Alamat:** Customer mendaftar di `/auth` memakai nama, nomor WhatsApp atau email, serta kata sandi minimal 8 karakter -> menerima OTP 6 digit -> akun aktif setelah verifikasi -> melengkapi alamat penjemputan via deteksi GPS browser (`Nominatim`) atau input manual.
2. **Pencarian & Pemesanan:** Customer membuka `/stores`, menyaring toko berdasarkan jarak/layanan/harga/rating, memilih layanan di `/stores/{id}`, mengisi estimasi berat dan catatan di `/stores/{id}/order`, lalu sistem menghitung estimasi tagihan di server dan membuat order berstatus `created`.
3. **Penjemputan & Penimbangan:** Kurir menerima/mengklaim tugas (`assigned -> accepted -> arrived -> picked_up`) hingga cucian tiba di toko (`received_by_store`). Mitra toko menimbang cucian riil (`actual_weight`); jika selisih harga melampaui ambang batas (`Rp50.000`), pesanan melewati persetujuan harga pelanggan (`awaiting_price_approval`) sebelum masuk tahap penagihan (`payment_required`).
4. **Pembayaran (Xendit / COD):** Customer memilih pembayaran digital Xendit (Invoice 60 menit / Dynamic QRIS 2 menit) yang terverifikasi otomatis oleh webhook idempoten, atau memilih COD tunai jika nominal `<= Rp500.000`.
5. **Pencucian, Pengantaran & Penutupan:** Toko memproses cucian (`processing -> ready_for_delivery`), kurir melakukan pengantaran (`courier_assigned_for_delivery -> out_for_delivery -> delivered`), dan setelah pelanggan konfirmasi atau proses tuntas status menjadi `completed`. Pelanggan dapat memberi ulasan (`1–5` bintang) atau mengajukan komplain bila terjadi kendala.
6. **Komisi & Settlement:** Sistem memotong komisi platform (default `10%`, dapat dikonfigurasi Super Admin) dan membentuk periode settlement untuk order `completed` yang sudah melewati jendela sengketa (`7 hari`).

### 2.4 Lingkungan Operasi, Batasan Teknis & Ketergantungan Eksternal

| Komponen | Spesifikasi Teknis Wajib / Nyata | Batasan & Keterangan Operasional |
| :--- | :--- | :--- |
| **Runtime Backend** | PHP `8.3.30` + Laravel `^12.0` | Dikunci pada PHP 8.3 sesuai keputusan kompatibilitas environment Laragon (tidak dinaikkan ke 8.4). |
| **Database** | MySQL `8.0` (`58` tabel terverifikasi dari `74` migrasi) | Guard pengaman di `TestCase.php` & `composer test` mencegah test merusak database produksi `laundryku`. |
| **Antrian (*Queue*)** | Driver `database` (`jobs`, `job_batches`, `failed_jobs`) | Penggunaan Redis tidak diaktifkan (`QUEUE_CONNECTION=database` untuk pekerja latar notifikasi). |
| **Pengiriman OTP & WA** | Fonnte WhatsApp API (`FONNTE_API_TOKEN`) + SMTP (`MAIL_MAILER=smtp`) | Di produksi, kegagalan gateway menggagalkan transaksi registrasi secara atomis (*atomic rollback*); tidak ada fallback simulasi OTP di produksi. |
| **Payment Gateway** | Xendit REST API (`v2/invoices` & `/qr_codes`) | Verifikasi keaslian webhook via header `x-callback-token` dan deduplikasi `PaymentEvent`. |
| **Peta & Geocoding** | Frontend Leaflet `^1.9.4` + Backend OpenStreetMap Nominatim | Nominatim dibatasi maksimal 1 request/detik secara global dengan cache koordinat 4 desimal (~11 meter) selama 24 jam. |
| **Penyedia LLM AI** | OpenRouter API (`https://openrouter.ai/api/v1`) | Model default `liquid/lfm-2.5-2.6b:free`; dapat diganti langsung oleh Super Admin melalui UI tanpa edit `.env`. |
| **Unggah Berkas Privat** | `SecureFileUpload` (`storage/app/private`) | Validasi ekstensi (`pdf, jpg, jpeg, png, webp`) + deteksi MIME via `finfo`; batas 5 MB (dokumen toko/komplain) dan 2 MB (avatar). |

---

## Bab 3 — Kebutuhan Fungsional, User Stories & Acceptance Criteria

Setiap kebutuhan fungsional (`FR`) berikut diturunkan langsung dari fitur aktif pada codebase dan dilengkapi **User Story (`US`)** serta **Acceptance Criteria (*Given–When–Then*)** terukur.

---

### FR-01: Autentikasi OTP, Login Password, & Isolasi Guard 4 Portal

- **Prioritas:** MUST HAVE
- **User Story (`US-01`):** Sebagai **Customer, Mitra Toko, Kurir, atau Super Admin**, saya ingin mendaftar/masuk hanya melalui portal peran masing-masing menggunakan verifikasi keamanan yang sesuai (OTP WhatsApp/Email untuk Customer, kredensial + 2FA TOTP untuk Super Admin), agar akses akun terlindungi dan terisolasi antarsesi.
- **Aturan Bisnis:**
  1. Pendaftaran Customer dilakukan via kanal **WhatsApp (Fonnte)** atau **Email (SMTP)** dengan kata sandi minimal **8 karakter**, tanpa login pihak ketiga eksternal.
  2. OTP terdiri dari **6 digit angka**, berlaku **5 menit**, maksimal **5 kali percobaan salah**, dan jeda kirim ulang (*resend cooldown*) **60 detik**.
  3. Seluruh pembuatan baris `users` dan pengiriman OTP dijalankan dalam **1 transaksi database atomik (`DB::transaction`)**; jika gateway WhatsApp/SMTP gagal, akun batal dibuat agar tidak ada akun menggantung.
  4. Customer yang mendaftar via nomor WhatsApp tanpa email diberikan email internal placeholder berpola `wa_<nomor>@laundryku.test` yang disembunyikan dari UI/notifikasi email melalui accessor `display_email` dan `has_real_email`.
  5. Nilai `debug_otp` hanya boleh muncul apabila `OTP_DEBUG_RESPONSE=true` DAN `APP_ENV` bernilai `local`/`testing`; pada mode bawaan bernilai `null`.
  6. Super Admin wajib melewati dua tahap di `/admin/login`: kredensial valid menerbitkan token sementara berdurasi **5 menit** (`ability: 2fa-pending`) sebelum verifikasi TOTP Google Authenticator menerbitkan token penuh di cookie `laundryku_admin_token`.
- **Acceptance Criteria (`AC-01`):**
  - *Given* tamu mengisi form daftar Customer di `/auth` dengan nomor WhatsApp valid `0812xxx` dan password >= 8 karakter, *When* sistem memanggil `POST /api/v1/auth/register` dan Fonnte mengembalikan sukses, *Then* `OtpChallenge` tersimpan dalam bentuk hash SHA-256 dengan masa berlaku 5 menit, `debug_otp` bernilai `null`, dan tab beralih ke input OTP 6 digit.
  - *Given* gateway WhatsApp atau SMTP mengembalikan error/timeout saat registrasi, *When* `OtpService::generateOtp()` melempar exception, *Then* transaksi DB di-rollback 100% sehingga tidak ada baris baru pada tabel `users`.
  - *Given* pengguna login di portal Customer mencoba mengakses endpoint `/api/v1/admin/*`, *When* permintaan melewati middleware `ability:role:super_admin` atau cookie `laundryku_admin_token` kosong, *Then* sistem menolak dengan status HTTP `401`/`403`.
  - *Given* Super Admin berhasil memasukkan email dan password benar pada `/admin/login`, *When* kode 2FA TOTP belum diverifikasi, *Then* pengguna hanya memperoleh cookie `laundryku_admin_temp_token` berizin `2fa-pending` dan tidak dapat memanggil endpoint `/api/v1/admin/dashboard/metrics`.

---

### FR-02: Manajemen Profil, Alamat Terstruktur, & Reverse/Forward Geocoding GPS

- **Prioritas:** MUST HAVE
- **User Story (`US-02`):** Sebagai **Customer**, saya ingin mengelola profil (termasuk menambah email riil jika mendaftar via WhatsApp), foto profil, serta menyimpan beberapa alamat lengkap memakai deteksi GPS browser ataupun input manual, agar kurir dapat menjemput dan mengantar cucian ke titik akurat.
- **Aturan Bisnis:**
  1. Pelanggan ber-email placeholder `wa_*@laundryku.test` ditampilkan nomor WhatsApp terformat (`+628...`) dan dapat mengisi email asli melalui pembaruan profil (`PUT /api/v1/profile`).
  2. Foto profil/avatar diverifikasi menggunakan `SecureFileUpload` maksimal **2 MB** (`jpg, jpeg, png, webp`).
  3. Deteksi GPS pada `/profile` mengisi kolom alamat terstruktur (`address`, `kelurahan`, `kecamatan`, `city`, `province`, `postal_code`, `latitude`, `longitude`) melalui endpoint `POST /api/v1/geocode/reverse` tanpa menyimpan otomatis sebelum pengguna menekan simpan.
  4. Alamat yang disimpan atau dijadikan default tanpa koordinat otomatis diproses *forward geocoding* oleh `NominatimGeocodingService` agar titik lokasi header di `/stores` tersinkronisasi melalui event `address-changed`.
- **Acceptance Criteria (`AC-02`):**
  - *Given* Customer terotentikasi menekan tombol "Gunakan Lokasi Saya Sekarang (GPS)" dan mengizinkan izin lokasi browser, *When* koordinat dikirim ke `POST /api/v1/geocode/reverse`, *Then* `NominatimGeocodingService` mengembalikan pemetaan alamat terstruktur Indonesia dan form alamat terisi otomatis untuk ditinjau pengguna.
  - *Given* Customer tidak mengizinkan GPS atau Nominatim gagal merespons dalam 5 detik, *When* form alamat tetap diisi manual dan disimpan, *Then* alamat tetap tersimpan ke tabel `customer_addresses` tanpa memblokir pengguna.
  - *Given* Customer A mengirim permintaan ubah/hapus alamat milik Customer B, *When* controller memeriksa kepemilikan `user_id`, *Then* akses ditolak dan isolasi data antar-pengguna terjaga.

---

### FR-03: Marketplace Discovery, Katalog Layanan, Peta Leaflet, & Estimasi Harga

- **Prioritas:** MUST HAVE
- **User Story (`US-03`):** Sebagai **Tamu atau Customer**, saya ingin mencari toko laundry terdekat pada daftar maupun peta interaktif, menyaring berdasarkan layanan/harga/rating/jarak, melihat rincian layanan toko, dan menghitung estimasi harga, agar saya dapat memilih laundry yang paling sesuai sebelum memesan.
- **Aturan Bisnis:**
  1. Hanya toko dengan status disetujui (`approved` / aktif) yang muncul pada pencarian publik (`GET /api/v1/stores`).
  2. Peta di `/stores` dirender menggunakan library **Leaflet (`^1.9.4`)** tanpa ketergantungan API peta berbayar.
  3. Pemeringkatan toko oleh `StoreSearchService` menghitung skor berbobot: jarak Haversine (**0.30**), rating rata-rata (**0.25**), ketersediaan (**0.15**), jumlah ulasan (**0.10**), kecocokan layanan (**0.10**), dan performa operasional (**0.10**), dengan paginasi default **15 toko per halaman**.
  4. Estimasi harga dihitung di server melalui `POST /api/v1/pricing/estimate` dan dikunci ulang di server saat order dibuat.
- **Acceptance Criteria (`AC-03`):**
  - *Given* terdapat toko berstatus `pending` atau `suspended` di database, *When* Customer membuka `/stores` atau memanggil `GET /api/v1/stores`, *Then* toko tersebut tidak dikembalikan dalam hasil pencarian.
  - *Given* Customer memiliki alamat utama di kota tertentu (mis. Cirebon), *When* halaman `/stores` dimuat, *Then* lokasi header peta otomatis mengacu ke label alamat utama dan menyortir toko berdasarkan bobot jarak dan skor terhitung.

---

### FR-04: Onboarding Mitra Toko, Upgrade Peran, & Persetujuan Admin

- **Prioritas:** MUST HAVE
- **User Story (`US-04`):** Sebagai **Calon Mitra Toko atau Customer yang ingin menjadi Kurir/Mitra**, saya ingin mengajukan pendaftaran toko atau lamaran kurir lengkap dengan dokumen pendukung dan titik koordinat gerai, agar pengajuan saya dapat ditinjau dan disetujui oleh Super Admin atau Mitra Toko.
- **Aturan Bisnis:**
  1. Tersedia dua jalur pendaftaran gerai: registrasi mitra baru (`/mitra/register` -> `POST /api/v1/auth/mitra/register`, wajib email/telepon unik berbeda dari akun customer) dan pengajuan upgrade akun customer (`/upgrade` -> `/api/v1/upgrades/store` dan `/api/v1/upgrades/courier`).
  2. Dokumen legal usaha (`NIB`, `KTP`, dll.) dan foto kurir diunggah melalui `SecureFileUpload` maksimal **5 MB** (`pdf, jpg, jpeg, png, webp`) ke disk privat (`storage/app/private`) dan hanya dapat diunduh melalui controller terotorisasi.
  3. Form `/upgrade` menyimpan draf sementara di `localStorage` browser (`laundryku_courier_draft`, `laundryku_store_draft`) guna mencegah kehilangan isian pengguna.
  4. Super Admin memproses pengajuan toko (`approve` / `reject` dengan alasan), dan setiap keputusan tercatat pada `audit_logs` serta memicu notifikasi ke pemohon.
- **Acceptance Criteria (`AC-04`):**
  - *Given* Customer mengisi pengajuan toko di `/upgrade` dan mengunggah berkas PDF `<= 5 MB`, *When* formulir dikirim ke `POST /api/v1/upgrades/store`, *Then* berkas tersimpan dengan nama acak di disk privat, status aplikasi menjadi `pending`, dan berkas tidak dapat diakses lewat URL publik langsung.
  - *Given* pengguna mencoba mengunggah berkas `.exe` atau `.php` yang diubah ekstensinya menjadi `.jpg`, *When* `SecureFileUpload` memeriksa MIME asli via `finfo`, *Then* unggahan ditolak sebelum disimpan ke disk.
  - *Given* Super Admin menyetujui toko pada `/admin/stores`, *When* `POST /api/v1/admin/stores/{id}/approve` dipanggil, *Then* status toko berubah menjadi aktif/disetujui, toko muncul di marketplace `/stores`, dan jejak perubahan dicatat di `audit_logs`.

---

### FR-05: Siklus Pesanan End-to-End (19 Status) & Penimbangan Berat Aktual

- **Prioritas:** MUST HAVE
- **User Story (`US-05`):** Sebagai **Customer dan Mitra Toko**, saya ingin membuat pesanan dengan estimasi awal, memisahkan pencatatan berat estimasi dengan berat hasil timbang aktual di toko, serta memantau setiap transisi status cucian secara akurat, agar harga akhir adil dan riwayat pesanan selalu dapat diaudit.
- **Aturan Bisnis:**
  1. Pembuatan order (`POST /api/v1/orders`) wajib memiliki minimal 1 item layanan aktif dari toko eligible dan menghitung total estimasi di server: `sum(max(qty * price_per_unit, minimum_charge)) + platform_fee (default Rp1.000) + delivery_fee + pickup_fee`.
  2. Status pesanan mengikuti **19 enum status** (`OrderStatus`): `created`, `waiting_pickup`, `courier_assigned`, `courier_to_customer`, `picked_up`, `in_transit_to_store`, `received_by_store`, `weighed`, `payment_required`, `paid`, `processing`, `ready_for_delivery`, `courier_assigned_for_delivery`, `out_for_delivery`, `delivered`, `completed`, `cancelled`, `pickup_failed`, `delivery_failed` — ditambah transisi `awaiting_price_approval` saat perubahan harga setelah ditimbang melewati ambang batas (`priceChangeThreshold = Rp50.000`).
  3. Nilai `estimated_weight` dan `actual_weight` disimpan pada kolom terpisah dan tidak pernah saling menimpa.
  4. Setiap perubahan status harus divalidasi oleh `OrderPolicy` & peta transisi di `OrderService`, lalu dicatat ke tabel `order_status_history` sebagai sumber kebenaran linimasa (*timeline*) pesanan.
- **Acceptance Criteria (`AC-05`):**
  - *Given* Customer memilih layanan aktif di toko berstatus disetujui dan menekan buat pesanan di `/stores/{id}/order`, *When* `OrderService::createOrder()` dieksekusi, *Then* nomor pesanan unik dibuat, harga dihitung dari `service_prices` server-side (mengabaikan manipulasi harga dari browser), dan `order_status_history` mencatat status awal `created`.
  - *Given* pesanan berada pada status `received_by_store` dan Mitra menimbang `actual_weight`, *When* selisih harga baru terhadap estimasi awal melebihi `Rp50.000`, *Then* sistem menandai status persetujuan harga dan mengirim notifikasi ke Customer sebelum berlanjut ke penagihan `payment_required`.
  - *Given* Mitra Toko A mencoba memanggil endpoint status pada pesanan milik Toko B, *When* `Gate::authorize('update', $order)` dieksekusi, *Then* Laravel menolak permintaan dengan HTTP `403`.

---

### FR-06: Pembayaran Digital Xendit, COD Tunai, Komisi & Settlement

- **Prioritas:** MUST HAVE
- **User Story (`US-06`):** Sebagai **Customer, Mitra Toko, dan Super Admin**, saya ingin membayar pesanan lewat QRIS/Invoice Xendit atau COD tunai sesuai ketentuan batas aman, serta mengelola persentase komisi dan laporan settlement toko secara transparan, agar alur keuangan bebas dari transaksi ganda dan selisih hitungan.
- **Aturan Bisnis:**
  1. Nilai tagihan yang dikirim ke Xendit selalu diambil dari `total_amount` / `final_price` di server, tidak pernah dari input klien.
  2. `XenditService` mendukung Invoice multifungsi (`v2/invoices`, batas waktu **60 menit**) dan Dynamic QRIS (`/qr_codes`, batas waktu **2 menit**) dengan kunci idempotensi per percobaan pembayaran.
  3. Webhook `POST /api/webhooks/xendit` dilindungi rate limiter (`60 req/menit`), wajib memvalidasi `x-callback-token` dari konfigurasi `xendit.webhook_secret`, dan memfilter duplikasi event melalui tabel `payment_events` (`xendit_event_id`).
  4. Pembayaran tunai **COD** dibatasi maksimal **Rp500.000** (`COD_MAX_AMOUNT` pada `settings`), dan penagihan oleh kurir/toko dicatat ke tabel `cod_collections` lewat `/api/v1/courier/tasks/{orderId}/confirm-cod` atau `/api/v1/store/orders/{order}/confirm-cod`.
  5. Persentase komisi platform (`platform_commission_rate`, default **10%**) dikelola secara dinamis oleh Super Admin melalui `/admin/settings` (`PATCH /api/v1/admin/platform-settings/commission`), dengan setiap perubahan dicatat ke `audit_logs` aksi `ADMIN_COMMISSION_UPDATE`.
  6. `SettlementService` membentuk laporan periode settlement toko (`generated -> verified`) **hanya untuk order `completed`** yang sudah melewati masa tunggu sengketa (`dispute_window_days = 7 hari`), bebas dari komplain terbuka, dan mencegah tumpang-tindih rentang tanggal antar-periode.
- **Acceptance Criteria (`AC-06`):**
  - *Given* Customer memilih metode bayar digital pada order yang siap bayar, *When* sistem membuat invoice ke Xendit `v2/invoices`, *Then* record `payments` tersimpan berstatus `pending` dengan `expires_at` 60 menit ke depan dan tautan pembayaran tampil di `/orders/{id}`.
  - *Given* Xendit mengirimkan webhook `PAID` sebanyak dua kali dengan `xendit_event_id` yang sama dan `x-callback-token` valid, *When* webhook pertama sudah diproses, *Then* webhook kedua dideteksi oleh `PaymentEvent` sehingga status order tidak terproses ulang dan tidak ada duplikasi catatan transaksi.
  - *Given* nilai pesanan melebihi `Rp500.000`, *When* pengguna bertanya/memilih COD pada aturan sistem atau mencoba pembayaran COD, *Then* sistem menegakkan batas maksimum COD `Rp500.000` dan mengarahkan ke metode pembayaran digital Xendit.

---

### FR-07: Manajemen Tugas Kurir & Pelacakan Status Perjalanan

- **Prioritas:** MUST HAVE
- **User Story (`US-07`):** Sebagai **Kurir dan Customer**, saya ingin kurir dapat mengambil dan memperbarui status tugas penjemputan/pengantaran serta merekam posisi koordinat saat bertugas aktif, agar pelanggan dapat memantau perkembangan jemput-antar cucian di dashboard pesanan.
- **Aturan Bisnis:**
  1. Hanya user ber-role `courier` yang sudah terverifikasi (`is_verified = true`) yang dapat mengakses profil dan daftar tugas kurir; akun belum terverifikasi ditolak HTTP `403`.
  2. Siklus tugas kurir mengikuti **7 status (`CourierTaskStatus`)**: `assigned`, `accepted`, `arrived`, `picked_up`, `delivered`, `failed`, `cancelled`.
  3. Saat penugasan oleh `CourierAssignmentService`, kolom `orders.courier_id` disinkronkan dengan `user_id` kurir agar statistik tugas aktif dan riwayat di `/courier` serta `/courier/tasks` konsisten.
  4. Perekaman koordinat GPS oleh `GpsTrackingService::storeLocation()` **hanya diterima apabila kurir memiliki tugas aktif**; apabila tidak ada tugas aktif, pembaruan GPS ditolak dan dicatat sebagai peringatan log.
  5. Antarmuka `/courier` dan `/courier/tasks` menyinkronkan profil serta daftar tugas via *silent background polling* (`usePolling` setiap **15 detik**) yang otomatis jeda saat tab tidak aktif.
- **Acceptance Criteria (`AC-07`):**
  - *Given* seorang Customer mendaftar upgrade menjadi kurir namun belum berstatus terverifikasi, *When* ia mencoba membuka tab tugas di `/courier`, *Then* backend `CourierPortalController::taskList()` menolak akses dengan HTTP `403`.
  - *Given* kurir terverifikasi berada di tab `active` pada `/courier/tasks`, *When* polling 15 detik memuat ulang profil dan tugas, *Then* lencana jumlah pada tab *Tugas aktif saya*, *Tugas tersedia*, dan *Riwayat selesai* tampil konsisten berdasarkan agregat `stats`.
  - *Given* seluruh tugas kurir sudah selesai (`delivered`/`cancelled`), *When* pengiriman titik GPS masuk ke endpoint lokasi kurir, *Then* `GpsTrackingService` menolak penyimpanan titik baru dan mengembalikan `null`.

---

### FR-08: Human Chat Toko–Pelanggan, Notifikasi Multi-Kanal, Rating & Komplain

- **Prioritas:** MUST HAVE
- **User Story (`US-08`):** Sebagai **Customer dan Mitra Toko**, saya ingin berkomunikasi langsung mengenai detail cucian lewat obrolan toko, menerima notifikasi otomatis status pesanan, memberi rating setelah selesai, dan mengajukan/menyelesaikan komplain secara adil, agar kendala layanan tertangani tuntas.
- **Aturan Bisnis:**
  1. Fitur **Human Chat** (`/chat` untuk Customer, `/store/chat` untuk Mitra) menggunakan grup endpoint `/api/v1/conversations` dengan isolasi kepemilikan percakapan dan streaming lampiran aman (`GET /conversations/{id}/attachments/{messageId}`).
  2. `NotificationService` mengirimkan notifikasi sebagai *side-effect* asinkron lewat queued job `SendUserNotificationJob` melalui 3 kanal (`in_app`, `email`, `whatsapp`), menghormati preferensi pengguna, dan otomatis melewati kanal email jika pengguna hanya memiliki placeholder `wa_*@laundryku.test`.
  3. Ulasan dan rating (`1–5`) hanya dapat dikirim oleh pemilik pesanan setelah pesanan berstatus **`completed`** (`1 ulasan per pesanan`), yang otomatis menghitung ulang agregat `average_rating` toko.
  4. Komplain/sengketa (`DisputeService`) mendukung 7 alasan (`damaged_item`, `lost_item`, `late_delivery`, `poor_quality`, `wrong_item`, `overcharge`, `other`), SLA penyelesaian **7 hari**, dibatasi rate limit **3 pengajuan per hari per pengguna**, serta penyelesaian oleh Mitra/Admin dengan catatan `resolution_note` wajib.
- **Acceptance Criteria (`AC-08`):**
  - *Given* Customer menekan tombol "Chat Toko" pada detail toko `/stores/{id}`, *When* permintaan dikirim ke `POST /api/v1/conversations`, *Then* sesi percakapan dibuat/dimuat untuk pasangan customer–toko tersebut dan notifikasi jumlah pesan belum dibaca muncul di header.
  - *Given* pesanan masih berstatus `processing`, *When* Customer mencoba mengirim POST ke `/api/v1/orders/{order}/rating`, *Then* `ReviewService` menolak dengan pesan bahwa ulasan hanya untuk pesanan berstatus `completed`.
  - *Given* salah satu kanal notifikasi (mis. SMTP) mengalami gangguan jaringan, *When* job `SendUserNotificationJob` mengeksekusi `NotificationService::notify()`, *Then* kanal `in_app` tetap tersimpan, kegagalan tercatat di kolom `email_error`, dan transisi status order tidak terganggu.

---

### FR-09: Manajemen Operasional & Keuangan Portal Mitra Toko (`/store/*`)

- **Prioritas:** MUST HAVE
- **User Story (`US-09`):** Sebagai **Mitra Toko / Store Owner**, saya ingin mengelola daftar layanan, aturan promo/diskon harga, jadwal operasional, perekrutan kurir toko, rekening pencairan dana, buku kas ledger, dan laporan omset terunduh CSV dalam satu portal toko, agar operasional gerai berjalan mandiri dan terukur.
- **Aturan Bisnis:**
  1. Seluruh query data pada `/api/v1/store/*`, `/api/v1/mitra/*`, dan `/api/v1/store-admin/*` wajib terikat pada `store_id` milik mitra yang terotentikasi (*tenant isolation*).
  2. Mitra mengelola katalog layanan gerai (`/store/services`) beserta status aktif/nonaktif massal (`bulk-status`), serta mengatur aturan harga & promo pada `/store/pricing` (`min_weight_discount_kg`, `min_weight_discount_percent`, `minimum_order_amount`, dll.) yang disimpan di `store_settings` (`key = 'pricing_rules'`).
  3. Mitra mengelola rekening tujuan pencairan pada `/store/payment` (`store_payout_accounts`) dan memantau arus kas pada `/store/ledger`.
  4. Halaman `/store/reports` menyajikan ringkasan omset, pemisahan digital vs COD, volume pesanan, rating/komplain pada rentang waktu pilihan (`7D`, `30D`, `Bulan Ini`, `Kustom`), serta tombol ekspor file **CSV** di sisi klien.
- **Acceptance Criteria (`AC-09`):**
  - *Given* Mitra menyimpan aturan diskon berat minimum di `/store/pricing`, *When* `PUT /api/v1/store/pricing-rules` tervalidasi, *Then* konfigurasi tersimpan di `store_settings` untuk toko terkait dan jejak perubahan dicatat.
  - *Given* Mitra memilih rentang 30 hari terakhir di `/store/reports`, *When* tombol Ekspor CSV ditekan, *Then* browser mengunduh rekap transaksi toko terfilter dalam format `.csv` dengan format numerik bersih.

---

### FR-10: Tata Kelola Platform Portal Super Admin (`/admin/*`)

- **Prioritas:** MUST HAVE
- **User Story (`US-10`):** Sebagai **Super Admin Platform**, saya ingin memantau kesehatan operasional lintas toko secara menyeluruh, menyetujui/menolak pendaftaran toko baru, menonaktifkan akun bermasalah, mengelola kategori layanan global, mengatur komisi platform & model AI, serta menginspeksi log audit sensitif, agar ekosistem marketplace tetap aman dan terstandar.
- **Aturan Bisnis:**
  1. Portal `/admin/*` menyediakan 9 menu sidebar tetap: Dashboard (`/admin`), Pesanan (`/admin/orders`), Transaksi & Bayar (`/admin/payments`), Persetujuan Toko (`/admin/stores`), Manajemen Pengguna (`/admin/users`), Kategori Layanan (`/admin/categories`), Konfigurasi AI (`/admin/settings/ai`), Komisi Platform (`/admin/settings`), dan Audit Log (`/admin/audit-logs`).
  2. Endpoint `GET /api/v1/admin/dashboard/metrics` menyajikan total pengguna per peran, pesanan aktif per status (normalisasi uppercase & lowercase), ringkasan pembayaran per status, toko pending, GMV, 10 pesanan terbaru, dan 10 log audit terbaru.
  3. Seluruh aksi sensitif (login/gagal login, persetujuan/penolakan toko, ubah status pengguna, ubah komisi platform, ubah model AI) wajib menulis baris ke `audit_logs` lengkap dengan `actor_id`, `actor_role`, `action`, `before_value`, `after_value`, `ip_address`, dan `user_agent`.
- **Acceptance Criteria (`AC-10`):**
  - *Given* Super Admin terotentikasi 2FA membuka `/admin`, *When* `AdminPortalController::metrics` dipanggil, *Then* dashboard menampilkan metrik agregat real-time dari MySQL tanpa nilai dummy statis.
  - *Given* Super Admin mengubah persentase komisi platform dari `10%` menjadi `12%` di `/admin/settings`, *When* `PATCH /api/v1/admin/platform-settings/commission` berhasil, *Then* `settings.platform_commission_rate` diperbarui dan `audit_logs` mencatat aksi `ADMIN_COMMISSION_UPDATE` dengan `before_value: 10` dan `after_value: 12`.

---

## Bab 4 — Spesifikasi Fitur AI Utama: LANDY Hybrid Assistant

### 4.1 Peran Strategis Fitur AI terhadap Tema `Laundryku`

Dalam ekosistem **Laundryku**, fitur kecerdasan buatan **LANDY AI Assistant** dirancang bukan sekadar pemanggil API chatbot konvensional, melainkan **Hybrid Intent-Routing Operational Assistant** yang memecahkan tiga tantangan nyata sekaligus:
1. **Efisiensi Biaya Operasional UMKM (0-Token Layer 1):** Pertanyaan panduan penggunaan, salam, serta kueri data terstruktur umum diselesaikan secara lokal dalam **15–30 ms tanpa menghabiskan token LLM eksternal**.
2. **Aksesibilitas Data Real-Time Lintas 4 Peran (*Strict Tenant & Read-Only*):** Customer, Mitra Toko, Kurir, dan Super Admin masing-masing memperoleh jawaban kontekstual berbasis data riil database mereka sendiri tanpa kebocoran data lintas pengguna/toko.
3. **Pengendalian Biaya & Kualitas Model Dinamis:** Super Admin dapat mengganti model OpenRouter antara **Free Tier (`Rp 0`)** dan **Commercial Tier** langsung dari antarmuka web sekaligus melakukan uji latensi real-time.

### 4.2 Arsitektur Hybrid 3-Layer Intent Routing

Seluruh logika inti dikelola oleh 12 kelas layanan di `app/Services/Ai/` (`AiAssistantService`, `AiIntentRouter`, `AiKnowledgeBaseService`, `AiDataQueryService`, `AiOrderStatsService`, `AiTextNormalizer`, `AiToolRegistry`, `AiTopicGuardrail`, `OpenAiClient`, serta antarmuka/eksepsi terkait).

```text
+------------------------------------------------------------------------------+
|  Pesan Masuk Pengguna (/api/chat -> /api/v1/chatbot/* atau /api/ai/chat)      |
+--------------------------------------+---------------------------------------+
                                       |
                                       v
+------------------------------------------------------------------------------+
|  LAYER 0: Guardrail Keamanan, Rate Limit (30 req/jam), & Normalizer Slang     |
|  - Cek out-of-scope topik (coding/politik/dll) -> tolak sopan + arahkan      |
|  - Cek permintaan mutasi lewat chat (cancel/hapus/refund) -> tolak + panduan |
|  - Normalisasi singkatan/bahasa gaul/typo via AiTextNormalizer               |
+--------------------------------------+---------------------------------------+
                                       |
                                       v
+------------------------------------------------------------------------------+
|  LAYER 1: Deterministik Rule, Levenshtein, & Procedural KB (0 TOKEN LLM)     |
|  - Sapaan / terima kasih / pamit -> respons instan                           |
|  - Pencocokan 21 topik KB (resources/ai/knowledge/*_guides.php) sesuai role  |
|  - Kueri data terstruktur langsung ke AiDataQueryService / AiOrderStats      |
|  - Compound Multi-Intent (pertanyaan ganda dipisah dengan '---')             |
+------------------+-----------------------------------+-----------------------+
                   | Cocok                             | Tidak Cocok
                   v                                   v
      [Kirim Jawaban 0-Token]       +------------------------------------------+
                                    |  LAYER 2: OpenRouter LLM + Tool Calling  |
                                    |  - Sliding window max_history = 6 pesan  |
                                    |  - Cuplikan konteks KB relevan (non-PII) |
                                    |  - Eksekusi max_tool_rounds = 5 putaran  |
                                    |  - Akses baca terotorisasi via 33 Tools  |
                                    +------------------+-----------------------+
                                                       | Gagal / Timeout (30s)
                                                       v
                                    +------------------------------------------+
                                    |  LAYER 3: Klarifikasi Role & Eskalasi CS |
                                    |  - Menu topik bantuan sesuai role        |
                                    |  - Arahkan ke CS resmi: cs@laundryku.test|
                                    +------------------------------------------+
```

### 4.3 Matriks Kemampuan & 33 Registered AI Tools Read-Only (`AiToolRegistry`)

Seluruh tool pada [AiToolRegistry.php](file:///c:/laragon/www/laundryku/app/Services/Ai/AiToolRegistry.php) bersifat **strictly read-only** dan hanya dieksekusi jika role terotentikasi pengguna sesuai dengan cakupan tool:

| Cakupan Akses | Jumlah & Daftar Nama Tool Terdaftar | Basis Pengetahuan Prosedural (0-Token KB) |
| :--- | :--- | :--- |
| **Umum / Publik & Profil (`Shared`)** | **3 Tools:** `get_user_profile`, `get_store_information`, `get_services_and_prices` | FAQ publik `/api/v1/chatbot/faq` |
| **Customer** | **6 Tools:** `customer_list_orders`, `customer_get_order_detail`, `customer_get_order_stats`, `customer_get_payment_status`, `customer_get_reviews`, `customer_get_complaints` | **8 topik** (`customer_guides.php`: order, bayar, lacak, batal, komplain, ulasan, alamat GPS, gabung mitra) |
| **Mitra / Store Admin** | **7 Tools:** `store_get_summary`, `store_list_orders`, `store_get_order_detail`, `store_get_services`, `store_get_couriers`, `store_get_reviews`, `store_get_complaints` | **7 topik** (`store_guides.php`: pesanan masuk, timbangan berat riil, layanan & harga, jam buka, komplain, rekrut kurir, pengaturan gerai) |
| **Kurir** | **4 Tools:** `courier_get_profile`, `courier_get_tasks`, `courier_get_task_stats`, `courier_get_cod_rules` | **3 topik** (`courier_guides.php`: tugas jemput/antar, tagih & setor COD, status online/offline) |
| **Super Admin Platform** | **9 Tools:** `platform_get_overview`, `platform_get_user_stats`, `platform_get_approvals`, `platform_get_complaints`, `platform_search_users`, `platform_get_orders`, `platform_get_courier_activity`, `platform_get_settlements`, `platform_explain_admin_menu` | **3 topik** (`admin_guides.php`: verifikasi mitra/kurir, monitoring overview platform, manajemen user & toko) |
| **Kompatibilitas Legacy** | **4 Tools Alias:** `get_customer_order_status`, `get_customer_payment_status`, `get_store_service`, `get_order_basic_information` | Diarahkan ke handler terotorisasi yang sama |

### 4.4 Guardrail Keamanan, Anti-Halusinasi, & Sanitasi PII

1. **Zero Write / Zero SQL Execution via Chat:** Permintaan pengguna untuk membatalkan order, mengubah alamat, refund, menghapus user, atau menjalankan mutasi data melalui prompt chat ditolak otomatis oleh Layer 0/1 dengan penjelasan sopan disertai **langkah bernomor** cara melakukannya lewat menu UI resmi.
2. **Isolasi Identitas Server-Side:** Identitas pengguna (`user_id`), `role`, dan `store_id` dibaca dari token/guard backend yang terotentikasi—**tidak pernah** mempercayai parameter peran atau ID akun dari payload klien. Non-Super Admin yang mencoba memicu tool `platform_*` langsung ditolak.
3. **Anti-Halusinasi Transaksi:** Harga, status order, status bayar, dan berat cucian wajib diambil dari System of Record (`AiDataQueryService` / tools). Pada fallback LLM, prompt sistem melarang mengarang fakta di luar konteks KB.
4. **Sanitasi PII (`maskEmail` & `maskPhone`):** Seluruh hasil pencarian user untuk AI maupun catatan log token (`ai_usage_logs`) menyamarkan email, nomor HP, serta tidak pernah memuat `password`, token sesi, atau `google2fa_secret`.

### 4.5 Dynamic AI Model Switcher & Runtime Tuning (`/admin/settings/ai`)

Melalui halaman Konfigurasi AI pada portal Super Admin ([admin/settings/ai/page.tsx](file:///c:/laragon/www/laundryku/frontend/app/admin/settings/ai/page.tsx) & `AdminPortalController`), administrator dapat mengubah model OpenRouter dan parameter inferensi tanpa menyentuh file `.env`:
- **8 Model Preset Terkurasi (`GET /api/v1/admin/ai-settings`):**
  - **Kategori Gratis (`Free Tier`, `$0`):** `liquid/lfm-2.5-2.6b:free` (default), `google/gemini-2.0-flash-exp:free`, `meta-llama/llama-3.3-70b-instruct:free`, `deepseek/deepseek-r1:free`.
  - **Kategori Komersial (`Paid Tier`):** `google/gemini-2.5-flash`, `openai/gpt-4o-mini`, `deepseek/deepseek-chat`, `anthropic/claude-3.5-haiku`.
  - Mendukung pengisian **custom model identifier OpenRouter**.
- **Parameter Tuning & Live Diagnostic:** Pengaturan slider `temperature` (dengan preset cepat `0.0`, `0.2`, `0.5`) serta `max_tool_rounds`, ditambah fitur **Uji Koneksi Live (`POST /api/v1/admin/ai-settings/test`)** yang mem-ping endpoint OpenRouter untuk menampilkan latensi (`ms`), status HTTP, dan pratinjau respons secara seketika.
- **Penyimpanan Cache & Audit:** Disimpan pada tabel `settings` dengan invalidasi cache otomatis dan pencatatan `UPDATE_AI_MODEL` ke tabel `audit_logs` beserta pembanding `before_value` dan `after_value`.

### 4.6 Hasil Evaluasi Terukur (`AiHoldOutEvaluationTest`)

Pengujian akurasi intent routing terhadap **165 kalimat hold-out independen** (`storage/holdout_dataset.php`: 35 panduan, 45 kueri data lintas 4 peran, 30 *out-of-scope*, 30 bahasa gaul/typo, 25 pertanyaan ganda) menunjukkan peningkatan objektif sebagai berikut:

| Kategori Uji Hold-Out | Jumlah Kasus | Akurasi Baseline Awal | Akurasi Final Setelah 3-Layer + KB |
| :--- | :---: | :---: | :---: |
| Pertanyaan Panduan Prosedural | 35 | 0 / 35 (0,00%) | **35 / 35 (100,0%)** |
| Kueri Data Operasional Lintas 4 Role | 45 | 39 / 45 (86,67%) | **45 / 45 (100,0%)** |
| Pertanyaan di Luar Topik (*Out-of-Scope*) | 30 | 27 / 30 (90,00%) | **30 / 30 (100,0%)** |
| Bahasa Gaul, Singkatan, & Typo | 30 | 13 / 30 (43,33%) | **30 / 30 (100,0%)** |
| Pertanyaan Ganda (*Compound Multi-Intent*) | 25 | 1 / 25 (4,00%) | **25 / 25 (100,0%)** |
| **Total Akurasi Keseluruhan** | **165** | **80 / 165 (48,48%)** | **165 / 165 (100,0%)** |

---

## Bab 5 — Kebutuhan Non-Fungsional (Terukur)

| ID | Kategori | Pernyataan Kebutuhan Terukur | Metode Verifikasi |
| :--- | :--- | :--- | :--- |
| **`NFR-PERF-01`** | Performa API | TTFB endpoint API ringan dengan OPcache aktif maksimal **100 ms** (terukur aktual **37,3 ms** pada `/api/v1/chatbot/faq` di Apache Laragon OPcache, membaik ~78,5% dari 173,5 ms). | Uji benchmark HTTP lokal dengan OPcache aktif. |
| **`NFR-PERF-02`** | Efisiensi AI | Mayoritas kueri panduan prosedural (21 topik KB) & kueri data Layer 1 diselesaikan pada **0 token LLM** dengan latensi router lokal <= **50 ms** dan timeout API OpenRouter maks **30 detik**. | `AiHoldOutEvaluationTest` & `OpenAiClient` timeout config. |
| **`NFR-PERF-03`** | Pembaruan Dashboard | Sinkronisasi dashboard kurir/tugas melalui *silent background polling* berjalan setiap **15 detik** saat tab aktif dan otomatis jeda di latar belakang. | Inspeksi hook `usePolling` & Playwright / dev tools. |
| **`NFR-PERF-04`** | Rate Limit Geocoding | Panggilan API OpenStreetMap Nominatim dibatasi global **<= 1 request/detik** dengan cache koordinat 4 desimal (~11 m) berlaku **86.400 detik (24 jam)**. | `AddressGeocodingTest` & cache key inspection. |
| **`NFR-SEC-01`** | Isolasi Sesi & BFF | Token Sanctum disimpan hanya pada cookie **`httpOnly`** (`SameSite=Lax`, `Secure` di produksi) terpisah per 4 portal (`customer`, `mitra`, `courier`, `admin`); BFF proxy memvalidasi `Origin`/`Referer` pada seluruh mutasi HTTP. | `bff.ts`, `Phase1FoundationAuthTest`, & `CourierPortalAuthorizationTest`. |
| **`NFR-SEC-02`** | Proteksi OTP & Brute Force | Kode OTP 6 digit di-hash SHA-256, berlaku **5 menit**, maks **5** salah input, cooldown kirim ulang **60 detik**, batas **15 req/jam/IP** dan **5 req/jam/kontak**. Login dibatasi **5 kali/menit** per email+IP; ubah password **3 kali/jam**; pengajuan dispute **3 kali/hari**; pengajuan refund **5 kali/jam**; webhook Xendit **60 kali/menit/IP**. | `AppServiceProvider::configureRateLimiters()`, `OtpService`, & `OtpRegistrationFlowTest`. |
| **`NFR-SEC-03`** | Wajib 2FA Privileged | Akun `super_admin` tidak dapat memperoleh token operasional sebelum lulus verifikasi TOTP; token sementara `2fa-pending` kedaluwarsa dalam **5 menit**. | `AdminApiAuthController` & feature test admin auth. |
| **`NFR-SEC-04`** | Keamanan Upload Privat | Seluruh dokumen usaha dan bukti komplain maks **5 MB** (avatar **2 MB**), divalidasi ekstensi + `finfo` MIME server-side, dan disimpan di disk privat (`storage/app/private`). | `SecureFileUpload.php` & upload security tests. |
| **`NFR-REL-01`** | Integritas Transaksi | Registrasi OTP wajib membungkus pembuatan user & dispatch OTP dalam **1 transaksi DB atomik**; webhook Xendit wajib idempoten per `xendit_event_id`. | `OtpRegistrationFlowTest` & `XenditService` idempotency tests. |
| **`NFR-REL-02`** | Degradasi Anggun | Kegagalan penyedia AI (HTTP 429/timeout), gangguan Nominatim, atau kegagalan 1 kanal notifikasi tidak boleh membatalkan pesanan maupun menampilkan stack trace mentah; fallback otomatis ke kontak `cs@laundryku.test` atau form manual. | `AiAssistantTest`, `NotificationService`, & `AddressGeocodingTest`. |
| **`NFR-UX-01`** | Responsivitas & Aksesibilitas | UI web mengadopsi pendekatan *mobile-first* dengan ukuran sentuh interaktif minimal **44×44 px**, rasio kontras teks WCAG AA **>= 4,5:1**, teks body >= **16 px** di mobile, serta state baku *Skeleton Loading*, *EmptyState*, dan *ErrorState* di seluruh halaman data. | Pedoman [DESIGN-RULES.md](file:///c:/laragon/www/laundryku/DESIGN-RULES.md) & inspeksi UI. |
| **`NFR-MAINT-01`** | Pemeliharaan & Guard Test | Logika bisnis dipisahkan di lapisan Service (`app/Services/*`), otorisasi terpusat di 9 Policy (`app/Policies/*`), seluruh kode dilindungi **76 file PHPUnit tests** dengan `Database Guard` yang membatalkan eksekusi otomatis jika mengarah ke database produksi (`composer test`). | `composer test` (`bin/test-runner.php`) & `playwright test`. |

---

## Bab 6 — Sistem Desain UI/UX, Peta Route & Model Data Sistem

### 6.1 Sistem Desain Aktual Frontend (`DESIGN-RULES.md` + `globals.css`)

Antarmuka utama LaundryKu dibangun di atas **Next.js `16.3.5` + Tailwind CSS v4** dengan standar [DESIGN-RULES.md](file:///c:/laragon/www/laundryku/DESIGN-RULES.md) dan token tema pada [frontend/app/globals.css](file:///c:/laragon/www/laundryku/frontend/app/globals.css):

| Elemen Desain | Standar Implementasi Aktual | Pengecualian / Catatan Koeksistensi |
| :--- | :--- | :--- |
| **Palet Warna Utama** | Komposisi 60-30-10 berbasis **Navy** (`navy-950: #071324`, `navy-900: #0B192C`, `navy-800: #1E40AF`, `navy-700: #1D4ED8`), **Teal** (`teal-600: #0D9488`, `teal-500: #14B8A6`, `teal-400: #2DD4BF`), **Amber** (`amber-500: #F59E0B`, `amber-400: #FBBF24` terbatas 1–2 sorotan), **Sky/Slate** (`#F0F7FF`, `#F8FAFC`, `#FFFFFF`), teks utama `#0F172A`, dan warna semantik Success `#16A34A`, Warning `#F59E0B`, Danger `#DC2626`, Info `#2563EB`. | Halaman **`/admin/settings/ai`** memakai palet **Cyan `#0E7490` / `#155E75`** sesuai file token [DESIGN.md](file:///c:/laragon/www/laundryku/DESIGN.md). Sidebar Admin ([admin/layout.tsx](file:///c:/laragon/www/laundryku/frontend/app/admin/layout.tsx)) memakai latar `#0F172A`. Sejumlah view Blade legacy tetap mempertahankan warna lama untuk kompatibilitas backward. |
| **Tipografi** | Heading (`h1`–`h6`, `.font-heading`): **`Plus Jakarta Sans`** (`500–800`); Body, form, & tabel (`.font-sans`): **`Inter`** (`400–700`); Angka rupiah & jam wajib `.tabular-nums` + helper `formatRupiah()`. | Didefinisikan di [frontend/app/layout.tsx](file:///c:/laragon/www/laundryku/frontend/app/layout.tsx) via `next/font/google`. |
| **Komponen & Layout** | **31 file komponen UI reusable** di `frontend/components/ui/` (`Button`, `Input`, `OTPInput`, `ConfirmDialog`, `Modal`, `Table`, `StatusBadge`, `Timeline`, `Skeleton`, `EmptyState`, `ErrorState`, dll.) serta `PublicLayout`, `AuthLayout`, `DashboardLayout`, dan `AdminLayout`. | Halaman `/design-system` disediakan untuk dokumentasi visual komponen antarmuka. |
| **Ikonografi & Anti-Slop** | Menggunakan **Lucide React (`^1.47.0`)** dengan stroke konsisten dan melarang ornamen dekoratif berlebihan atau badge warna-warni tanpa fungsi semantik. | Diperkuat pada perancangan ulang Admin UI (termasuk `/admin/settings/ai`, `/admin/settings`, `/admin/audit-logs`). |

### 6.2 Peta Antarmuka Web (`frontend/app/`) & Grup Endpoint API (`/api/v1/*`)

| Portal | Rute Halaman Web Next.js | Grup Endpoint REST Laravel (`routes/api.php`) |
| :--- | :--- | :--- |
| **Publik & Customer** | `/`, `/auth`, `/stores`, `/stores/[id]`, `/stores/[id]/order`, `/orders`, `/orders/[id]`, `/chat`, `/chat/[id]`, `/notifications`, `/profile`, `/upgrade`, `/onboard`, `/design-system` | `/api/v1/auth/*` (`register`, `login`, `otp/verify`, `otp/send`, `logout`, `me`), `/api/v1/stores*`, `/api/v1/pricing/estimate`, `/api/v1/geocode/*`, `/api/v1/profile*`, `/api/v1/addresses*`, `/api/v1/orders*`, `/api/v1/conversations*`, `/api/v1/notifications*`, `/api/v1/upgrades/*`, `/api/v1/chatbot/*` |
| **Mitra / Store Admin** | `/mitra/login`, `/mitra/register`, `/mitra/status`, `/store`, `/store/orders`, `/store/orders/[id]`, `/store/services`, `/store/pricing`, `/store/payment`, `/store/couriers`, `/store/reports`, `/store/ledger`, `/store/complaints`, `/store/ratings`, `/store/chat`, `/store/profile`, `/store/settings` | `/api/v1/auth/mitra/*`, `/api/v1/mitra/*`, `/api/v1/store/*` (`dashboard`, `services`, `pricing-rules`, `ledger`, `reports`, `payout-account`, `confirm-cod`), `/api/v1/store-admin/*` (`couriers`, `ratings`, `complaints`) |
| **Kurir** | `/courier/login`, `/courier`, `/courier/tasks`, `/courier/orders` | `/api/v1/auth/kurir/*`, `/api/v1/courier/*` (`profile`, `tasks`, `claim`, `transition`, `confirm-cod`) |
| **Super Admin** | `/admin/login`, `/admin`, `/admin/orders`, `/admin/payments`, `/admin/stores`, `/admin/users`, `/admin/categories`, `/admin/settings/ai`, `/admin/settings`, `/admin/audit-logs` | `/api/v1/auth/admin/*`, `/api/v1/admin/*` (`overview`, `dashboard/metrics`, `orders`, `payments`, `stores`, `users`, `categories`, `platform-settings/commission`, `audit-logs`, `ai-settings`), serta `/api/ai/chat` untuk widget admin |
| **Webhook & Proxy** | `/api/auth/[portal]/[action]`, `/api/proxy/[...path]`, `/api/chat` | `POST /api/webhooks/xendit` (`throttle:webhook`) |

### 6.3 Model Data Inti MySQL 8 (58 Tabel Terverifikasi)

Basis data MySQL 8 dikelola melalui **74 file migrasi** yang membentuk **58 tabel** pada 6 kelompok domain (menggantikan catatan skema awal 8 tabel di `database/schema.sql`):
1. **Identitas, Autentikasi & RBAC (11 tabel):** `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `personal_access_tokens`, `otp_challenges`, `failed_login_attempts`, `password_reset_tokens`, `password_resets`, `sessions`.
2. **Pelanggan, Mitra Toko, Katalog & Kurir (13 tabel):** `customers`, `customer_addresses`, `stores`, `store_applications`, `store_documents`, `store_staff`, `store_settings`, `store_payout_accounts`, `services`, `service_prices`, `laundry_packages`, `couriers`, `courier_gps`.
3. **Pesanan & Penugasan Logistik (4 tabel):** `orders`, `order_items`, `order_status_history`, `courier_tasks`.
4. **Pembayaran, COD, Settlement, Ulasan & Sengketa (11 tabel):** `payments`, `payment_events`, `xendit_webhooks`, `cod_collections`, `refunds`, `commission_settings`, `settlement_periods`, `settlement_items`, `disputes`, `dispute_events`, `reviews`.
5. **Komunikasi, Notifikasi & Asisten AI (10 tabel):** `conversations`, `chat_messages`, `notifications`, `notification_preferences`, `whatsapp_logs`, `ai_conversations`, `ai_messages`, `ai_chat_sessions`, `ai_chat_messages`, `ai_usage_logs`.
6. **Tata Kelola Sistem, Cache & Antrian (9 tabel):** `settings`, `system_configurations`, `audit_logs`, `dashboard_metric_caches`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. *(Total keseluruhan = 58 tabel unik)*.

---

## Bab 7 — Matriks Keterlacakan & Daftar Utang Teknis Terkendali

### 7.1 Traceability Matrix (`User Story -> FR -> Implementasi -> Verifikasi`)

| User Story | ID FR | Service / Controller / Endpoint Utama | Halaman Frontend | Bukti Pengujian Otomatis |
| :--- | :--- | :--- | :--- | :--- |
| `US-01` | `FR-01` | `OtpRegistrationApiController`, `OtpService`, `AdminApiAuthController`, `bff.ts` | `/auth`, `/mitra/login`, `/courier/login`, `/admin/login` | `OtpRegistrationFlowTest`, `Phase1FoundationAuthTest`, `SanctumFoundationTest`, `DashboardTwoFactorTest`, `RolePortalIsolationTest` |
| `US-02` | `FR-02` | `CustomerController`, `NominatimGeocodingService`, `User` (`display_email`) | `/profile`, `/stores` | `AddressGeocodingTest`, `AddressLocationTest`, `UserProfilePlaceholderEmailTest` |
| `US-03` | `FR-03` | `StoreController`, `StoreSearchService`, `StoreMap.tsx` | `/`, `/stores`, `/stores/[id]` | `StoreSearchTest`, `StoreLocationCoordinatesAndRadiusTest`, E2E `index.spec.js` |
| `US-04` | `FR-04` | `MitraApiAuthController`, `CustomerController::apply*`, `SecureFileUpload` | `/mitra/register`, `/upgrade`, `/admin/stores` | `MitraRegisterDirectTest`, `StoreOnboardingTest`, `MitraOnboardingE2ETest`, `CourierApplicationFlowTest` |
| `US-05` | `FR-05` | `OrderController`, `OrderService`, `OrderStatus` (`19` enum), `OrderPolicy` | `/stores/[id]/order`, `/orders/[id]`, `/store/orders/[id]` | `OrderServiceTest`, `OrderStatusHistoryRecordTest`, `OrderFinancialBreakdownTest`, `OrderLifecycleAndCourierReassignmentSimulationTest`, E2E `order-lifecycle.spec.ts` |
| `US-06` | `FR-06` | `PaymentController`, `XenditService`, `XenditWebhookController`, `SettlementService` | `/orders/[id]`, `/store/payment`, `/store/ledger`, `/admin/settings` | `PaymentTest`, `PaymentAuthorizationTest`, `SettlementTest`, `SettlementExclusionTest`, `RefundTest` |
| `US-07` | `FR-07` | `CourierPortalController`, `CourierAssignmentService`, `GpsTrackingService` | `/courier`, `/courier/tasks`, `/courier/orders` | `CourierPortalAuthorizationTest`, `CourierPortalTransitionTaskTest`, `CourierAssignmentTest`, `CourierDashboardTest` |
| `US-08` | `FR-08` | `ChatController`, `NotificationService`, `ReviewService`, `DisputeService` | `/chat`, `/store/chat`, `/notifications`, `/store/complaints`, `/store/ratings` | `CustomerStoreConversationTest`, `NotificationTest`, `WhatsAppNotificationTest`, `ReviewDisputeTest`, `DisputeRefundTest` |
| `US-09` | `FR-09` | `MitraPortalController` (`services`, `pricing-rules`, `reports`, `couriers`, `ledger`) | `/store/*` (13 rute halaman toko) | `StorePricingAndReportsTest`, `StorePaymentMethodsTest`, `StoreDashboardMetricsTest`, `DashboardTenantIsolationTest` |
| `US-10` | `FR-10` | `AdminPortalController` (`metrics`, `stores`, `users`, `categories`, `commission`, `auditLogs`, `aiSettings`) | `/admin/*` (9 menu utama Admin) | `AdminDashboardMetricsTest`, `AdminPortalApiTest`, `AdminSettingsTest`, `AdminAiSettingsTest`, `DashboardResponseRegressionTest` |
| `US-AI` | Bab 4 | `AiIntentRouter`, `AiAssistantService`, `AiKnowledgeBaseService`, `AiToolRegistry` (`33` tools), `OpenAiClient` | Widget `AiChatWidget` di seluruh portal + `/admin/settings/ai` | `AiHoldOutEvaluationTest` (`165/165`), `AiAssistantTest`, `SuperAdminAiAssistantTest`, `AiDataQueryTest`, `AiRoleFunctionCallingTest`, `AiParaphraseTest` |

### 7.2 Daftar Utang Teknis & Keterbatasan Terbuka (`docs/technical-notes.md` & `OPEN-ISSUES.md`)

PRD ini secara transparan mencatat item teknis yang belum selesai dan dijadwalkan untuk iterasi berikutnya:
1. **`T011` Penyatuan Dua Otoritas Role (*Split-Brain*):** Kolom `users.role` masih hidup berdampingan dengan tabel relasional RBAC (`roles`, `user_roles`); rencana deprekasi kolom datar `users.role` menuju Policy + tabel RBAC sebagai satu-satunya SSOT telah terdokumentasi.
2. **`T009` Duplikasi Penutupan Tugas Kurir:** Logika transisi selesai `delivered` pada tugas kurir masih tersebar di `CourierPortalController::transitionTask` dan `OrderService::transition`.
3. **`T010` Penundaan Model `$casts` Enum:** Cast Eloquent langsung untuk `OrderStatus` dan `CourierTaskStatus` masih ditunda demi menjaga kompatibilitas test dan query builder string yang ada.
4. **`T012` Refaktor Halaman `/stores`:** Berkas `frontend/app/stores/page.tsx` masih sepanjang ±700 baris dan belum memiliki cakupan unit test UI terisolasi.
5. **Validasi Berat Transisi `awaiting_price_approval` & Field `Bug E`:** Perhitungan `order_items.quantity_actual` multi-item serta kewajiban pengisian `actual_weight > 0` sebelum perpindahan ke `awaiting_price_approval` dijadwalkan untuk penguatan lanjutan.
6. **Sinkronisasi Artefak Repo Pendukung:** Berkas [database/schema.sql](file:///c:/laragon/www/laundryku/database/schema.sql) (masih berisi 8 tabel awal vs 58 tabel aktual di migrasi), [README.md](file:///c:/laragon/www/laundryku/README.md), dan [AGENTS.md](file:///c:/laragon/www/laundryku/AGENTS.md) perlu diselaraskan pada tugas terpisah agar tidak merujuk ke stack fase awal.

# Technical Notes — Laundryku Project

## Technical Debt & Business Placeholders

| ID | Area | Issue | Status | Notes |
|----|------|-------|--------|-------|
| T001 | Payment | COD_MAX_AMOUNT belum ditentukan | Placeholder | Akan dikonfigurasi di Tahap 21 (Business Logic) |
| T002 | Pricing | MARKETPLACE_COMMISSION masih placeholder | Placeholder | Akan ditetapkan di Tahap 21 |
| T003 | Store | Upload dokumen store (KTP, dll) ditunda | Scheduled | Ditunda ke Tahap 19 (Security Hardening) |
| T004 | Search | Sponsored store placement (is_sponsored) sudah ada kolom, tapi logic belum aktif | Pending | Menunggu keputusan bisnis final |
| T005 | Auth | Guard API sempat pakai 'auth' biasa sebelum Sanctum rencana diinstall | Deferred | Akan direview di Tahap 17 atau saat dibutuhkan API eksternal; keputusan sadar untuk tidak upgrade PHP sekarang demi menghindari resiko infrastruktur yang tidak perlu di tengah development. |
| T006 | Infrastructure | PHP 8.3.33 vs Laravel 12 dependency (symfony/css-selector requires PHP ≥ 8.4) | Deferred | Deferred — akan direview di Tahap 17 atau saat dibutuhkan API eksternal, keputusan sadar untuk tidak upgrade PHP sekarang demi menghindari resiko infrastruktur yang tidak perlu di tengah development. |
| T007 | Infrastructure | Queue driver dependency: `QUEUE_CONNECTION=redis` butuh PHP Redis extension (`ext-redis`) | Resolved | Di local/staging default menggunakan `QUEUE_CONNECTION=database` (via MySQL `jobs` table). Jika di production ingin menggunakan `QUEUE_CONNECTION=redis`, pastikan PHP Redis extension (`ext-redis`) sudah terpasang dan aktif di server production SEBELUM mengubah .env, atau tetap pertahankan `QUEUE_CONNECTION=database`. NotificationTrigger sudah dibentengi dengan try-catch defensif sehingga queue failure tidak membatalkan transaksi bisnis. |
| T008 | Security | Akun testing Platform Owner (`admin@laundryku.test` / `admin12345`) | Security Caution | Akun ini dibuat HANYA untuk kebutuhan pengujian lokal/staging. DILARANG KERAS menggunakan kredensial ini di production. Saat deployment go-live, akun ini WAJIB dihapus atau diubah password-nya menjadi string acak yang kuat (32+ karakter) dan diwajibkan mengaktifkan 2FA via Google Authenticator. |
| T009 | Courier / Order | Logika penutupan task duplikat di `CourierPortalController` dan `OrderService` | Resolved | Disatukan ke `CourierTask::closeActiveFor` di Tahap 3b (commit `2f5b327`). |
| T010 | Enum / Model | Penundaan Eloquent `$casts` Enum untuk `OrderStatus` dan `CourierTaskStatus` | Deferred | Magic string diganti enum value di file kurir/order; `$casts` model ditunda agar tidak memecah kueri mentah. |
| T011 | Auth / RBAC | Dua sistem role tidak sinkron: `users.role` vs tabel `roles` | Pending Refactoring | Kolom flat (`super_admin`, `store_admin`, `courier`, `customer`) vs tabel RBAC (`platform_owner`, `platform_admin`, `store_owner`, `store_staff`, `courier`, `customer`). |

## Decision Log

- 2026-08-30: Memutuskan untuk menggunakan `auth` middleware (web guard) untuk API routes sementara, karena Sanctum tidak bisa diinstall akibat PHP 8.3 < 8.4.
- 2026-08-30: `is_sponsored` kolom sudah ditambahkan ke stores, tapi ranking tidak menggunakannya sampai ada keputusan bisnis.
- 2026-08-30: File upload dokumen store ditunda ke Tahap 19 (Security Hardening) untuk mengurangi kompleksitas di awal.
- 2026-08-30: Keputusan untuk menunda upgrade PHP ke 8.4 dan instalasi Sanctum hingga Tahap 17 atau saat benar-benar dibutuhkan, demi stabilitas development.
- 2026-09-02: Konfigurasi antrean lokal menggunakan `QUEUE_CONNECTION=database` untuk menghindari fatal error `Class "Redis" not found` pada environment tanpa ekstensi PHP Redis. Seluruh notification dispatch dibentengi try-catch sentral. Untuk production go-live, wajib verifikasi ketersediaan ekstensi Redis sebelum mengaktifkan driver redis.
- 2026-09-02: Dibuat command `php artisan platform:owner` dan `php artisan store:approve` untuk keperluan internal provisioning dan review toko. Kredensial default testing (`admin@laundryku.test` / `admin12345`) dicatat secara tegas hanya untuk lingkungan lokal/dev.
- 2026-10-02 (Fase 15C): Memperbaiki kompatibilitas dependency dengan mengunci platform PHP 8.3.30 dan Laravel 12 (Symfony 7.4). Mengimplementasikan logging mendalam pada alur registrasi & OTP (`AuthController`, `OtpService`, `FontneService`), normalisasi nomor WA, migrasi properti `$fillable` Eloquent pada model User, debug endpoint `/api/v1/debug/fonnte` dan `/api/v1/debug/otp`, serta peningkatan penanganan pesan error pada frontend `lib/auth.ts`.
- 2026-10-08 (Super Admin Suite & Real Data Integration):
  1. Login Terisolasi Super Admin: Dibuat endpoint terpisah `POST /api/v1/admin/login` & `POST /api/v1/admin/logout` menggunakan field `username` + `password` dengan audit logging (`ADMIN_LOGIN_SUCCESS`, `ADMIN_LOGIN_FAILED`, `ADMIN_LOGIN_REJECTED`, `ADMIN_LOGOUT_SUCCESS`), proteksi rate limit (5x/menit), serta pencegahan kebocoran enumerasi username. Ditambahkan migration kolom `username` (unique, nullable) pada tabel `users`. Ditambahkan komentar `TODO: [2FA]` setelah verifikasi password sebelum token diterbitkan.
  2. Idempotent Seeder: `SuperAdminSeeder` membaca variabel dev dari `.env` (`ADMIN_USERNAME` dan `ADMIN_PASSWORD`) dengan password di-hash secara aman dan peringatan wajib ganti password sebelum production.
  3. Redesign UI Tanpa "AI Slop": Menggantikan seluruh emoji dengan Lucide React icons seragam (16-18px, stroke 1.5, warna netral), palet slate + aksen brand #1A3A5C, tata letak padat (compact data table, tabular-nums), penghapusan pill/badge dekoratif, drawer responsive mobile, serta modularitas komponen bersama (`StatCard`, `DataTable`, `StatusText`, `AdminHeader`).
  4. Real Database Data Connection: Seluruh widget dan metrik dashboard terhubung ke MySQL database asli via `AdminMetricsService` (Asia/Jakarta timezone), menghitung GMV riil dari order berstatus `COMPLETED` (`SUM(final_amount)`), snapshot komisi platform, distribusi pesanan, dan komposisi pengguna. Disediakan endpoint dan halaman Pesanan (`/admin/orders`) serta Transaksi & Pembayaran (`/admin/payments`). Integrasi hook otomatis pencatatan `AuditLogService` pada siklus hidup pesanan, pembayaran webhook Xendit, dan operasional administratif. Auto-refresh polling 30 detik pada dashboard.
- 2026-10-10 (Perbaikan Pusat Bantuan Otomatis / AI Chatbot):
  1. Diagnosis: Menemukan akar penyebab chatbot selalu menampilkan fallback neutral "Layanan bantuan otomatis sedang tidak tersedia. Hubungi CS." yaitu:
     - Domain base URL di `.env` salah (`openrouter.io` mengembalikan 404, seharusnya `openrouter.ai`).
     - Nama model tidak valid (`qwen/qwen3.8-27b:free`).
     - API Key terpotong/invalid dengan elipsis literal `...` (`sk-or-v1-5b2...40e`, hanya 18 karakter) yang menghasilkan error 401 Unauthorized dari provider.
     - `AI_MOCK_IN_LOCAL` belum diaktifkan di `.env` sehingga kegagalan provider langsung melempar teks fallback statis kaku alih-alih respons simulasi lokal cerdas.
  2. Implementasi Next.js Route Handler: Menambahkan `frontend/app/api/chat/route.ts` server-side dengan system prompt panduan platform LaundryKu, error logging transparan (`console.error` status code, response body, target endpoint aman tanpa expose API key), sanitasi format input/output, dan penanganan graceful fallback ke local knowledge assistant bila API key eksternal bermasalah.
  3. Peningkatan Backend AiChatService: Menambahkan auto-normalisasi domain provider (`openrouter.io` -> `openrouter.ai`), validasi deteksi kunci terpotong/elipsis, serta detail error logging terperinci.
  4. Pengujian Terbukti: Diverifikasi melalui skrip mandiri `scripts/test-ai.mjs`, endpoint `/api/chat` (Next.js), dan `/api/v1/chatbot/guest/ask` (Laravel) dengan pertanyaan "Bagaimana cara pesan laundry?", seluruhnya berhasil merespons instruksi lengkap pemesanan platform secara ramah dan solutif.
- 2026-10-10 (Revisi UI/UX AI Chatbot Assistant — Modern, Clean & Professional):
  1. Penghapusan Elemen Slop: Seluruh chip/pill suggested questions dan horizontal scrolling carousel dihapus sepenuhnya dari welcome state dan chat area tanpa meninggalkan sisa margin/padding.
  2. Redesign Icon & Floating Button: Dibuat icon SVG custom `AiAssistantIcon` (siluet chat bubble presisi dengan diamond spark concierge 4-sudut) dengan micro-interaction hover/scale halus. Floating chat button dipertahankan di pojok kanan bawah dengan styling solid dark slate (`#0F172A`), online indicator (dot hijau emerald), serta kontras tinggi.
  3. Window & Message Hierarchy: Modal chat diperbarui dengan palet netral profesional, typography Plus Jakarta Sans yang tajam, bubble chat user berkontras tinggi (`#0F172A`), bubble asisten bersih (`bg-white border-slate-200`), typing indicator tenang, dan layout input yang nyaman di desktop & mobile. Validasi interaksi diverifikasi via browser subagent.
- 2026-10-10 (Perbaikan Responsivitas, System Prompt & Konteks Marketplace AI Assistant):
  1. Akar Penyebab Greeting Statis: Evaluasi menemukan bahwa `getLocalKnowledgeReply` di `route.ts` dan `generateFallbackResponse` di `AiChatService.php` menjatuhkan sebagian besar pertanyaan pengguna ke blok default sapaan/greeting template karena regex kata kunci yang terlalu sempit. Selain itu, multi-turn history sebelumnya tidak dikirimkan dari frontend `guestAskChatbot` ke API backend.
  2. Multi-turn History Pipeline: Memperbarui `frontend/lib/chatbot.ts` dan `AiChatWidget.tsx` untuk menyertakan riwayat percakapan (`messages: { role, content }[]`) dalam payload request ke `/api/chat` dan `/chatbot/guest/ask`, serta memperbarui `ChatbotController.php` untuk memvalidasi dan meneruskannya ke `AiChatService::chat`.
  3. 16 Aturan Perilaku & System Prompt Komprehensif: Mengintegrasikan 16 aturan perilaku resmi dan basis pengetahuan komprehensif LaundryKu mencakup Customer (Passwordless OTP /auth, alur pesanan, timbangan riil, hak pembatalan tanpa biaya, pelacakan /orders, metode bayar QRIS/VA/COD, kendala transaksi pending, komplain 1x24 jam), Mitra Toko (/upgrade, verifikasi 1x24 jam, katalog layanan, dashboard toko), Kurir (/upgrade, syarat SIM C/KTP/motor, penjemputan/pengantaran, kendala alamat), serta penolakan santun topik di luar konteks.
  4. Pengujian 12 Skenario Lengkap: Teruji 12 dari 12 skenario secara otomatis via `frontend/scripts/test-12-scenarios.mjs` dan verifikasi visual end-to-end via Browser Subagent tanpa regresi UI.
- 2026-10-10 (Perbaikan Bug AI Chatbot untuk Pengguna Guest / Belum Login):
  1. Akar Penyebab Bug Guest: Pada `AiChatWidget.tsx` fungsi `handleSendMessage`, pemanggilan `guestAskChatbot` secara keliru bersarang di dalam blok `else if (isAuth && !currentSession)` sehingga percabangan `else` untuk `!isAuth` (guest) tidak pernah dieksekusi. Akibatnya, pesan pengguna guest optimis muncul namun eksekusi berhenti tanpa pernah meminta jawaban dari API atau menampilkan respons asisten.
  2. Pemisahan Alur Guest & Autentikasi Bersih: Memperbaiki percabangan kontrol alur sehingga ketika `!isAuth`, `handleSendMessage` langsung mengeksekusi `guestAskChatbot` dengan riwayat percakapan sesi lokal (`priorHistory`), tanpa token autentikasi palsu, tanpa mengarang user_id, dan tanpa persistent session di database.
  3. Proteksi Akses Data Pribadi & Zero Trust: Backend tetap menjaga ketat endpoint privat (`/api/v1/chatbot/sessions` dan `/api/v1/orders` tetap mewajibkan auth:sanctum dan menolak akses guest dengan 401). Bila guest menanyakan data pesanan pribadi, asisten AI mengarahkan login di `/auth` dan melihat `/orders` tanpa mengarang nomor pesanan atau status palsu.
  4. Verifikasi Browser & API Lengkap: Teruji lulus pada script otomatis `test-guest-chatbot.mjs` dan terverifikasi secara visual pada sesi browser live via Browser Subagent (`guest_chatbot_test`).
- 2026-10-10 (Upgrade AI Assistant — Pemahaman Bahasa Natural, Slang, Typo & Knowledge Marketplace Laundry):
  1. Akar Masalah Keterbatasan & Klarifikasi Berlebihan:
     - Logika pencocokan pada fallback (`route.ts` dan `AiChatService.php`) sebelumnya mengandalkan pencarian substring formal yang kaku tanpa normalisasi istilah bahasa percakapan sehari-hari. Pertanyaan singkat ("Mau batalin", "Kurir blm dateng", "Uang gue balik ga?", "Gue mau komplain") jatuh ke fallback generic ("apakah Anda memerlukan informasi spesifik seputar alur pemesanan...?") yang memaksa pengguna mengulang pertanyaan.
     - Substring collision: kata "alamat" mengandung substring "lama", menyebabkan pertanyaan seputar pergantian alamat salah diklasifikasikan menjadi pertanyaan komplain durasi pengerjaan cucian.
     - Pertanyaan jam buka toko ("buka pas idul fitri") salah diklasifikasikan menjadi pendaftaran mitra karena pencocokan kata "buka" dan "toko".
  2. Implementasi Normalizer Bahasa Natural & Slang:
     - Menambahkan fungsi pembersih tanda baca dan kamus normalisasi bahasa percakapan/slang/typo Indonesia (`normalizeUserQuery` di TypeScript dan `normalizeQuery` di PHP). Contoh pemetaan: `blm`/`blom`/`belom` -> `belum`, `dateng`/`dtg` -> `datang`, `batalin`/`cancelin` -> `batal`, `duit`/`fulus` -> `uang`, `balikin`/`balik` -> `refund`, `sobek`/`robek` -> `rusak`, `lelet`/`lambat` -> `lama`, `almt` -> `alamat`, dll.
     - Menggunakan word boundary regex (`/\blama\b/`) untuk mencegah tabrakan substring kata dasar.
  3. Knowledge Base Solutif 7 Domain Laundry:
     - A. Pesanan & Pembatalan: Alur pemesanan `/stores`, hak pembatalan tanpa biaya di tahap `AWAITING_PRICE_APPROVAL`, koordinasi pembatalan via `/chat` di tahap `PROCESSING`, estimasi pengerjaan Reguler (1-3 hari) vs Express (6-24 jam).
     - B. Kurir & Pengantaran: Penanganan kurir belum datang (`PENDING_PICKUP` vs `PICKUP_ASSIGNED`), koordinasi alamat/patokan via `/chat`, syarat pendaftaran kurir di `/upgrade` (SIM C, KTP, motor, nomor WhatsApp).
     - C. CS & Komplain: Alur komplain resmi via `/orders` maksimal 1x24 jam pasca `COMPLETED` dengan foto bukti, garansi cuci ulang/kompensasi, WhatsApp CS platform di `/profile`.
     - D. Pembayaran & Refund: QRIS, VA, COD; alur transaksi gagal / expired (bayar ulang di `/orders`), transaksi pending (jeda webhook 1-5 menit; mutasi rekening tanpa bayar ganda); alur refund via Xendit 1-3 hari kerja.
     - E. Akun & Pelanggan: Passwordless OTP di `/auth`, pelacakan mandiri di `/orders`, privasi Zero Trust untuk tamu.
     - F. Mitra Toko: Syarat pendaftaran mitra di `/upgrade`, verifikasi 1x24 jam kerja, manajemen katalog layanan di dashboard toko.
     - G. Pertanyaan Belum Diketahui / Toko Spesifik: Memberikan arahan jujur dan solutif ke profil toko di `/stores` dan komunikasi langsung melalui `/chat` tanpa mengarang data fiktif.
  4. Pengujian & Verifikasi:
     - 16 skenario wajib (termasuk variasi typo, bahasa gaul, multi-turn follow-up "Terus syaratnya apa?", dan pertanyaan tidak diketahui) lulus 100% pada `frontend/scripts/test-16-scenarios.mjs` (Next.js `/api/chat`).
     - PHPUnit Feature Test `NaturalLanguageAiTest` lulus 100% (57 assertions) pada backend Laravel (`artisan test --filter=NaturalLanguageAiTest`).
     - Seluruh suite AI backend (51 tests, 243 assertions) lulus hijau tanpa regresi.
- 2026-10-11 (Badge Sidebar Toko, Perbaikan 404 Halaman Tugas Kurir, & User Mode Switcher Kurir):
  1. Badge Sidebar Toko:
     - Endpoint terpusat `GET /api/v1/store/sidebar-counts` (auth:sanctum, store_admin, store scope) mengembalikan `pelamar_kurir_pending`, `pesanan_baru` (`PENDING_PICKUP`), dan `unread_chat`.
     - Dihitung efisien via database SQL count.
     - Komponen frontend `DashboardLayout.tsx` menampilkan badge angka slate-900 di atas latar slate-100 dengan radius 6px (`rounded-[6px]`), tanpa gradasi atau pill warna mencolok, polling 30s serta refresh seketika via event bus saat pelamar disetujui/ditolak. Badge disembunyikan otomatis jika bernilai 0.
  2. Perbaikan 404 Tugas Pengantaran Kurir:
     - Dibuat halaman `frontend/app/courier/tasks/page.tsx` lengkap dengan tab filter (Tugas Aktif, Tugas Tersedia, Semua Tugas, Riwayat Selesai), modal konfirmasi aksi sesuai state machine (ambil tugas, mulai perjalanan, tiba, selesaikan tugas), skeleton loader, dan empty state ramah pengguna.
     - Halaman `app/courier/orders/page.tsx` diarahkan redirect ke `/courier/tasks` untuk konsistensi tautan lama.
  3. Mode Switcher & Proteksi Role Kurir:
     - Ditambahkan kolom `active_mode` (nullable, customer|store|courier) pada tabel `users` via migrasi baru `2026_10_11_000001_add_active_mode_to_users_table.php`.
     - Endpoint `POST /api/v1/me/mode` memvalidasi ketersediaan role/penugasan kurir aktif (`courier_store_assignments.status = 'active'`) dan mencatat audit log `USER_MODE_SWITCHED` (metadata from & to).
     - Dibuat middleware `EnsureActiveMode:courier` yang melindungi rute grup `/api/v1/courier/*`. Bila penugasan kurir berakhir, akun otomatis diturunkan ke `customer` dengan respons 403 `MODE_REVOKED`.
     - Frontend `PublicLayout.tsx` dan `DashboardLayout.tsx` mempertahankan ketersediaan link "Dashboard Kurir" dan switcher akun selama user memiliki hak kurir di `available_modes`, sehingga pengguna dapat berpindah bebas antara marketplace dan dashboard kurir tanpa logout.
  4. Pengujian & Verifikasi:
     - Seluruh 252 tests PHPUnit backend lulus tanpa regresi (`php artisan test`).
     - TypeScript typecheck (`npx tsc --noEmit`), linter (`npm run lint`), dan produksi Next.js build (36 static & dynamic pages) seluruhnya lulus dengan exit code 0.
- 2026-10-17 (Fase 1: Fondasi, Multi-Guard Auth & Role Isolation):
  1. Multi-Guard Architecture: 4 guard independen dikonfigurasi di config/auth.php: web (Customer / CustomerUser), mitra (Store Admin / MitraUser), kurir (Kurir / CourierUser), dan admin (Super Admin / AdminUser). Setiap guard memiliki provider model Eloquent ter-scope dengan global scope role.
  2. Strict Role Isolation: Fitur switch-role dilarang keras dan dihapus sepenuhnya (rute /switch-role mengembalikan 404). Setiap akun terikat permanen pada perannya via kolom role enum (super_admin, store_admin, courier, customer) dan indeks unik gabungan (email, role) sehingga email yang sama dapat mendaftar peran berbeda secara terisolasi tanpa tabrakan.
  3. Pintu Masuk Terpisah & Autentikasi:
     - Customer: /login, /register (redirect: /orders).
     - Mitra: /mitra/login, /mitra/register (redirect: /mitra/dashboard).
     - Kurir: /kurir/login (tanpa registrasi publik, redirect: /kurir/dashboard).
     - Super Admin: /admin/login (wajib Google2FA TOTP verification, redirect: /admin/dashboard).
     - Percobaan login silang pintu ditolak secara otomatis dengan pesan error scoped.
  4. Fondasi Database & Ledger COD:
     - Tabel settings (key-value ber-tipe dengan caching Redis otomatis via model Setting).
     - Tabel cod_collections (pencatatan setoran tunai kurir dengan lifecycle status PENDING, COLLECTED, DEPOSITED, RECONCILED, dan verifikasi OTP).
  5. UI & Layouts:
     - Layout terisolasi untuk tiap portal: layouts/app.blade.php, layouts/mitra.blade.php, layouts/kurir.blade.php, dan layouts/dashboard.blade.php.
     - Palet warna konsisten sesuai token brand: #1A3A5C, #4A90D9, #2ECC71, #F5F7FA, #2C3E50.
  6. Pengujian & Verifikasi:
     - Phase1FoundationAuthTest (12 tests, 65 assertions) — 100% lulus.
     - Seluruh test suite (268 tests, 971 assertions) — 100% lulus tanpa kegagalan.

- 2026-10-17 (Perbaikan Bug Login Akun Lama & Sinkronisasi Role Idempoten):
  1. Akar Masalah Kontak atau password salah:
     - Akun lama hasil dump/prototype sebelumnya memiliki role customer di database lama meskipun memiliki relasi toko/kurir, sehingga scope query MitraUser dan CourierUser menolak login mereka di portal mitra/kurir.
     - Akun lama sempat tidak ada di tabel users aktif pasca inisialisasi skema Phase 1.
     - Beberapa akun lama dari prototype awal memiliki password NULL di database asal.
     - AuthController sebelumnya memanggil Auth::attempt() tanpa binding eksplisit ke guard web.
     - File terjemahan lang/id/auth.php dan lang/en/auth.php belum mendefinisikan pesan failed terstandarisasi (Kontak atau password salah).
  2. Perbaikan & Sinkronisasi:
     - Dibuat command idempoten php artisan auth:sync-roles (SyncUserRolesCommand) yang memulihkan akun lama tanpa mengubah/mereset password yang ada, mensinkronkan users.role berdasarkan kepemilikan stores/couriers/user_roles, dan menautkan pivot role.
     - AuthController diperketat secara eksplisit menggunakan Auth::guard(web)->attempt(), Auth::guard(web)->user(), dan Auth::guard(web)->logout().
     - DefaultUsersSeeder dibentengi agar hanya berjalan di environment local dan testing.
     - File bahasa lang/id/auth.php dan lang/en/auth.php dikonfigurasi dengan pesan kegagalan Kontak atau password salah.
  3. Verifikasi:
     - Dibuat feature test RoleIsolatedLoginBugTest (9 tests, 44 assertions) — 100% lulus.
     - Seluruh test suite (277 tests, 1015 assertions) — 100% lulus hijau.

- 2026-10-17 (Fase 2: Laravel Sanctum API Auth, RBAC Middleware & Portal Isolation):
  1. Integrasi Resmi Laravel Sanctum & Platform PHP 8.3.30:
     - Sanctum terpasang via Composer resmi (v4.3.3) dengan config.platform.php = 8.3.30.
     - Tabel personal_access_tokens termigrasi.
     - Relation::morphMap di AppServiceProvider memetakan scoped model aliases (CustomerUser, MitraUser, CourierUser, AdminUser) ke App\Models\User::class untuk menjaga integritas polymorphic tokenable_type.
  2. Dua Lapis Validasi Autentikasi & RBAC (Token Ability + Real-Time DB Role Check):
     - Dibuat middleware EnsureUserRole (alias 'role') yang memvalidasi kolom users.role langsung dari database secara real-time pada setiap request terproteksi.
     - Setiap rute privat API memvalidasi token ability (ability:role:<expected_role>) DAN database role (role:<expected_role>). Jika role di DB berubah atau dimanipulasi, token langsung ditolak dengan 403 Forbidden.
  3. Isolasi Multi-Tenant Portal Mitra:
     - Mitra store access mencakup Store Owner (stores.owner_user_id) dan Store Staff (store_staff.user_id).
     - Restriksi toko pending/submitted: Mitra dengan toko belum di-approve dapat mengakses profil toko, namun dilarang mengakses pesanan/operasional (403 Forbidden).
  4. Autentikasi 2FA Dua Langkah:
     - Super Admin (wajib) dan Mitra dengan 2FA aktif: Tahap 1 menerbitkan token sementara dengan ability terbatas ['2fa-pending'] (masa berlaku 5 menit).
     - Tahap 2 (/api/v1/auth/admin/2fa dan /api/v1/auth/mitra/2fa) memvalidasi TOTP Google Authenticator dan menukar token sementara dengan full token (role:super_admin / role:store_admin).
  5. Siklus Hidup Token & Rate Limiting:
     - Re-login otomatis mencabut token lama untuk role tersebut (satu sesi token aktif per role).
     - Logout mencabut token saat ini via $user->currentAccessToken()->delete().
     - Semua endpoint auth API dilindungi rate limiter throttle:login (5 percobaan per menit).
  6. Pengujian & Verifikasi:
     - Feature test baru ApiAuthAndPortalTest (15 tests, 86 assertions) — 100% lulus.
     - Seluruh test suite (294 tests, 1109 assertions) — 100% lulus tanpa kegagalan.

- 2026-10-18 (Fase 3: Pintu Autentikasi Terisolasi Next.js, BFF dan Cookie httpOnly per Portal):
  1. Arsitektur BFF dan Cookie httpOnly Terisolasi:
     - Dibuat helper server-side frontend/lib/bff.ts dan catch-all proxy frontend/app/api/proxy/[...path]/route.ts yang memvalidasi Origin/CSRF dan membatasi akses melalui whitelist endpoint.
     - Token otentikasi dipindahkan sepenuhnya dari localStorage ke cookie httpOnly, SameSite=Lax, Path=/, secure (production).
     - Nama cookie terisolasi per portal: laundryku_customer_token, laundryku_mitra_token, laundryku_courier_token, laundryku_admin_token, serta cookie sementara laundryku_admin_temp_token dan laundryku_mitra_temp_token (berumur 5 menit untuk 2FA).
     - Token Laravel tidak pernah diekspos ke respon JSON browser atau JavaScript klien.
  2. Pintu Masuk Next.js Mandiri per Aktor:
     - Customer: /auth (tetap mempertahankan registrasi OTP WhatsApp/Email, login sandi, verifikasi OTP, resend).
     - Mitra: /mitra/login dan /mitra/register (registrasi toko baru menghasilkan status submitted/pending, dilengkapi tampilan /mitra/status dan dukungan 2FA).
     - Kurir: /courier/login (tanpa registrasi publik, langsung mengarah ke /courier/tasks).
     - Super Admin: /admin/login (alur 2 langkah wajib Google Authenticator 2FA TOTP sebelum masuk ke /admin).
  3. Strict Role Isolation dan Multi-Session Coexistence:
     - Percobaan login kredensial salah role di setiap portal ditolak dengan pesan generic terstandarisasi (Kontak atau password salah).
     - Sesi customer dan mitra/admin dapat hidup bersamaan pada browser yang sama tanpa saling mengganggu. Logout pada satu portal hanya menghapus cookie dan mencabut token portal tersebut.
  4. Pengujian dan Verifikasi:
     - Seluruh test suite Laravel (300 passed, 1142 assertions).
     - Next.js build produksi berhasil untuk 40 rute tanpa error.
     - Pengujian UI browser subagent end-to-end terverifikasi untuk seluruh portal (Customer, Mitra, Kurir, Super Admin).

- 2026-10-18 (AI Chatbot 3-Layer Intent Routing, Paraphrase Understanding & Real Database Integration):
  1. Arsitektur 3 Lapisan (3-Layer Routing Engine):
     - Lapisan 1 (Rule-Based Scoring Engine): Normalisasi teks UTF-8, stripping emoji/tanda baca, ekspansi kamus slang & singkatan Indonesia (gw, lu, udh, blm, brp, nyuci, kelar, byr, dll.), toleransi typo fuzzy Levenshtein pada kata kunci kanonik domain (`CANONICAL_KEYWORDS`), preservasi token nomor pesanan (`ORD-...`), deteksi compound multi-intent, dan follow-up percakapan pendek berbasis sesi ("kalau yang selesai?", "terus bayarnya?", "yang kemarin?"). Mengonsumsi 0 token model LLM dan dieksekusi dalam 15-30ms.
     - Lapisan 2 (Model Router Cadangan): Prompt JSON ketat, temperature 0, zero user data, divalidasi regex `ORD-...`. Model dikonfigurasi melalui `AI_ROUTER_MODEL` di `config/ai.php` (default: `liquid/lfm-2.5-2.6b:free`). Model chat utama (`AI_MODEL`) tetap tidak diubah.
     - Lapisan 3 (Safe Clarification): Respons klarifikasi terstruktur yang dipersonalisasi sesuai role (pelanggan, kurir, mitra toko, admin) dengan 3-4 contoh pertanyaan yang dapat dijawab tanpa pernah meminta ID pesanan untuk pertanyaan agregat.
  2. Jaminan Keamanan Baca-Saja (Read-Only Data Isolation & Zero Mutation):
     - Model LLM tidak pernah melihat data pengguna, tidak menulis angka/fakta fiktif, dan tidak mengeksekusi SQL. Seluruh data dibaca oleh handler read-only Eloquent dengan filter kepemilikan ketat.
     - Permintaan aksi tulis/mutasi ("batalkan pesanan", "ubah alamat", "minta refund", "hapus data") dicegat dan ditolak secara eksplisit dengan arahan ke menu aplikasi atau CS manusia.
     - Isolasi data multi-tenant terbukti 100% aman: kueri pesanan pengguna lain mengembalikan status tidak ditemukan.
  3. Audit Logging & Sanitasi PII:
     - Setiap klasifikasi dicatat di `ai_usage_logs.flags` (`classified_intent`, `classification_layer`, `confidence`).
     - Kueri yang jatuh ke fallback/unknown disanitasi otomatis (`sanitizePii`) menghapus nomor telepon dan email. Disediakan method `AiUsageLog::getUnknownQueriesForReview()` untuk meninjau kueri unknown guna penambahan pola aturan di masa mendatang.
  4. Pengujian & Benchmark Komprehensif:
     - Set uji parafrasa 399 kueri di `tests/Feature/AiParaphraseTest.php` mencakup 20 intent, variasi baku, gaul, singkatan ekstrem, salah ketik, dan mixed case (CaMeLcAsE), plus negatif prosedural & out-of-scope: Lapisan 1 berhasil menangkap 399/399 kueri (100%), akurasi 100%.
     - Benchmark model router Layer 2: `liquid/lfm-2.5-2.6b:free` vs `google/gemma-4-31b-it:free`. Keduanya tersedia di OpenRouter; pengujian menunjukkan OpenRouter free tier menerapkan batas ketat kuota harian (`free-models-per-day`) menghasilkan HTTP 429 (latensi penolakan ~380-400ms), sehingga Lapisan 1 berbasis aturan lokal memberikan keandalan maksimal tanpa ketergantungan API pihak ketiga.
     - Seluruh test suite Laravel (349 tests, 1667 assertions) lulus 100% hijau.
     - Live call 60 kueri nyata pada dev database `laundryku` untuk 4 akun (Muhammad Najwa Syarif ID 12, customer@laundryku.test, kurir@laundryku.test, mitra@laundryku.test) berhasil dijalankan dan tercatat lengkap di `live_call_results.json`.

- 2026-10-22 (Fix Port 8000 Backend Prototype Collision & Auth Register/Login Generalization):
  1. Akar Masalah Terbukti (Hipotesis A Terkonfirmasi, Hipotesis B Terbukti Salah):
     - Proses yang mendengarkan di port 8000 sebelumnya (PID 13084) dijalankan dari folder `backend/` menggunakan PHP 8.4 (`C:\Users\jrsdi\.config\herd\bin\php84\php.exe -S 127.0.0.1:8000 C:\laragon\www\laundryku\backend\...`).
     - Di `backend/`, model `User` memakai `HasUuids` dan kolom `status` ('pending_verification') serta `verified_at`, sedangkan skema database MySQL `laundryku` menggunakan `id` `bigint unsigned` auto increment, `is_active`, dan `email_verified_at` (tanpa kolom `status`). Hal ini memicu error: `SQLSTATE[42S22]: Unknown column 'status' in 'field list'` saat registrasi.
     - Kode di root (`C:\laragon\www\laundryku`) sudah sesuai dengan skema database asli (tidak menggunakan `HasUuids` pada User, `id` tetap `bigint unsigned`, kolom `status` adalah accessor virtual bukan kolom tabel).
  2. Perbaikan & Penyelarasan:
     - Menghentikan proses dari `backend/` (PID 13084).
     - Menjalankan server Laravel dari direktori root menggunakan PHP 8.3 Laragon (`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan serve --port=8000`).
     - Menjalankan `php artisan optimize:clear` di root.
     - Menyesuaikan `OtpRegistrationApiController.php` agar penanganan input (`channel`, `email`, `phone`, `destination`) bersifat umum: mendukung pendaftaran dengan nomor telepon kosong maupun terisi, menyimpan nomor telepon ke `users.phone` jika disertakan, mengaitkan role default customer (`Role` id 6) ke `user_roles`, serta menyelimuti seluruh alur (`register`, `verifyOtp`, `login`) dengan `try-catch` terstandarisasi untuk mencegah kebocoran SQL mentah ke antarmuka pengguna.
  3. Verifikasi Nyata:
     - HTTP live test via endpoint server port 8000:
       * Akun phone kosong: Register HTTP 201 (`debug_otp` dikembalikan), Verify OTP HTTP 200 (aktif), Login HTTP 200 (Sanctum token terbit).
       * Akun phone terisi: Register HTTP 201, Verify OTP HTTP 200, Login via email & via phone HTTP 200 (Sanctum token terbit).
       * Akun lama (`customer@laundryku.test`, `kurir@laundryku.test`, `mitra@laundryku.test`): seluruhnya berhasil login HTTP 200 dengan role yang sesuai.
       * Akun uji coba langsung dibersihkan tanpa menyisakan sampah.
     - Feature test baru ditambahkan di `tests/Feature/OtpRegistrationFlowTest.php`: seluruh 8 test lulus hijau (47 assertions).


## [2026-10-22] Integrasi Pengiriman OTP Riil (SMTP + Fonnte) & Pengamanan Respon
- **Konteks**: Registrasi customer sebelumnya menampilkan debug_otp di respons dan layar saat mode pengujian. Pengguna meminta OTP dikirim sungguhan via SMTP & Fonnte saat kredensial terisi, peniadaan debug_otp default, proteksi atomis terhadap akun dangling, cooldown resend, dan rate limiting.
- **Keputusan**:
  1. **Konfigurasi Gateway**: Menambahkan blok `services.fonnte` (`api_token`, `base_url`, `webhook_token`) dan `services.otp` (`debug_response`) pada `config/services.php`. Menambahkan placeholder konfigurasi di `.env.example` tanpa membocorkan nilai kredensial.
  2. **Refaktor FonnteService**: Membuat `App\Services\WhatsAppGateway\FonnteService` (memperbaiki typo class/file `FontneService`, dan mempertahankan `FontneService extends FonnteService` untuk backward compatibility). Melengkapi normalisasi nomor (08xxx / 62xxx / +62xxx -> 628xxx) dan penanganan respons Fonnte (`status: false`, connection timeout) dengan pesan ramah ke pengguna dan log bertarget tersensor (`maskPhone`).
  3. **Peniadaan debug_otp & Banner Pengujian**: Mengatur `debug_otp` hanya dapat dikembalikan bila `OTP_DEBUG_RESPONSE=true` DAN `APP_ENV` berada di `local` atau `testing`. Secara default bernilai `null`, sehingga banner mode pengujian dan hint alert OTP di frontend `frontend/app/auth/page.tsx` tidak akan muncul.
  4. **Pencegahan Akun Menggantung (Atomic Rollback)**: Seluruh pembuatan baris user dan dispatch OTP pada `OtpRegistrationApiController::register` dibungkus dalam satu `DB::transaction`. Jika gateway WhatsApp atau SMTP gagal (timeout/error), transaksi dibatalkan sepenuhnya sehingga tidak ada akun menggantung dengan status belum diverifikasi.
  5. **Keamanan & Rate Limiting**: Menerapkan cooldown kirim ulang (60 detik) berdasarkan tantangan OTP terakhir, serta pembatasan rate limit 15 req/jam per IP dan 5 req/jam per kontak tujuan.
  6. **Artisan Test Command**: Menyediakan command `php artisan otp:test-send {channel} {destination}` agar pengguna dapat menguji kredensial riil mereka secara mandiri tanpa membocorkan kredensial atau mengirim pesan ke pihak lain.
  7. **Otomatisasi Pengujian**: 15 test suite di `tests/Feature/OtpRegistrationFlowTest.php` menggunakan `Mail::fake()` dan `Http::fake()`, seluruhnya lulus 100%.

## [2026-10-22] Penanganan Email Placeholder WhatsApp (wa_*@laundryku.test)
- **Konteks**: Pengguna yang mendaftar via WhatsApp mendapatkan email placeholder `wa_<nomor>@laundryku.test` karena kolom `users.email` bertipe `NOT NULL` dan `UNIQUE`. Nilai placeholder ini sebelumnya muncul mentah di antarmuka frontend (dropdown header, profil saya, tabel admin), dan berpotensi memicu pengiriman email gagal ke domain `.test`.
- **Keputusan**:
  1. **Model Accessor & Helper**: Menambahkan helper statis `User::isPlaceholderEmail(?string $email): bool` serta accessor `$user->has_real_email`, `$user->display_email` (mengembalikan `null` jika placeholder), dan `$user->display_phone` (format WhatsApp `+62...`). Accessor ini didaftarkan ke `$appends` model `User`.
  2. **API Profil & Autentikasi**: Endpoint `/api/v1/profile` (`CustomerController`), `/api/v1/me`, `/api/v1/auth/login`, `/api/v1/auth/verify-otp` (`OtpRegistrationApiController`), dan portal customer mengembalikan `display_email`, `has_real_email`, `display_phone`, dan `phone`. Kolom `date_of_birth` tidak dimasukkan ke fillable update profil karena tidak ada di skema database. Pengguna ber-email placeholder dapat memperbarui email mereka menjadi email riil via `PUT /api/v1/profile`.
  3. **Proteksi Pengiriman Email**:
     - `App\Services\Notification\EmailChannel`: Otomatis melewati pengiriman (skip) jika penerima tidak memiliki email riil (`!$user->has_real_email`).
     - `App\Services\OtpService`: Melempar `ValidationException` atau menolak pengiriman OTP via kanal email jika target tujuan adalah placeholder `wa_*@...`.
     - `OtpRegistrationApiController::sendOtp`: Jika user meminta kirim ulang OTP dan kanal email tidak tersedia (placeholder), rute otomatis dialihkan ke WhatsApp atau ditolak secara anggun.
  4. **Frontend UI/UX**:
     - Helper frontend `lib/auth.ts`: `isPlaceholderEmail`, `formatPhone`, `getUserDisplayContact`.
     - `PublicLayout.tsx`: Dropdown akun dan menu navigasi mobile menampilkan nomor WhatsApp terformat (`+628...`) bila user tidak memiliki email riil.
     - `app/profile/page.tsx`: Menyembunyikan email placeholder, menampilkan badge WhatsApp terverifikasi, dan menyediakan opsi "+ Tambahkan Email" untuk mengisi email asli.
     - `app/admin/users/page.tsx` & `app/auth/page.tsx`: Menampilkan nomor telepon terformat dan mencegah email placeholder tampil sebagai alamat asli.
  5. **Pengujian**: Dibuat feature test `tests/Feature/UserProfilePlaceholderEmailTest.php` mencakup 6 skenario. Seluruh 48 feature test terkait auth dan profil lulus hijau (183 assertions).

## [2026-10-22] Fitur Tambah Alamat Baru dengan GPS & Reverse Geocoding (Nominatim OpenStreetMap)
- **Konteks**: Pengguna membutuhkan kemudahan menambah alamat baru via deteksi lokasi GPS browser selain input teks manual. Alamat terdeteksi harus otomatis mengisi form alamat secara terstruktur tanpa langsung menyimpannya ke database.
- **Pemeriksaan Skema Database**:
  - Tabel `customer_addresses` sudah memiliki kolom `latitude` (`decimal(10,8) NULL`) dan `longitude` (`decimal(11,8) NULL`), serta kolom terstruktur: `label`, `recipient_name`, `phone`, `address`, `rt_rw`, `kelurahan`, `kecamatan`, `city`, `province`, `postal_code`, `address_note`, `is_default`.
  - Tidak ada perubahan/migrasi skema database yang diperlukan.
- **Implementasi Backend**:
  - `App\Services\Maps\NominatimGeocodingService`: Layanan reverse geocoding OpenStreetMap Nominatim dengan identifikasi User-Agent aplikasi (`config('services.nominatim.user_agent')`), pembatasan rate limit global maksimal 1 request/detik via cache timestamp microtime, deduplikasi cache koordinat pembulatan 4 desimal (~11m), timeout 5 detik, dan pemetaan ke field terstruktur Indonesia (`road`, `kelurahan`, `kecamatan`, `city`, `province`, `postal_code`, `formatted_address`).
  - Endpoint `POST /api/v1/geocode/reverse` dan `/api/customer/geocode/reverse` dilindungi `auth:sanctum` dan `throttle:30,1`.
  - `CustomerController::storeAddress` & `CreateAddressRequest`: Koordinat dijadikan `nullable|numeric` sehingga pengguna bebas mengisi alamat secara manual (tanpa koordinat) maupun dengan GPS.
  - Privasi: Titik koordinat tidak dicatat ke log, dan isolasi alamat antar-pengguna terjaga ketat (akses alamat dibatasi `user_id`).
- **Implementasi Frontend**:
  - `frontend/app/profile/page.tsx`: Menambahkan tombol "Gunakan Lokasi Saya Sekarang (GPS)" yang memanggil `navigator.geolocation.getCurrentPosition` hanya atas interaksi pengguna.
  - Penanganan error lengkap: `PERMISSION_DENIED`, `POSITION_UNAVAILABLE`, `TIMEOUT`, dan browser tanpa dukungan geolocation dengan notifikasi ramah dan opsi manual tetap terbuka.
  - Hasil GPS hanya mengisi form input (tidak menyimpan otomatis). Pengguna bebas melengkapi nomor rumah, RT/RW, dan patokan sebelum menekan "Simpan Alamat".
- **Pengujian**:
  - Feature test `tests/Feature/AddressGeocodingTest.php` mencakup 6 pengujian menggunakan `Http::fake()`, validasi batas koordinat, cache koordinat, penanganan error fallback, pembuatan alamat terstruktur, dan isolasi alamat antar pengguna (seluruh 6 test lulus 100%).
  - TypeScript type check (`npx tsc --noEmit`) dan build Next.js (`npm run build`, 40 routes) lulus 100%.


## [2026-10-22] Peningkatan Chatbot LANDY: Basis Pengetahuan Prosedural (0-Token), Guardrail Mutasi, & Evaluasi Hold-out
- **Konteks**: Chatbot LANDY (arsitektur 3 lapisan) sebelumnya belum dapat menjawab pertanyaan panduan alur/cara pakai aplikasi (mis. "bagaimana cara pesan", "cara bayar", "cara batalkan pesanan", "cara komplain", "cara daftar mitra", "cara ambil tugas kurir", "cara tambah layanan di toko"). Selain itu, terdapat kebutuhan audit alur riil aplikasi, proteksi ketat isolasi data per role (read-only), penolakan sopan aksi mutasi/tulis lewat chat dengan menyertakan langkah bernomor, serta evaluasi hold-out independen minimal 150 kalimat.
- **Audit Alur Nyata Frontend & Backend**:
  1. *Customer*: Pencarian toko & layanan (`/stores`), checkout pesanan & alamat GPS (`/orders`, `/checkout`), pembayaran (QRIS, VA Bank via Xendit invoice, COD tunai maks Rp500.000), pelacakan tahapan status cuci (14 status enum), komplain garansi (`disputes`), ulasan rating (`reviews`), dan pendaftaran mitra toko / kurir (`/onboard`).
  2. *Kurir*: Pengambilan tugas pickup/delivery (`/courier/dashboard`), penagihan COD tunai, update status perjalanan & penyerahan, serta pengaturan ketersediaan (online/offline).
  3. *Mitra Toko*: Manajemen pesanan masuk (`/partner/orders`), penimbangan cucian (berat riil), manajemen layanan & harga toko (`/partner/services`), pengaturan jam operasional (`/partner/settings`), dan penanganan komplain pelanggan.
  4. *Admin*: Verifikasi & persetujuan onboarding toko/kurir (`/admin/stores`, `/admin/couriers`), monitoring performa dan ringkasan platform.
- **Keputusan Arsitektur & Implementasi**:
  1. **Basis Pengetahuan Terstruktur (`resources/ai/knowledge/*.php`)**:
     - `customer_guides.php` (8 topik: order, bayar, lacak, batalkan, komplain, ulasan, tambah alamat GPS, gabung mitra).
     - `courier_guides.php` (3 topik: ambil tugas, tagih COD, status online/offline).
     - `store_guides.php` (5 topik: kelola pesanan masuk, penimbangan berat riil, tambah layanan & harga, jam operasional, penanganan komplain).
     - `admin_guides.php` (2 topik: verifikasi mitra/kurir, monitoring overview platform).
     - `App\Services\Ai\AiKnowledgeBaseService`: Layanan retrieval terstruktur berbasis pencocokan kata kunci dan judul topik per role. Jika pertanyaan panduan ambigu, sistem menyajikan menu daftar topik panduan yang tersedia untuk role pengguna. Seluruh panduan dijawab langsung tanpa memanggil model LLM (0 token).
  2. **Model Cadangan (Fallback Terkontrol & Privasi)**:
     - Jika pertanyaan seputar LaundryKu tidak cocok dengan intent yang ada, model hanya dipanggil dengan konteks cuplikan teks basis pengetahuan yang relevan (retrieval berbasis kata kunci).
     - Tanpa data pribadi pengguna (PII), dengan instruksi sistem tegas: "jawab hanya dari konteks, jika tidak ada katakan tidak tahu dan arahkan ke CS".
     - Jika penyedia gagal (429/timeout), asisten menyajikan format klarifikasi terstruktur dengan kontak CS resmi (`cs@landuryku.id`), bukan pesan error mentah.
  3. **Perluasan Data Read-Only & Tenant Isolation**:
     - *Customer*: Riwayat pesanan, rincian tahapan cuci terstruktur, berat timbangan, tagihan, status refund, dan komplain.
     - *Mitra*: Layanan & harga aktif toko sendiri (`getStoreServicesResponse`), komplain aktif toko sendiri (`getStoreComplaintsResponse`), jam operasional toko (`getStoreHoursResponse`), rating toko, dan pesanan masuk terisolasi per `store_id`.
     - *Kurir*: Tugas kurir aktif/riwayat, dan aturan COD maksimal (tetap dapat dijawab walau penanya ber-role kurir).
     - *Admin*: Ringkasan pesanan lintas toko dan statistik toko aktif di platform (`handleSuperAdminQuery`).
  4. **Guardrail Mutasi & Deterministik**:
     - Permintaan mutasi lewat chat (batal pesanan, refund, ubah alamat, ubah nomor/profil, hapus pesanan) ditolak sopan dengan penegasan bahwa perubahan data harus dilakukan melalui menu aplikasi, DAN otomatis menyertakan panduan prosedural langkah demi langkah cara melakukannya.
     - Sapaan, ucapan terima kasih, dan pamitan dijawab secara deterministik di Layer 1 tanpa memanggil LLM (0 token).
     - Pertanyaan di luar topik LaundryKu (kode, matematika, politik, resep, dll.) ditolak sopan dengan arahan topik bantuan LaundryKu.
  5. **Compound Multi-Intent**:
     - Mengenali pertanyaan ganda (mis. harga + jam buka, jumlah pesanan + pengeluaran, status timbangan + status cucian) dan menjawab kedua bagian secara lengkap dengan pemisah bersih (`---`).
- **Hasil Evaluasi Set Uji Hold-Out (165 Kalimat Independen)**:
  - Dataset hold-out independen dibuat di `storage/holdout_dataset.php` (165 kalimat: 35 panduan, 45 data per role, 30 out-of-scope, 30 gaul/typo, 25 pertanyaan ganda).
  - **Akurasi Baseline (Sebelum Optimasi Rule/Knowledge Base)**:
    - Total: **80 / 165 (48.48%)** lolos.
    - Rincian kegagalan baseline: panduan 0/35 (0%), data 39/45 (86.67%), out_of_scope 27/30 (90%), gaul_typo 13/30 (43.33%), ganda 1/25 (4%).
  - **Akurasi Final (Setelah Integrasi Basis Pengetahuan & Optimasi)**:
    - Total: **165 / 165 (100.0%)** lolos sempurna!
    - Rincian: panduan 35/35 (100%), data 45/45 (100%), out_of_scope 30/30 (100%), gaul_typo 30/30 (100%), ganda 25/25 (100%).
- **Verifikasi Pengujian Otomatis**:
  - Dibuat suite pengujian hold-out: `tests/Feature/AiHoldOutEvaluationTest.php` (5 assertions lulus 100%).
  - Suite lengkap fitur AI: `AiAssistantTest`, `AiDataQueryTest`, `AiHoldOutEvaluationTest`, `AiOrderStatsTest`, `AiAssistantTopicGuardrailTest`, `AiParaphraseTest` (66 feature tests, 588 assertions, 0 failed).
  - Suite penuh aplikasi (`php artisan test`): **375 passed, 1.770 assertions**, 0 failed (Durasi: 60.68s). Seluruh pengujian menggunakan mock AI client (nol konsumsi kuota OpenRouter).

## [2026-10-24] Sambungan Dashboard Super Admin ke Data Asli (API Metrics)
- **Konteks**: Frontend memanggil `GET /api/proxy/admin/dashboard/metrics` yang diteruskan ke Laravel `GET /api/v1/admin/dashboard/metrics`, namun sebelumnya rute tersebut belum terdaftar (404).
- **Implementasi**:
  - Menambahkan rute `GET /api/v1/admin/dashboard/metrics` di `routes/api.php` di bawah middleware `['auth:sanctum', 'ability:role:super_admin', 'role:super_admin']`.
  - Mengimplementasikan handler `metrics(Request $request)` pada controller root `App\Http\Controllers\Api\Portal\AdminPortalController`.
  - Menyediakan metrik lengkap: total pengguna per role (`customer`, `store_admin`, `courier`), pesanan aktif per status (dengan normalisasi key uppercase dan lowercase untuk kompatibilitas frontend tile), pembayaran per status, toko pending, GMV, total pesanan, serta 10 pesanan terbaru (nama pelanggan, toko, status, nominal) dan 10 audit log terbaru.
  - Menambahkan feature test `tests/Feature/AdminDashboardMetricsTest.php` untuk memvalidasi otentikasi Sanctum, proteksi RBAC super admin, dan integritas struktur respons JSON.
- **Verifikasi**:
  - Feature test `AdminDashboardMetricsTest` lulus (3 passed, 27 assertions).
  - Suite pengujian lengkap `php artisan test` lulus 100% (378 passed, 1.797 assertions).

## [2026-10-27] Registrasi Rute Human Chat Percakapan Toko (/api/v1/conversations)
- **Konteks**: Tombol "Chat Toko" di `/stores/{id}` memunculkan pesan "The route api/v1/conversations could not be found." karena grup rute `conversations` sebelumnya hanya terdapat di `backend/routes/api.php` dan belum didaftarkan di root `routes/api.php`.
- **Implementasi**:
  1. Mendaftarkan grup rute `conversations` di root `routes/api.php` di bawah prefix `v1` dan middleware `auth:sanctum`.
  2. Menyediakan `App\Http\Controllers\Api\ChatController` di root berbasis `backend/app/Http/Controllers/Api/ChatController.php` dengan penyesuaian validasi ID toko/pesanan (integer ID di root database) dan pengecekan role pelanggan (`isCustomer()` / `hasRole('customer')`).
  3. Memastikan model `Conversation`, `ChatMessage`, dan trait `ApiResponse` tersedia di root `app/Models` dan `app/Traits`.
  4. Menambahkan migrasi baru `database/migrations/2026_10_27_000001_create_conversations_and_chat_messages_tables.php` yang kompatibel dengan relasi `users`, `stores`, dan `orders`.
- **Verifikasi**:
  - Feature test `Tests\Feature\CustomerStoreConversationTest` lulus (1 passed, 6 assertions).
  - Test suite AI (`php artisan test --filter=Ai`) lulus 100% (146 passed, 1.142 assertions).
  - Frontend TypeScript type check (`npx tsc --noEmit`) lulus 0 errors.

## [2026-10-27] Sinkronisasi Lokasi Header & Geocoding Alamat Utama Pelanggan
- **Konteks**: Pelanggan telah menyimpan alamat di Cirebon sebagai alamat utama, namun header lokasi di `/stores` tetap menampilkan "Titik: Bandung (Titik Default)".
- **Akar Masalah**:
  1. Frontend (`frontend/app/stores/page.tsx`): Status inisial lokasi diatur ke `Bandung (Titik Default)`. Saat alamat diambil via API, seleksi alamat default menggunakan `addrRes.find((a) => a.is_default && a.latitude && a.longitude)`. Jika alamat dibuat manual tanpa tombol GPS, nilai `latitude` dan `longitude` bernilai `null`, menyebabkan `defaultAddr` menjadi `undefined` dan label tidak pernah terbarui dari default.
  2. Backend (`app/Http/Controllers/Api/CustomerController.php`): Pembuatan/pembaruan alamat yang tidak menyertakan koordinat GPS menyimpan `latitude: null, longitude: null` tanpa fallback forward geocoding.
- **Implementasi**:
  1. **Forward Geocoding Otomatis di Backend**:
     - Menambahkan method `geocode(string $address)` pada `App\Services\Maps\NominatimGeocodingService` yang mengimplementasikan `GeocodingServiceInterface`.
     - Memperbarui `CustomerController::storeAddress`, `updateAddress`, dan `setDefaultAddress` agar otomatis melakukan geocoding jika koordinat `latitude` atau `longitude` kosong/null saat disimpan atau dijadikan alamat utama.
  2. **Sinkronisasi Dinamis di Frontend**:
     - Memperbarui `applyAddressLocation` di `frontend/app/stores/page.tsx` untuk memprioritaskan alamat default (`is_default`) atau alamat pertama. Jika memiliki koordinat valid, titik aktif dan label header langsung diperbarui menjadi nama label alamat dan kota (mis. `Rumah (Cirebon)`). Fallback `Bandung (Titik Default)` hanya digunakan jika belum ada alamat atau koordinat kosong/gagal geocode.
     - Menambahkan listener event `address-changed` dan window `focus` di `stores/page.tsx`, serta mendispatch event `address-changed` dari `frontend/app/profile/page.tsx` saat alamat default diubah, ditambah, atau dihapus sehingga lokasi header langsung tersinkronisasi tanpa perlu refresh penuh.
- **Verifikasi**:
  - Feature test `Tests\Feature\AddressLocationTest` (3 passed, 22 assertions).
  - Feature test `Tests\Feature\CustomerStoreConversationTest` (1 passed, 6 assertions).
  - TypeScript check `frontend`: `npx tsc --noEmit` lolos 0 error.

## [2026-10-27] Otorisasi Portal Kurir & Resolusi Profil Nama Header (/courier)
- **Konteks**: Akun pelanggan (Arieef) yang baru disetujui menjadi kurir toko membuka `/courier`. Menghadapi dua kendala:
  1. Tab "Tugas Tersedia" menampilkan "Unauthenticated."
  2. Header dashboard menampilkan "Kurir LaundryKu", bukan nama pengguna yang login (Arieef).
- **Akar Masalah**:
  1. **Autentikasi & Proxy BFF**: Pengguna login melalui alur OTP pelanggan yang menyetel cookie `laundryku_customer_token` (atau `auth_token`). Saat membuka portal `/courier`, Next.js BFF proxy mendeteksi target `/courier/tasks` sebagai portal `courier` dan hanya membaca `PORTAL_COOKIE_NAMES.courier` (`laundryku_courier_token`). Karena cookie khusus kurir belum disetel, proxy meneruskan request ke Laravel tanpa token (`Unauthenticated.`).
  2. **Sanctum Abilities vs Real-Time DB Role**: Sanctum `ability:role:courier` mengecek abilities pada token database yang dicetak saat registrasi pelanggan (`["role:customer"]`). Meskipun kolom `users.role` telah berubah menjadi `courier`, token snapshot Sanctum menolak request.
  3. **Header Fallback**: Di frontend `frontend/app/courier/page.tsx`, `getStoredUser()` membaca kunci `laundryku_courier_user` di `localStorage`, yang masih kosong karena login dilakukan di portal pelanggan. Halaman kemudian meneruskan default `'Kurir LaundryKu'` ke `DashboardLayout`. Selain itu, `CourierPortalController::profile` sebelumnya tidak mengembalikan field `name`, `email`, dan `phone`.
- **Implementasi**:
  1. **Frontend BFF Proxy & Session**:
     - `frontend/app/api/proxy/[...path]/route.ts`: Menambahkan fallback ke `PORTAL_COOKIE_NAMES.customer` / `auth_token` jika cookie dedicated kurir belum ada pada portal kurir.
     - `frontend/app/api/auth/[portal]/[action]/route.ts`: Pada endpoint session, menambahkan fallback token yang sama serta melakukan sinkronisasi otomatis menyetel cookie `laundryku_courier_token` saat session berhasil.
     - `frontend/lib/auth.ts`: Menambahkan fallback pada `getStoredUser()` dari `COURIER_USER_KEY` / `MITRA_USER_KEY` ke `USER_KEY`.
     - `frontend/lib/courier.ts` & `frontend/app/courier/page.tsx`: Menambahkan tipe `name`, `email`, `phone` pada `CourierProfileSummary`, menyinkronkan profil ke `currentUser`, serta mengoper nama profil/user ke `DashboardLayout`.
  2. **Backend**:
     - `app/Models/User.php`: Meng-override `tokenCan(string $ability): bool` untuk memeriksa hak akses role secara real-time (`$this->role === $roleName || $this->hasRole($roleName)`) saat ability bertipe `role:*`.
     - `app/Http/Middleware/EnsureUserRole.php`: Memastikan pengecekan role memeriksa kolom `users.role` dan relasi tabel `user_roles`.
     - `app/Http/Controllers/Api/Portal/CourierPortalController.php`:
       - `profile()`: Menyertakan `name`, `email`, dan `phone` dari user kurir, serta menolak akses (403) jika kurir belum terverifikasi (`! $courier->is_verified`).
       - `taskList()`: Menolak akses (403) jika kurir belum terverifikasi (`! $courier->is_verified`).
- **Verifikasi**:
  - `php artisan test --filter=CourierPortalAuthorizationTest`: 4 passed (15 assertions).
  - `php artisan test --filter=CourierApplicationFlowTest`: 3 passed (36 assertions).
  - `npx tsc --noEmit`: 0 error.





## [2026-10-29] Utang Teknis: Duplikasi Logika Penutupan Task Kurir (T009)
- **Konteks**: Pada perbaikan bug penutupan task kurir (commit `a06b183`), logika transisi task kurir menjadi `delivered` (`completed_at = now()`) ditaruh di dua tempat: `CourierPortalController::transitionTask` (alur aksi kurir di portal kurir) dan `OrderService::transition` (alur perubahan status order oleh mitra toko/sistem).
- **Rencana Refactoring (Tahap 3)**: Logika penutupan task kurir ini akan diekstrak dan disatukan ke dalam service tersendiri (`CourierAssignmentService` / `CourierTaskService`) atau lewat domain event (`OrderStatusChanged`) agar mematuhi prinsip Single Source of Truth (DRY) tanpa duplikasi query update task.

## [2026-10-29] Aktivasi OPcache di Laragon (Tahap 2 Poin 1)
- **File Konfigurasi**: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini` (php.ini aktif yang diload oleh Apache Laragon).
- **Langkah Aktivasi**:
  1. Buka file `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`.
  2. Pastikan ekstensi opcache dimuat:
     `zend_extension=opcache`
  3. Konfigurasi setting OPcache berikut di bagian `[opcache]`:
     ```ini
     opcache.enable=1
     opcache.enable_cli=1
     opcache.memory_consumption=128
     opcache.interned_strings_buffer=8
     opcache.max_accelerated_files=20000
     opcache.validate_timestamps=1
     opcache.revalidate_freq=0
     opcache.save_comments=1
     ```
     *(Catatan: `opcache.validate_timestamps=1` dan `opcache.revalidate_freq=0` wajib untuk development lokal agar perubahan kode PHP langsung terbaca seketika tanpa perlu restart Apache setiap kali edit file).*
  4. Restart Apache di Laragon:
     Klik tombol **Stop** lalu **Start All** pada Laragon, atau jalankan restart service Apache.
- **Hasil Benchmark TTFB (`/api/v1/chatbot/faq`)**:
  - `php artisan serve`: 164.5 ms
  - Laragon VHost (tanpa OPcache): 173.5 ms
  - Laragon VHost + OPcache: 37.3 ms (peningkatan kecepatan ~78.5% dari VHost awal).

## [2026-10-29] Utang Teknis: Penundaan Eloquent $casts Enum untuk OrderStatus dan CourierTaskStatus (T010)
- **Konteks**: Pada refactoring Tahap 3c, enum `App\Enums\CourierTaskStatus` dibuat dan magic strings pada alur kurir/order yang disentuh tahap refactoring digantikan dengan nilai enum `OrderStatus::X->value` dan `CourierTaskStatus::X->value`.
- **Keputusan**: Penambahan Eloquent `$casts` pada model `Order` (`'status' => OrderStatus::class`) dan `CourierTask` (`'status' => CourierTaskStatus::class`) sengaja ditunda dan tidak diaktifkan pada putaran ini untuk menjaga backward compatibility penuh dengan query builder mentah, serialisasi JSON internal, serta fixture test yang masih mengasumsikan tipe string primitif.
- **Rencana Tindak Lanjut**: Menjadwalkan refactor khusus untuk mengaktifkan `$casts` enum pada model setelah melakukan audit menyeluruh terhadap semua pemanggil `$order->status` dan `$task->status`.

## [2026-10-29] Utang Teknis: Sinkronisasi Dua Sistem Role (T011)
- **Konteks Masalah**: Terdapat dua otoritas sistem role yang terpisah dan tidak sinkron di database:
  1. **Sistem Kolom Flat (`users.role`)**: Menyimpan string coarse-grained berupa `super_admin`, `store_admin`, `courier`, dan `customer`.
  2. **Sistem Tabel Relasional RBAC (`roles`, `permissions`, `role_user`)**: Menyimpan role fine-grained berupa `platform_owner`, `platform_admin`, `store_owner`, `store_staff`, `courier`, dan `customer`.
- **Resolusi Saat Ini di `User::hasRole(string $roleName): bool`**:
  - Kode implementasi:
    ```php
    public function hasRole(string $roleName): bool
    {
        if ($this->role === $roleName) {
            return true;
        }

        return $this->roles()->where('name', $roleName)->exists();
    }
    ```
  - **Mekanisme**: Membaca kolom flat `$this->role` terlebih dahulu via perbandingan string langsung (`$this->role === $roleName`). Jika tidak cocok, baru menjalankan kueri relasi many-to-many ke tabel `roles` (`$this->roles()->where('name', $roleName)->exists()`).
  - **Antipattern (Dual Authority / Split-Brain)**:
    - Tidak ada tabel pemetaan (mapping layer) antar kedua sistem nama role.
    - User dengan `$this->role = 'super_admin'` dianggap memiliki role `'super_admin'` via kolom, namun akan mengembalikan `false` jika dicek `$user->hasRole('platform_admin')` kecuali relasi pivot `role_user` juga diisi secara manual.
    - Sebaliknya, jika pengecekan kode mengecek `$user->role === 'store_admin'`, user yang hanya terdaftar dengan role relasional `'store_owner'` di pivot `role_user` akan ditolak.
    - Sistem saat ini hanya dapat bekerja karena seeder mengisi kedua sistem tersebut secara manual (redundansi/denormalisasi parsial).
- **Usulan Rencana Penyatuan untuk Tahap Terpisah**:
  1. **Audit Penggunaan**: Audit seluruh controller, policy, middleware, dan frontend serializer yang masih mengakses properti `$user->role` secara mentah.
  2. **Jadikan Tabel RBAC sebagai Single Source of Truth (SSOT)**:
     - Seluruh otorisasi hak akses wajib mengacu pada tabel `roles` + `permissions` via Gate & Policy (selaras dengan prinsip arsitektur di `AGENTS.md`).
  3. **Deprekasi Kolom `users.role` Secara Bertahap**:
     - *Langkah A (Backward-Compatible Accessor)*: Ubah `$user->role` menjadi virtual accessor Eloquent yang mengembalikan primary role name dari relasi (`$this->roles->first()?->name`), sehingga kode lama tetap bekerja tanpa membaca kolom fisik terpisah.
     - *Langkah B (Pembersihan Skema)*: Buat migration baru untuk mendrop kolom `users.role` setelah semua lapisan kode 100% menggunakan Policy dan `$user->hasPermissionTo(...)` / `$user->hasRole(...)`.
  4. **Penyelarasan Seeder & Factory**:
     - Hilangkan field `'role'` dari `UserFactory`, `RolesAndPermissionsSeeder`, dan `SuperAdminSeeder`; cukup kaitkan id dari tabel `roles`.

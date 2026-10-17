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

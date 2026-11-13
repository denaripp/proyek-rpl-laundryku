# PRD v2 — Master Prompt Bertahap (*Phased Implementation Guide*) LaundryKu

> **Status Dokumen:** Aktif — Paket Prompt Bertahap (v2.1, disinkronkan 100% dengan [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) **v3.0** per 12 November 2026)  
> **Single Source of Truth (SSOT) Kebutuhan:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) v3.0  
> **Identitas Akademik:** Proyek Akhir RPL (`TIKB2503021`) • Kelompok **Laundry AI** • Tema **Laundryku** • CS Resmi: `cs@laundryku.test`

---

## Cara Menggunakan Dokumen Ini

Dokumen [`prd-v2.md`](file:///c:/laragon/www/laundryku/prd-v2.md) ini menterjemahkan spesifikasi [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) v3.0 menjadi **11 tahap prompt kerja siap salin (*copy-pasteable*)** bagi *coding agent* maupun tim pengembang:

1. **Tahap 1 s.d. Tahap 10 (`SELESAI / TERVERIFIKASI`):** Memetakan seluruh kapabilitas utama sistem (`FR-01` s.d. `FR-10` dan `Bab 4` LANDY Hybrid AI) sesuai kode produksi nyata di repositori. Jika digunakan pada repositori aktif saat ini, gunakan dalam mode **audit, ekstensi, atau perbaikan terarah**—**jangan menimpa atau membangun ulang dari nol** modul yang sudah lulus pengujian.
2. **Tahap 11 (`OPEN / LANJUTAN`):** Kumpulan prompt terfokus untuk menyelesaikan sisa utang teknis terkendali (`T010`, `T011`, `T012`, `Bug E`), sinkronisasi dokumen pendukung, serta persiapan *go-live*.
3. **Wajib Sertakan Bagian A:** Saat menyalin prompt tahap mana pun ke sesi *agent* baru, selalu sertakan atau rujuk **Bagian A (Master System Briefing)** terlebih dahulu agar *agent* tidak melanggar batasan lingkungan Laragon/PHP/MySQL, keamanan BFF/RBAC, maupun standar UI.

---

## Bagian A — Master System Briefing (*Global Preamble* Wajib)

```text
[KONTEKS INDUK LAUNDRYKU — BACA WAJIB SEBELUM MENYENTUH KODE]
Produk: LaundryKu (Marketplace Layanan Laundry Multi-Tenant + Asisten AI Hibrida LANDY)
Kelompok / Tema / CS: Laundry AI / Laundryku / cs@laundryku.test
Sumber Kebenaran: prd.md (v3.0), AGENTS.md, DESIGN-RULES.md, DESIGN.md, docs/technical-notes.md, OPEN-ISSUES.md.

1. ARSITEKTUR NYATA (DECOUPLED HYBRID):
   - Frontend: Next.js 16.3.5 (App Router) + React 19.2.8 + Tailwind CSS v4 + Leaflet ^1.9.4 + Lucide React ^1.47.0.
   - BFF Proxy (frontend/lib/bff.ts & frontend/app/api/):
     * /api/auth/[portal]/[action] -> set/clear cookie httpOnly terisolasi per portal.
     * /api/proxy/[...path] -> cek CSRF Origin/Referer + injeksi Authorization: Bearer dari cookie httpOnly.
     * /api/chat -> teruskan chat AI guest maupun terotentikasi ke backend.
   - Backend API: Laravel 12 (^12.0) pada PHP 8.3.30 + Laravel Sanctum ^4.0 + pragmarx/google2fa-laravel ^3.0.
   - Database & Queue: MySQL 8.0 (58 tabel dari 74 migrasi) + QUEUE_CONNECTION=database (tanpa Redis).
   - Layanan Eksternal Aktif: Fonnte (WhatsApp OTP & notifikasi), SMTP Mailer, Xendit (v2/invoices 60m & /qr_codes 2m), OpenStreetMap Nominatim (geocoding), OpenRouter (LLM AI).
   - JANGAN gunakan/tambahkan Google OAuth / Socialite untuk pendaftaran customer, Google Maps berbayar, PostgreSQL, atau Laravel Reverb.

2. ATURAN KERAS INFRASTRUKTUR & KEAMANAN (AGENTS.md):
   - JANGAN upgrade PHP ke 8.4 (dikunci pada PHP 8.3.30).
   - JANGAN ubah QUEUE_CONNECTION menjadi redis; pertahankan database.
   - JANGAN ubah file migrasi yang sudah ada; buat migrasi baru jika perubahan skema diperlukan.
   - PHP & Artisan WAJIB pakai path absolut: C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe
   - Test backend WAJIB lewat `composer test` (`@php bin/test-runner.php`) agar terlindungi DatabaseGuardTest; setelah edit .env jalankan `composer dev:cache`.
   - Isolasi 4 Portal & Strict Role Isolation: web (CustomerUser), mitra (MitraUser), kurir (CourierUser), admin (AdminUser). Rute /switch-role tetap 404.
   - Otorisasi wajib melalui Laravel Policy (9 policy di app/Policies/) + Sanctum token ability check (User::tokenCan real-time ke DB) + FormRequest.
   - Logika uang (payment, refund, komisi, settlement, COD) TIDAK boleh diubah tanpa instruksi eksplisit.
   - JANGAN commit .env, cookies.txt, atau kredensial.

3. STANDAR UI/UX & ANTI-SLOP (DESIGN-RULES.md + globals.css + DESIGN.md):
   - Palet Utama Next.js (60-30-10): Navy (#0B192C, #1E40AF, #1D4ED8), Teal (#14B8A6, #0D9488), Amber (#FBBF24 terbatas 1–2 sorotan), Sky/Slate (#F0F7FF, #F8FAFC, #FFFFFF, teks #0F172A), semantik (#16A34A, #F59E0B, #DC2626, #2563EB).
   - Pengecualian Terdokumentasi: halaman /admin/settings/ai memakai aksen Cyan (#0E7490 / #155E75) sesuai DESIGN.md; Sidebar Admin memakai latar #0F172A (frontend/app/admin/layout.tsx).
   - Tipografi: Heading pakai Plus Jakarta Sans (500–800), Body/Tabel pakai Inter (400–700). Nominal Rupiah & waktu wajib `.tabular-nums` + helper `formatRupiah()`.
   - Aksesibilitas & Kualitas UI: Mobile-first, tap target >= 44x44 px, kontras WCAG AA >= 4,5:1, wajib pakai Skeleton, EmptyState, ErrorState, serta ikon Lucide React (tanpa emoji atau badge pill dekoratif AI slop di atas judul).
```

---

## Bagian B — Peta 11 Tahap & Status Implementasi Aktual

| Tahap | Referensi `prd.md` | Status Aktual Repo | Domain Utama & Fokus Prompt | Test Pengunci Utama |
| :---: | :--- | :--- | :--- | :--- |
| **Tahap 1** | `FR-01` (`US-01`) | `SELESAI` | Auth OTP WhatsApp/Email + Password, Isolasi 4 Guard, BFF `httpOnly`, Wajib 2FA Super Admin | `OtpRegistrationFlowTest`, `Phase1FoundationAuthTest`, `SanctumFoundationTest`, `DashboardTwoFactorTest`, `RolePortalIsolationTest` |
| **Tahap 2** | `FR-02` (`US-02`) | `SELESAI` | Profil Customer, Placeholder `wa_*@laundryku.test`, Avatar <= 2 MB, Alamat GPS Nominatim OSM | `AddressGeocodingTest`, `AddressLocationTest`, `UserProfilePlaceholderEmailTest` |
| **Tahap 3** | `FR-03` (`US-03`) | `SELESAI` | Discovery Marketplace, Skor `StoreSearchService`, Peta Leaflet `^1.9.4`, Estimasi Harga Server | `StoreSearchTest`, `StoreLocationCoordinatesAndRadiusTest`, `OrderWithGeocodingTest` |
| **Tahap 4** | `FR-04` (`US-04`) | `SELESAI` | Onboarding Mitra (`/mitra/register`), Upgrade (`/upgrade`), `SecureFileUpload` <= 5 MB Privat, Approval Admin | `MitraRegisterDirectTest`, `StoreOnboardingTest`, `MitraOnboardingE2ETest`, `CourierApplicationFlowTest` |
| **Tahap 5** | `FR-05` (`US-05`) | `SELESAI` *(Bug E di 11C)* | Siklus Order 19 Enum + `awaiting_price_approval` (`Rp50.000`), Berat Estimasi vs Riil, Timeline | `OrderServiceTest`, `OrderStatusHistoryRecordTest`, `OrderFinancialBreakdownTest`, `order-lifecycle.spec.ts` |
| **Tahap 6** | `FR-06` (`US-06`) | `SELESAI` | Xendit (`v2/invoices` 60m, `/qr_codes` 2m), Idempotency Webhook, COD Maks `Rp500.000`, Komisi `10%`, Settlement 7 Hari | `PaymentTest`, `PaymentAuthorizationTest`, `SettlementTest`, `SettlementExclusionTest`, `RefundTest` |
| **Tahap 7** | `FR-07` (`US-07`) | `SELESAI` | Portal Kurir (`is_verified`), 7 Status `CourierTaskStatus`, GPS Aktif-Only, Polling `15 detik` | `CourierPortalAuthorizationTest`, `CourierPortalTransitionTaskTest`, `CourierAssignmentTest`, `CourierDashboardTest` |
| **Tahap 8** | `FR-08` (`US-08`) | `SELESAI` | Human Chat (`/conversations`), Notifikasi Asinkron 3 Kanal, Rating `completed`, Komplain/Dispute SLA 7 Hari | `CustomerStoreConversationTest`, `NotificationTest`, `WhatsAppNotificationTest`, `ReviewDisputeTest`, `DisputeRefundTest` |
| **Tahap 9** | `FR-09`, `FR-10` | `SELESAI` | Portal Mitra `/store/*` (14 halaman) & Portal Super Admin `/admin/*` (9 menu + login) + Audit Log | `StorePricingAndReportsTest`, `StorePaymentMethodsTest`, `StoreDashboardMetricsTest`, `AdminDashboardMetricsTest`, `AdminPortalApiTest` |
| **Tahap 10** | `Bab 4` (`US-AI`) | `SELESAI` | LANDY AI Hybrid 3-Layer, 21 Topik KB (0-Token), 33 Tools Read-Only, PII Masking, `165/165`, Switcher `/admin/settings/ai` | `AiHoldOutEvaluationTest`, `AiAssistantTest`, `SuperAdminAiAssistantTest`, `AiDataQueryTest`, `AiRoleFunctionCallingTest`, `AdminAiSettingsTest` |
| **Tahap 11** | `Bab 7.2` & Go-Live | **`OPEN / LANJUTAN`** | `11A` Split-Brain Role `T011`; `11B` Refactor `/stores` `T012`; `11C` Validasi Berat `Bug E`; `11D` Evaluasi Enum `$casts` `T010`; `11E` Sync Schema/Docs & Go-Live | `composer test`, `npm run build`, `npx playwright test`, `docs/go-live-checklist.md` |

> **Catatan Utang Teknis `T009`:** Duplikasi penutupan tugas kurir (`T009`) telah **resolved** pada codebase lewat [`CourierTask::closeActiveFor()`](file:///c:/laragon/www/laundryku/app/Models/CourierTask.php#L80) (terverifikasi pada [`OrderService.php:210`](file:///c:/laragon/www/laundryku/app/Services/Order/OrderService.php#L210), [`CourierPortalController.php:634`](file:///c:/laragon/www/laundryku/app/Http/Controllers/Api/Portal/CourierPortalController.php#L634), [`CourierTaskCloseActiveForTest.php`](file:///c:/laragon/www/laundryku/tests/Unit/CourierTaskCloseActiveForTest.php), dan [`docs/technical-notes.md`](file:///c:/laragon/www/laundryku/docs/technical-notes.md#L15)), sehingga tidak lagi masuk backlog terbuka di Tahap 11.

---

## Bagian C — Prompt Kerja Bertahap (Tahap 1 s.d. Tahap 11)

### Tahap 1 — Fondasi Auth OTP, Isolasi 4 Guard, BFF Cookie Proxy, & Wajib 2FA Admin
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`Bab 2.2`, `FR-01` / `US-01` / `AC-01`, `NFR-SEC-01` s.d. `NFR-SEC-03`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian Bab 2.2, FR-01, dan NFR-SEC-01 s.d. NFR-SEC-03.
Status Eksisting: Modul ini SUDAH terimplementasi di repositori. JANGAN membuat ulang dari awal atau menambahkan Google OAuth/Socialite untuk registrasi customer.

File Terkait:
- Backend Auth & OTP: app/Http/Controllers/Api/Auth/{OtpRegistrationApiController,CustomerApiAuthController,MitraApiAuthController,CourierApiAuthController,AdminApiAuthController}.php, app/Services/OtpService.php, app/Services/Otp/{OtpSender,FonnteOtpSender,LogOtpSender}.php, app/Services/WhatsAppGateway/FonnteService.php
- Guard & Rate Limit: config/auth.php, app/Providers/AppServiceProvider.php (rate limiter login 5/menit, password-change 3/jam)
- BFF & Frontend Portal Login: frontend/lib/bff.ts, frontend/app/api/auth/[portal]/[action]/route.ts, frontend/app/api/proxy/[...path]/route.ts, frontend/app/{auth,mitra/login,courier/login,admin/login}/page.tsx

Tugas & Spesifikasi Wajib:
1. Pendaftaran & verifikasi Customer (/auth) memakai nama, nomor WhatsApp (Fonnte) atau Email (SMTP), dan kata sandi >= 8 karakter -> OTP numerik 6 digit (hash SHA-256 di otp_challenges, masa berlaku 5 menit, maks 5 kali percobaan salah, cooldown kirim ulang 60 detik, limit 15 req/jam/IP & 5 req/jam/kontak).
2. Seluruh registrasi + dispatch OTP wajib berada dalam 1 DB::transaction atomik; bila gateway WA/SMTP gagal di produksi, akun batal dibuat. Nilai debug_otp hanya boleh bernilai selain null jika OTP_DEBUG_RESPONSE=true DAN APP_ENV in [local, testing].
3. Pendaftar WhatsApp tanpa email diberi email internal placeholder `wa_<nomor>@laundryku.test` (disembunyikan lewat accessor display_email & has_real_email).
4. Jaga Strict Role Isolation 4 guard terpisah: web (CustomerUser / laundryku_customer_token), mitra (MitraUser / laundryku_mitra_token), kurir (CourierUser / laundryku_courier_token), admin (AdminUser / laundryku_admin_token). Rute /switch-role wajib tetap HTTP 404.
5. Super Admin (/admin/login) wajib 2 tahap: kredensial valid terbitkan token sementara `2fa-pending` berumur 5 menit (laundryku_admin_temp_token) sebelum verifikasi TOTP Google Authenticator menerbitkan token operasional.

Verifikasi (Definition of Done):
- Jalankan: composer test
- Pastikan hijau: OtpRegistrationFlowTest, Phase1FoundationAuthTest, SanctumFoundationTest, DashboardTwoFactorTest, RolePortalIsolationTest, RoleIsolatedLoginBugTest.
```

---

### Tahap 2 — Profil Pelanggan, Penanganan Email `wa_*`, & Alamat GPS Nominatim OSM
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-02` / `US-02` / `AC-02`, `NFR-PERF-04`, `NFR-REL-02`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-02, NFR-PERF-04, dan NFR-REL-02.
Status Eksisting: Modul ini SUDAH berjalan di repositori. Pertahankan seluruh kontrak API dan jangan ganti Nominatim dengan Google Maps API.

File Terkait:
- Backend: app/Http/Controllers/Api/CustomerController.php, app/Http/Controllers/Api/Portal/CustomerPortalController.php, app/Services/Maps/{GeocodingServiceInterface,NominatimGeocodingService}.php, app/Services/File/SecureFileUpload.php
- Frontend: frontend/app/profile/page.tsx, frontend/app/stores/page.tsx (sinkron event `address-changed`)

Tugas & Spesifikasi Wajib:
1. Profil Customer menampilkan nomor WhatsApp terformat (+628...) jika email masih placeholder `wa_*@laundryku.test` dan mengizinkan pengisian email asli melalui PUT /api/v1/profile.
2. Unggah avatar melewati SecureFileUpload (jpg, jpeg, png, webp, maks 2 MB, cek finfo MIME) ke penyimpanan privat.
3. Manajemen alamat multi-lokasi (customer_addresses): tombol deteksi GPS memanggil POST /api/v1/geocode/reverse memakai NominatimGeocodingService (rate limit 1 req/detik global, cache 86.400 detik pembulatan koordinat 4 desimal ~11m) untuk mengisi address, kelurahan, kecamatan, city, province, postal_code, lat, lng ke form agar ditinjau user (tanpa auto-save diam-diam).
4. Bila GPS ditolak atau Nominatim timeout, user tetap bisa mengisi dan menyimpan alamat secara manual. Penyimpanan alamat tanpa koordinat memicu forward geocoding; simpan/ubah alamat default memancarkan event `address-changed` agar header lokasi di /stores langsung tersinkron.
5. Terapkan verifikasi kepemilikan user_id pada ubah/hapus alamat.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: AddressGeocodingTest, AddressLocationTest, UserProfilePlaceholderEmailTest, OrderWithGeocodingTest.
```

---

### Tahap 3 — Discovery Marketplace, Pembobotan Toko, Peta Leaflet, & Estimasi Harga
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-03` / `US-03` / `AC-03`, `Bab 6.1`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-03 dan Bab 6.1 (DESIGN-RULES.md).
Status Eksisting: Modul SUDAH berjalan. Catat utang teknis T012 (refaktor frontend/app/stores/page.tsx) dikerjakan terpisah pada Tahap 11B.

File Terkait:
- Backend: app/Http/Controllers/Api/StoreController.php, app/Services/Store/StoreSearchService.php, app/Services/StoreService.php, app/Policies/StorePolicy.php, config/search.php
- Frontend: frontend/app/page.tsx, frontend/app/stores/page.tsx, frontend/app/stores/[id]/page.tsx, frontend/components/map/{StoreMap,DynamicStoreMap}.tsx

Tugas & Spesifikasi Wajib:
1. GET /api/v1/stores hanya menampilkan toko berstatus disetujui/aktif (status pending/suspended dilarang tampil ke publik).
2. StoreSearchService menghitung ranking Haversine + skor berbobot: jarak (0.30), rating (0.25), availability (0.15), review_count (0.10), service_match (0.10), performa (0.10), paginasi default 15 toko per halaman.
3. Render peta menggunakan Leaflet (^1.9.4) secara dinamis di client (DynamicStoreMap / StoreMap) bebas API berbayar.
4. Kalkulasi estimasi harga dilayani endpoint POST /api/v1/pricing/estimate secara server-side dari tabel service_prices.
5. UI halaman publik dan katalog mengikuti palet Navy + Teal DESIGN-RULES.md, menggunakan Skeleton, EmptyState, dan ErrorState.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: StoreSearchTest, StoreLocationCoordinatesAndRadiusTest, StoreAuthorizationTest.
```

---

### Tahap 4 — Onboarding Mitra Toko, Upgrade Peran, Upload Aman, & Persetujuan Admin
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-04` / `US-04` / `AC-04`, `NFR-SEC-04`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-04 dan NFR-SEC-04.
Status Eksisting: Alur registrasi mitra langsung & pengajuan upgrade customer sudah aktif dan terproteksi SecureFileUpload.

File Terkait:
- Backend: app/Http/Controllers/Api/Auth/MitraApiAuthController.php, app/Http/Controllers/Api/CustomerController.php (applyStore/applyCourier), app/Http/Controllers/Api/StoreDocumentController.php, app/Http/Controllers/Api/Portal/AdminPortalController.php, app/Services/File/SecureFileUpload.php
- Frontend: frontend/app/mitra/{register,status}/page.tsx, frontend/app/upgrade/page.tsx, frontend/app/onboard/page.tsx, frontend/app/admin/stores/page.tsx, frontend/components/map/StoreLocationPickerMap.tsx

Tugas & Spesifikasi Wajib:
1. Dukung dua jalur pengajuan toko:
   - Pendaftaran Mitra baru (/mitra/register -> POST /api/v1/auth/mitra/register, wajib email/telepon unik terpisah dari akun customer).
   - Upgrade akun Customer (/upgrade -> /api/v1/upgrades/store dan /api/v1/upgrades/courier) dengan penyimpanan draf localStorage (`laundryku_courier_draft`, `laundryku_store_draft`).
2. Dokumen legal usaha (KTP, NIB, foto tempat usaha) dan foto kurir diunggah melalui SecureFileUpload (pdf, jpg, jpeg, png, webp; maks 5 MB; cek finfo MIME) ke disk privat `storage/app/private` dan hanya dapat diakses lewat controller terotorisasi (StoreDocumentController / policy).
3. Super Admin memproses pengajuan (approve / reject beserta alasan) di /admin/stores dan mencatat setiap aksi keputusan ke audit_logs.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: MitraRegisterDirectTest, StoreOnboardingTest, MitraOnboardingE2ETest, CourierApplicationFlowTest, SecurityHardeningTestTest.
```

---

### Tahap 5 — Siklus Pesanan End-to-End (19 Status) & Penimbangan Berat Riil
- **Status di Repo:** `[SELESAI — Penguatan Bug E Dijadwalkan di Tahap 11C]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-05` / `US-05` / `AC-05`, `NFR-REL-01`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-05 dan docs/technical-notes.md (Bug E & threshold Rp50.000).
Status Eksisting: Core OrderService, 19 enum OrderStatus, dan OrderPolicy SUDAH berjalan. Penguatan validasi multi-item quantity_actual + actual_weight > 0 saat awaiting_price_approval ditangani terpisah di Tahap 11C.

File Terkait:
- Backend: app/Enums/OrderStatus.php, app/Http/Controllers/Api/OrderController.php, app/Services/Order/OrderService.php, app/Policies/OrderPolicy.php
- Frontend: frontend/app/stores/[id]/order/page.tsx, frontend/app/orders/page.tsx, frontend/app/orders/[id]/page.tsx, frontend/app/store/orders/page.tsx, frontend/app/store/orders/[id]/page.tsx

Tugas & Spesifikasi Wajib:
1. Pembuatan order (POST /api/v1/orders) minimal 1 item layanan aktif di toko disetujui; perhitungan biaya 100% di server: sum(max(qty * price_per_unit, minimum_charge)) + platform_fee (default Rp1.000) + delivery_fee + pickup_fee. Abaikan total/harga dari payload browser.
2. Terapkan 19 enum OrderStatus: created, waiting_pickup, courier_assigned, courier_to_customer, picked_up, in_transit_to_store, received_by_store, weighed, payment_required, paid, processing, ready_for_delivery, courier_assigned_for_delivery, out_for_delivery, delivered, completed, cancelled, pickup_failed, delivery_failed — serta persetujuan `awaiting_price_approval` bila selisih harga setelah input actual_weight melampaui `priceChangeThreshold = Rp50.000`.
3. Pisahkan kolom `estimated_weight` dan `actual_weight` tanpa saling menimpa.
4. Validasi transisi via OrderPolicy + peta transisi OrderService, serta tulis setiap perubahan status ke tabel `order_status_history`.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: OrderServiceTest, OrderStatusHistoryRecordTest, OrderFinancialBreakdownTest, OrderLifecycleAndCourierReassignmentSimulationTest, StoreServiceOrderValidationTest.
- E2E: `cd frontend && npx playwright test e2e/order-lifecycle.spec.ts`
```

---

### Tahap 6 — Pembayaran Digital Xendit, COD Terkontrol, Komisi & Settlement
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-06` / `US-06` / `AC-06`, `NFR-REL-01`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-06 dan aturan ketat AGENTS.md terkait uang.
Status Eksisting: Integrasi Xendit, idempotency webhook, batas COD, komisi dinamis, dan settlement sudah berjalan. DILARANG mengubah rumus uang/komisi tanpa instruksi eksplisit.

File Terkait:
- Backend: app/Http/Controllers/Api/{PaymentController,XenditWebhookController,SettlementController,RefundController}.php, app/Services/Payment/XenditService.php, app/Services/Settlement/SettlementService.php, app/Services/Refund/RefundService.php, app/Policies/{PaymentPolicy,SettlementPolicy,RefundPolicy}.php, config/xendit.php
- Frontend: frontend/app/orders/[id]/page.tsx, frontend/app/store/{payment,ledger}/page.tsx, frontend/app/admin/settings/page.tsx

Tugas & Spesifikasi Wajib:
1. Nilai tagihan yang dikirim ke XenditService selalu diambil dari total_amount / final_price di database server.
2. Dukung Invoice (`v2/invoices`, masa berlaku default 60 menit) dan Dynamic QRIS (`/qr_codes`, masa berlaku 2 menit) dengan idempotency key per percobaan pembayaran.
3. Webhook `POST /api/webhooks/xendit` dilindungi rate limiter `throttle:webhook` (60 req/menit), validasi header `x-callback-token`, dan deduplikasi event lewat tabel `payment_events` (`xendit_event_id`).
4. Batas pembayaran COD tunai maks `Rp500.000` (`settings.COD_MAX_AMOUNT`); konfirmasi tunai oleh kurir/toko dicatat ke `cod_collections`.
5. Komisi platform default `10%` (`settings.platform_commission_rate`) dikelola Super Admin (`/admin/settings` -> PATCH `/api/v1/admin/platform-settings/commission`) dengan pencatatan audit `ADMIN_COMMISSION_UPDATE`.
6. SettlementService membentuk periode (`generated -> verified`) hanya untuk order `completed` yang melewati `dispute_window_days = 7 hari`, bebas komplain aktif, dan tanpa rentang tanggal tumpang-tindih.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: PaymentTest, PaymentAuthorizationTest, SettlementTest, SettlementExclusionTest, RefundTest.
```

---

### Tahap 7 — Portal Kurir, 7 Status Tugas, GPS Tugas Aktif, & Polling 15 Detik
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-07` / `US-07` / `AC-07`, `NFR-PERF-03`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-07 dan NFR-PERF-03.
Status Eksisting: Portal kurir, CourierAssignmentService, GpsTrackingService, dan usePolling (15s) SUDAH aktif. Catat bahwa T009 sudah disatukan ke CourierTask::closeActiveFor.

File Terkait:
- Backend: app/Enums/CourierTaskStatus.php, app/Models/CourierTask.php, app/Http/Controllers/Api/Portal/CourierPortalController.php, app/Http/Controllers/Api/{CourierController,CourierTaskController}.php, app/Services/Courier/{CourierAssignmentService,GpsTrackingService}.php, app/Policies/CourierPolicy.php
- Frontend: frontend/app/courier/{page,tasks/page,orders/page}.tsx, frontend/hooks/usePolling.ts

Tugas & Spesifikasi Wajib:
1. Semua endpoint /api/v1/courier/* menolak akun kurir yang belum terverifikasi (`is_verified = false`) dengan HTTP 403.
2. Siklus tugas kurir mengikuti 7 enum CourierTaskStatus: assigned, accepted, arrived, picked_up, delivered, failed, cancelled. Penutupan tugas aktif tetap memakai `CourierTask::closeActiveFor`.
3. CourierAssignmentService menjaga sinkronisasi `orders.courier_id` dengan `user_id` kurir agar penghitung stats tab ("Tugas aktif saya", "Tugas tersedia", "Riwayat selesai") selalu akurat.
4. `GpsTrackingService::storeLocation()` hanya menyimpan titik koordinat ke `courier_gps` bila kurir memiliki tugas aktif; jika tidak ada tugas aktif, tolak dan catat warning log.
5. Dashboard /courier dan /courier/tasks melakukan silent polling setiap 15 detik melalui `usePolling.ts` (jeda otomatis saat tab tidak aktif) — jangan ganti dengan WebSocket Reverb.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: CourierPortalAuthorizationTest, CourierPortalTransitionTaskTest, CourierAssignmentTest, CourierDashboardTest, CourierTaskCloseActiveForTest.
```

---

### Tahap 8 — Human Chat Toko–Pelanggan, Notifikasi Asinkron, Rating, & Komplain
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-08` / `US-08` / `AC-08`, `NFR-SEC-02`, `NFR-REL-02`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-08, NFR-SEC-02, dan NFR-REL-02.
Status Eksisting: Modul Human Chat, SendUserNotificationJob, ReviewService, dan DisputeService SUDAH aktif.

File Terkait:
- Backend: app/Http/Controllers/Api/{ChatController,ReviewController,DisputeController}.php, app/Http/Controllers/Api/Portal/NotificationPortalController.php, app/Services/Notification/{NotificationService,InAppChannel,EmailChannel,WhatsAppChannel}.php, app/Services/WhatsApp/WhatsAppService.php, app/Services/Review/ReviewService.php, app/Services/Dispute/DisputeService.php, app/Policies/{ReviewPolicy,DisputePolicy}.php
- Frontend: frontend/app/chat/{page,[id]/page}.tsx, frontend/app/store/{chat,complaints,ratings}/page.tsx, frontend/app/notifications/page.tsx, frontend/components/complaint/{ComplaintFormModal,ComplaintListCard}.tsx

Tugas & Spesifikasi Wajib:
1. Human Chat (/chat dan /store/chat -> /api/v1/conversations) menjaga isolasi percakapan pasangan customer–toko beserta streaming lampiran terotorisasi (`GET /conversations/{id}/attachments/{messageId}`).
2. NotificationService mengirim notifikasi asinkron lewat `SendUserNotificationJob` (`QUEUE_CONNECTION=database`) pada 3 kanal (`in_app`, `email`, `whatsapp`), otomatis melewati kanal email bila user ber-email `wa_*@laundryku.test`, dan mengisolasi kegagalan tiap kanal via try-catch agar status order tidak rollback.
3. ReviewService menerima rating 1–5 hanya jika order berstatus `completed` (maks 1 ulasan per order) dan menghitung ulang rata-rata rating toko.
4. DisputeService mendukung 7 alasan komplain (`damaged_item`, `lost_item`, `late_delivery`, `poor_quality`, `wrong_item`, `overcharge`, `other`), SLA 7 hari, rate limit `dispute` 3/hari/user (`refund` 5/jam/user), dan penyelesaian manual wajib disertai `resolution_note` (tanpa auto-refund dari UI mitra).

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: CustomerStoreConversationTest, NotificationTest, WhatsAppNotificationTest, ReviewDisputeTest, DisputeRefundTest.
```

---

### Tahap 9 — Portal Mitra Toko (`/store/*`) & Portal Super Admin (`/admin/*`)
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`FR-09`, `FR-10`, `US-09`, `US-10`, `Bab 6.1–6.2`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian FR-09, FR-10, dan Bab 6.1–6.2.
Status Eksisting: Seluruh 14 halaman /store/* dan 9 menu operasional + login /admin/* sudah berjalan memakai data real MySQL 8.

File Terkait:
- Backend Mitra: app/Http/Controllers/Api/Portal/MitraPortalController.php, app/Http/Controllers/Api/{StoreOwnerController,StoreDashboardController}.php, app/Services/Store/StoreDashboardService.php
- Backend Admin: app/Http/Controllers/Api/Portal/AdminPortalController.php, app/Http/Controllers/Api/AdminController.php, app/Services/Dashboard/DashboardMetricsService.php, app/Policies/AuditLogPolicy.php
- Frontend Mitra (14 page): frontend/app/store/{page,orders/page,orders/[id]/page,services/page,pricing/page,payment/page,couriers/page,reports/page,ledger/page,complaints/page,ratings/page,chat/page,profile/page,settings/page}.tsx
- Frontend Admin (9 menu + login): frontend/app/admin/layout.tsx, frontend/app/admin/{login,page,orders,payments,stores,users,categories,settings/ai,settings,audit-logs}/page.tsx

Tugas & Spesifikasi Wajib:
1. Portal Mitra (/api/v1/store/*, /api/v1/mitra/*, /api/v1/store-admin/*) wajib mengikat seluruh query dan mutasi pada `store_id` milik mitra terotentikasi (strict tenant isolation).
2. Fitur Mitra: kelola katalog & bulk-status (/store/services), aturan diskon/harga di `store_settings` key `pricing_rules` (/store/pricing), kurir gerai (/store/couriers), rekening pencairan (`store_payout_accounts`, /store/payment), ledger (/store/ledger), serta laporan omset rentang `7D`/`30D`/`Bulan Ini`/`Kustom` + ekspor CSV (/store/reports).
3. Portal Super Admin menampilkan 9 menu sidebar (Dashboard, Pesanan, Transaksi & Bayar, Persetujuan Toko, Manajemen Pengguna, Kategori Layanan, Konfigurasi AI, Komisi Platform, Audit Log) dengan data agregasi real-time dari `DashboardMetricsService` (normalisasi status, GMV order completed, audit log terbaru).
4. Semua aksi administratif sensitif (approve/reject toko, ubah status user, komisi platform, model AI, login/gagal login) wajib menulis ke `audit_logs` beserta `before_value`, `after_value`, `ip_address`, dan `user_agent`.

Verifikasi (Definition of Done):
- Pastikan hijau via `composer test`: StorePricingAndReportsTest, StorePaymentMethodsTest, StoreDashboardMetricsTest, StoreSidebarCountsSyncTest, DashboardTenantIsolationTest, TenantIsolationTest, AdminDashboardMetricsTest, AdminPortalApiTest, AdminSettingsTest, DashboardResponseRegressionTest.
```

---

### Tahap 10 — LANDY Hybrid 3-Layer AI Assistant & Dynamic Model Switcher
- **Status di Repo:** `[SELESAI — MODE AUDIT / EXTEND SAJA]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`Bab 4.1–4.6`, `US-AI`, `NFR-PERF-02`, `Bab 6.1`)

```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md bagian Bab 4 (seluruh subbab 4.1–4.6), DESIGN.md (khusus /admin/settings/ai), dan NFR-PERF-02.
Status Eksisting: Engine LANDY 3-Layer (12 file service AI, 21 topik KB, 33 read-only tools, 165/165 hold-out) dan UI /admin/settings/ai SUDAH lengkap. JANGAN turunkan akurasi hold-out di bawah 165/165 dan JANGAN ubah token visual Cyan #0E7490 pada /admin/settings/ai.

File Terkait:
- Backend AI Engine: app/Services/Ai/{AiAssistantService,AiIntentRouter,AiKnowledgeBaseService,AiDataQueryService,AiOrderStatsService,AiTextNormalizer,AiToolRegistry,AiTopicGuardrail,OpenAiClient,AiClientInterface,AiProviderException,AiRateLimitException}.php
- Knowledge Base (21 topik): resources/ai/knowledge/{customer_guides,store_guides,courier_guides,admin_guides}.php
- Controllers & Route AI: app/Http/Controllers/Api/{AiChatController,ChatbotController}.php, AdminPortalController (aiSettings/testAiModel), frontend/app/api/chat/route.ts
- Frontend: frontend/components/chatbot/AiChatWidget.tsx, frontend/app/admin/settings/ai/page.tsx

Tugas & Spesifikasi Wajib:
1. Pertahankan Arsitektur 3-Layer Intent Routing:
   - Layer 0: Guardrail out-of-scope, anti-mutasi lewat chat (tolak sopan + beri langkah bernomor ke UI resmi), rate limit 30 req/jam, normalisasi slang (`AiTextNormalizer`).
   - Layer 1 (0 Token LLM): Sapaan, pencocokan 21 topik KB sesuai role (customer 8, store 7, courier 3, admin 3) + kueri data terstruktur via `AiDataQueryService` / `AiOrderStatsService` + compound multi-intent pemisah `---`.
   - Layer 2 (OpenRouter LLM + Tool Calling): Sliding window `max_history = 6`, konteks KB relevan non-PII, `max_tool_rounds = 5`, eksekusi terotorisasi lewat **33 Read-Only Tools** (3 Shared, 6 Customer, 7 Store, 4 Courier, 9 Super Admin, 4 alias legacy).
   - Layer 3 (Fallback Aman): Klarifikasi topik per role + rujukan CS resmi `cs@laundryku.test` (timeout API OpenRouter maks 30s, tanpa stack trace).
2. Keamanan Mutlak AI: identitas user/role/store selalu dibaca dari guard backend; wajib `maskEmail` & `maskPhone` sebelum dikirim ke log atau hasil pencarian user admin; dilarang mengekspose password, token, atau google2fa_secret.
3. Dynamic Model Switcher (/admin/settings/ai): kelola 8 model preset OpenRouter (4 Free termasuk default `liquid/lfm-2.5-2.6b:free` dan 4 Paid), custom model ID, tuning temperature (`0.0`/`0.2`/`0.5`), `max_tool_rounds`, live diagnostic ping (`/api/v1/admin/ai-settings/test`), cache invalidation, serta log audit `UPDATE_AI_MODEL`. Pertahankan desain Cyan `#0E7490`/`#155E75` (`DESIGN.md`).

Verifikasi (Definition of Done):
- Pastikan 100% (165/165) pada: AiHoldOutEvaluationTest.
- Pastikan hijau via `composer test`: AiAssistantTest, SuperAdminAiAssistantTest, AiDataQueryTest, AiDataQueryServiceWeighingTest, AiRoleFunctionCallingTest, AiParaphraseTest, AiAssistantTopicGuardrailTest, AiOrderStatsTest, AiDiagnosisEvaluationTest, AdminAiSettingsTest.
```

---

### Tahap 11 — Lanjutan Penguatan & Utang Teknis Terkendali (`OPEN / NEXT`)
- **Status di Repo:** `[OPEN — SIAP DIEKSEKUSI SATU SUB-TAHAP PER SESI]`
- **Referensi SSOT:** [`prd.md`](file:///c:/laragon/www/laundryku/prd.md) (`Bab 7.2`), [`docs/technical-notes.md`](file:///c:/laragon/www/laundryku/docs/technical-notes.md), [`docs/go-live-checklist.md`](file:///c:/laragon/www/laundryku/docs/go-live-checklist.md)

> **Aturan Eksekusi Tahap 11:** Kerjakan **satu sub-tahap (`11A`..`11E`) per sesi agent** dan commit setelah `composer test` hijau; jangan menggabungkan refaktor otorisasi RBAC (`11A`) dengan refaktor frontend (`11B`) atau logika order (`11C`).

#### Prompt `11A` — Penyatuan Dual Role Authority / *Split-Brain* (`T011`)
```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md Bab 2.2 & Bab 7.2 (T011), docs/technical-notes.md bagian T011, serta config/auth.php & app/Policies/*.
Tugas: Audit dan rencanakan migrasi bertahap untuk menghentikan ketergantungan pada kolom datar `users.role` (`customer`, `store_admin`, `courier`, `super_admin`) menuju satu sumber kebenaran RBAC (`roles`, `permissions`, `user_roles`, `role_permissions`) + Laravel Policies tanpa memutus isolasi 4 guard (web, mitra, kurir, admin) dan BFF cookies.
Batasan:
- Jangan ubah perilaku `User::tokenCan()` real-time ke DB yang melemahkan keamanan.
- Wajib pertahankan isolasi `/switch-role` = 404 dan 4 pintu masuk login.
- Jangan langsung drop kolom `users.role` sebelum seluruh controller/service/test/AI tool yang masih membaca `$user->role` diaudit dan bermigrasi aman lewat migrasi baru (bukan edit migrasi lama).
Verifikasi (DoD): Jalankan penuh `composer test` (termasuk RbacTest, RbacAcrossRolesTest, RolePortalIsolationTest, AiRoleFunctionCallingTest) dan catat pembaruan status T011 di docs/technical-notes.md.
```

#### Prompt `11B` — Refaktor Halaman Cari Toko `/stores` + Unit Test UI (`T012`)
```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md Bab 3 FR-03, Bab 6.1 (DESIGN-RULES.md), Bab 7.2 (T012), dan docs/technical-notes.md bagian T012.
File Target: frontend/app/stores/page.tsx (±700 baris), frontend/components/map/{DynamicStoreMap,StoreMap}.tsx.
Tugas: Pecah frontend/app/stores/page.tsx menjadi modul bersih tanpa mengubah kontrak API maupun desain UI Navy+Teal:
1. Ekstrak logika resolusi koordinat & event `address-changed` menjadi custom hook `useResolvedLocation()`.
2. Pisahkan panel daftar toko, filter/sortir, dan peta Leaflet ke komponen terfokus yang bersih.
3. Pertahankan tampilan, responsivitas mobile-first, Skeleton, EmptyState, dan ErrorState sesuai DESIGN-RULES.md.
4. Tambahkan pengujian komponen/E2E untuk memverifikasi sinkronisasi lokasi header, filter jarak/rating, dan render peta.
Verifikasi (DoD): `cd frontend && npm run lint && npm run build && npx playwright test`, plus verifikasi manual visual desktop & mobile.
```

#### Prompt `11C` — Penguatan Validasi Berat `awaiting_price_approval` & Field `Bug E`
```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md FR-05 & Bab 7.2 poin 5, serta docs/technical-notes.md (bagian Bug E & Celah Validasi Berat pada Transisi Persetujuan Harga).
File Target: app/Services/Order/OrderService.php, app/Http/Controllers/Api/OrderController.php, Request/Controller terkait penimbangan mitra, serta frontend/app/store/orders/[id]/page.tsx.
Tugas:
1. Perkuat validasi agar perpindahan ke `awaiting_price_approval` menolak berat aktual <= 0 (`actual_weight > 0`).
2. Audit dan selaraskan pencatatan `order_items.quantity_actual` pada pesanan multi-item sesuai keputusan catatan teknis Bug E tanpa mengubah rumus komisi/platform fee atau merusak kontrak API/UI order tunggal.
Verifikasi (DoD): Tambahkan feature test baru untuk kasus `actual_weight <= 0` dan multi-item `quantity_actual`, lalu jalankan `composer test`.
```

#### Prompt `11D` — Evaluasi Eloquent `$casts` Enum (`T010`)
```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md Bab 7.2 poin 3 (T010) & docs/technical-notes.md (T010), app/Enums/{OrderStatus,CourierTaskStatus}.php.
Tugas: Audit dampak penambahan Eloquent `$casts` untuk `OrderStatus` dan `CourierTaskStatus` terhadap query builder mentah (`DB::table`, `whereIn` string, agregasi `DashboardMetricsService`/`StoreDashboardService`), lalu terapkan hanya bila seluruh kueri & test tetap kompatibel (atau dokumentasikan batas amannya).
Verifikasi (DoD): Jalankan penuh `composer test` (termasuk OrderStatusHistoryRecordTest, AdminDashboardMetricsTest, CourierPortalTransitionTaskTest).
```

#### Prompt `11E` — Sinkronisasi Dokumen Pendukung & Persiapan Go-Live (`T008`)
```text
[SERTAKAN BAGIAN A TERLEBIH DAHULU]

Konteks: Baca prd.md Bab 7.2 poin 6, docs/go-live-checklist.md, OPEN-ISSUES.md, AGENTS.md, dan docs/technical-notes.md (T008).
Tugas:
1. Selaraskan dokumen repo pendukung yang masih tertinggal terhadap `prd.md` v3.0:
   - Sinkronkan `database/schema.sql` atau dokumentasikan dump skema 58 tabel aktual dari MySQL 8.
   - Mutakhirkan `README.md` dan `AGENTS.md` (pastikan referensi ke Next.js 16 + Sanctum v4 + MySQL 8 konsisten dengan kondisi produksi saat ini).
2. Eksekusi pemeriksaan pra-rilis sesuai `docs/go-live-checklist.md`:
   - Pastikan kredensial uji `admin@laundryku.test` (`T008`) dihapus atau dirotasi password kuat + wajib 2FA TOTP di environment produksi.
   - Verifikasi `OTP_DEBUG_RESPONSE=false`, `AI_MOCK_IN_LOCAL=false`, kunci live Xendit/Fonnte/SMTP/OpenRouter valid, `QUEUE_CONNECTION=database` dengan worker aktif, dan backup/restore DB (`docs/backup-restore.md`) teruji.
```

---

## Bagian D — Protokol Verifikasi & Eksekusi Standar

Gunakan perintah tepat berikut saat mengeksekusi salah satu tahap di mesin pengembangan Windows (Laragon):

```powershell
# 1. Backend full regression suite (dilindungi DatabaseGuardTest)
composer test

# 2. Menjalankan 1 file test PHPUnit secara terisolasi lewat PHP absolut Laragon
#    (pastikan config test aman via bin/test-runner.php atau composer test -- --filter=<NamaTest>)
composer test -- --filter=AiHoldOutEvaluationTest

# 3. Wajib setelah mengubah file .env lokal pada Apache Laragon mod_php
composer dev:cache

# 4. Build, lint, & E2E frontend Next.js
cd frontend
npm run lint
npm run build
npx playwright test e2e/order-lifecycle.spec.ts
```

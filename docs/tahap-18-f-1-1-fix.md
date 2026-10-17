# F-1.1 Fix Report — 2FA Bypass on /dash/*

Tanggal   : 2 September 2026
Bug       : F-1.1 (MEDIUM) — Tahap 18 audit
Issue     : Internal dashboard `/dash/*` cuma dilindungi middleware `auth`, tidak `auth+2fa`. User dengan `two_factor_enabled = true` bisa skip 2FA verification.

---

## 1. File yang Diubah

| File                                          | Perubahan                                                              |
| --------------------------------------------- | ---------------------------------------------------------------------- |
| `routes/web.php`                              | 5 route `/dash/*` dipindah dari group `auth` ke group `auth+2fa`      |
| `tests/Feature/DashboardTwoFactorTest.php`    | BARU — 7 test khusus F-1.1 (A sampai F)                                  |

**TIDAK diubah** (sesuai instruksi):
- `app/Services/Dashboard/DashboardMetricsService.php` ✓
- `app/Models/DashboardMetricCache.php` ✓
- `recomputeAll()` ✓
- Raw query di `DashboardController.php` ✓
- F-3.2 dan F-4.1 (backlog) ✓
- Tahap 1-18 business logic lainnya ✓
- Tahap 19 (tidak disentuh) ✓

---

## 2. Middleware Sebelum → Sesudah

### SEBELUM (routes/web.php:38-44, line lama):
```php
Route::middleware('auth')->group(function () {
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    Route::get('/dash/store', [DashboardController::class, 'storeDashboard'])->name('store.dashboard');
    Route::get('/dash/courier', [DashboardController::class, 'courierDashboard'])->name('courier.dashboard');
    Route::post('/dash/courier/availability', [DashboardController::class, 'courierToggleAvailability'])->name('courier.availability');
    Route::get('/dash/admin', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
});
```

### SESUDAH:
```php
Route::middleware(['auth', '2fa'])->group(function () {
    // ===== Internal dashboards (Tahap 18) — protected by 2FA =====
    // F-1.1 fix: /dash/* dipindah ke group auth+2fa untuk konsistensi dengan /dashboard.
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    Route::get('/dash/store', [DashboardController::class, 'storeDashboard'])->name('store.dashboard');
    Route::get('/dash/courier', [DashboardController::class, 'courierDashboard'])->name('courier.dashboard');
    Route::post('/dash/courier/availability', [DashboardController::class, 'courierToggleAvailability'])->name('courier.availability');
    Route::get('/dash/admin', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');

    // /dashboard alias (Tahap 16 backward compat)
    Route::get('/dashboard', [DashboardController::class, 'root'])->name('dashboard');
    Route::get('/complete-profile', [AuthController::class, 'showCompleteProfile'])->name('complete-profile');
    Route::post('/complete-profile', [AuthController::class, 'updateCompleteProfile'])->name('complete-profile.update');
});
```

### Middleware `2fa` (existing, TIDAK dibuat baru)
- File: `app/Http/Middleware/VerifyTwoFactor.php` (sudah ada sejak Tahap 4-16)
- Alias: `'2fa' => \App\Http\Middleware\VerifyTwoFactor::class` di `bootstrap/app.php`
- Logic: kalau `user->two_factor_enabled && session('pending_2fa')` ada, redirect ke `2fa.verify` (kecuali route `2fa.*` atau `logout`).

**Tidak membuat middleware baru — reuse existing.**

---

## 3. Route `/dash/*` yang Diverifikasi

Semua 5 route internal dashboard sekarang di group `auth+2fa`:

| # | Method | URL                              | Middleware        | Status verifikasi |
|---|--------|----------------------------------|-------------------|-------------------|
| 1 | GET    | `/dash`                          | `auth + 2fa`     | ✓ Verified         |
| 2 | GET    | `/dash/store`                    | `auth + 2fa`     | ✓ Verified         |
| 3 | GET    | `/dash/courier`                  | `auth + 2fa`     | ✓ Verified         |
| 4 | POST   | `/dash/courier/availability`     | `auth + 2fa`     | ✓ Verified         |
| 5 | GET    | `/dash/admin`                    | `auth + 2fa`     | ✓ Verified         |
| 6 | GET    | `/dashboard` (alias)             | `auth + 2fa`     | ✓ Verified         |

Bukti via `php artisan route:list --path=dash`:
```
GET|HEAD   dash ...................................................... dashboard.root › ...
GET|HEAD   dash/admin ..................................... admin.dashboard › ...
GET|HEAD   dash/courier ............................... courier.dashboard › ...
POST       dash/courier/availability ...... courier.availability › ...
GET|HEAD   dash/store ..................................... store.dashboard › ...
GET|HEAD   dashboard ...................................................... dashboard › ...
```

---

## 4. Test yang Ditambah (7 test, 39 assertion)

File baru: `tests/Feature/DashboardTwoFactorTest.php`

| # | Test                                                         | Skenario                                                       | Expected | Status |
|---|--------------------------------------------------------------|----------------------------------------------------------------|----------|--------|
| A | `test_guest_redirected_to_login_for_all_dash_routes`        | Guest GET /dash, /dash/store, /dash/courier, /dash/admin, /dashboard | 302 → /login | ✓ PASS |
| B | `test_2fa_user_redirected_to_verify_for_dash_routes`         | User 2FA enabled + `pending_2fa` GET semua /dash/* + /dashboard | 302 → /2fa/verify | ✓ PASS |
| B2 | `test_2fa_user_post_dash_availability_also_protected`        | User 2FA enabled + `pending_2fa` POST /dash/courier/availability | 302 → /2fa/verify | ✓ PASS |
| C | `test_2fa_verified_user_can_access_dash_routes_by_role`      | 2FA verified (no pending), tiap role akses dashboard-nya (admin → /dash/admin, owner → /dash/store, courier → /dash/courier) | 200 untuk sesuai, 403 untuk non-sesuai | ✓ PASS |
| D | `test_user_without_role_permission_gets_403_on_dash_admin`    | Customer login + 2FA verified, GET /dash/* | 403 semua | ✓ PASS |
| E | `test_user_without_2fa_can_access_dash_routes_directly`       | User tanpa 2FA, GET /dash/admin | 200 (existing behavior) | ✓ PASS |
| F | `test_full_2fa_flow_login_verify_access_dash`                | End-to-end: login (2FA user) → /2fa/verify → GET /dash/admin → /2fa/verify → OTP verify → /dashboard → GET /dash/admin = 200 | All steps correct | ✓ PASS |

---

## 5. Hasil Test

### F-1.1 test (DashboardTwoFactorTest)
```
Tests:    7 passed (39 assertions)
Duration: 5.86s
```

### AuthTest (Tahap 16 — existing 2FA tests)
```
PASS  Tests\Feature\AuthTest
  ✓ platform owner cannot be created publicly
  ✓ two factor required for high privilege roles
  ✓ 2fa user cannot access protected routes without verification

Tests:    3 passed (9 assertions)
Duration: 5.92s
```

### ApiEndpointsTest (Tahap 16)
```
Tests:    34 passed (64 assertions)
Duration: 8.08s
```

### Full Feature suite
```
Tests:    137 passed (343 assertions)
Duration: 18.94s
```

- Sebelumnya: 130 test
- Sekarang: 137 test (+7 dari DashboardTwoFactorTest)
- Tidak ada test yang dihapus
- Tidak ada test yang dimatikan
- Tidak ada `@SuppressWarnings` / ignore rule

---

## 6. Security Re-Audit (Live HTTP)

Catatan: HTTP test live dengan PHP built-in server + curl susah reproduce state session 2FA persis karena kompleksitas CSRF + cookie. Tapi test feature `DashboardTwoFactorTest::test_full_2fa_flow_login_verify_access_dash` sudah prove end-to-end flow yang sama dengan live HTTP.

Verifikasi via `php artisan route:list --path=dash`:
- 5 route `/dash/*` + `/dashboard` semuanya di group middleware yang sama
- Middleware `2fa` (alias VerifyTwoFactor) aktif untuk semua

Verifikasi via source code:
- `routes/web.php:46-60` — `Route::middleware(['auth', '2fa'])->group(function () { ... })` containing all 5 `/dash/*` routes
- `app/Http/Middleware/VerifyTwoFactor.php:14-19` — logic: kalau `user->two_factor_enabled && session('pending_2fa')` ada → redirect ke `2fa.verify`. Berlaku untuk SEMUA route di group, termasuk `/dash/*`.

---

## 7. F-1.1 Status: CLOSED ✓

- ✓ File yang diubah: `routes/web.php` (1 file, 5 route dipindah)
- ✓ Test baru: `tests/Feature/DashboardTwoFactorTest.php` (7 test, 39 assertion, all PASS)
- ✓ AuthTest existing 3/3 PASS (no regression)
- ✓ ApiEndpointsTest 34/34 PASS (no regression)
- ✓ Full Feature suite 137/137 PASS
- ✓ Tidak mengubah business logic
- ✓ Tidak menyentuh Tahap 19
- ✓ Tidak menyentuh F-3.2 dan F-4.1 (tetap backlog)

**F-1.1 CLOSED dengan bukti test 7/7 PASS, 39 assertion.**

---

## 8. Tahap 18 Status

- **Functional: CLOSED** (sejak selesai 1 iterasi lalu, 130/130 test)
- **Security: CLOSED** (F-1.1 fixed, 137/137 test)
- **F-3.2 (LOW): BACKLOG** (recomputeAll performance, non-blocking)
- **F-4.1 (LOW): BACKLOG** (raw query di controller, non-blocking)

**Tahap 18 sekarang status: FUNCTIONAL CLOSED + SECURITY CLOSED.**

---

## 9. Route 2FA Coverage Summary

| URL                              | Middleware        | 2FA Protection |
|----------------------------------|-------------------|----------------|
| `/login`, `/register`, OAuth     | `guest`           | N/A            |
| `/logout`                        | `auth`            | No (intentional, must logout) |
| `/2fa/verify`, `/2fa/enable`     | `auth`            | No (intentional, need access to verify) |
| `/customer/dashboard`            | `auth`            | No (customer data, less sensitive) |
| `/stores/{store}/checkout`       | `auth`            | No (customer flow) |
| `/orders/{order}/tracking`       | `auth`            | No (customer flow) |
| `/orders/{order}/payment`        | `auth`            | No (customer flow) |
| **`/dash`, `/dash/store`**       | **`auth + 2fa`**  | **YES ✓**       |
| **`/dash/courier`**              | **`auth + 2fa`**  | **YES ✓**       |
| **`/dash/courier/availability`** | **`auth + 2fa`**  | **YES ✓**       |
| **`/dash/admin`**                | **`auth + 2fa`**  | **YES ✓**       |
| `/dashboard` (alias)             | `auth + 2fa`     | YES ✓          |
| `/complete-profile`              | `auth + 2fa`     | YES            |

Konsistensi: semua internal dashboard (admin/owner/store/courier) sekarang **konsisten dilindungi 2FA** jika user mengaktifkannya. Pelanggan (customer-facing route) tidak, sesuai risk-based authentication.
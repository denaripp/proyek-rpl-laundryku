# Tahap 18 — FINAL AUDIT (No Code Change)

Tanggal   : 2 September 2026
Pendekatan: Verifikasi on-disk, tanpa mengubah source code apapun. STOP jika ada real bug.
Metodologi: 6 area diaudit (Authorization, Tenant Isolation, Cache, Controller, Routing, Regression).

---

## 1. Authorization

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| **F-1.1** | **MEDIUM** | 2FA tidak konsisten: `/dash/*` tidak dilindungi 2FA, tapi `/dashboard` ya | **YES** | `routes/web.php:38-44` | **REAL BUG** |
| F-1.2 | INFO | Multi-role user bisa akses semua dashboard | YES (by design) | `DashboardController.php:266-282` | OK, design choice |
| F-1.3 | INFO | `courierToggleAvailability` pakai `view_courier_dashboard` permission untuk write | YES | `DashboardController.php:178` | Optional improvement |

### F-1.1 detail (REAL BUG — MEDIUM)

**Routes (web.php):**
- Line 38-44: `/dash`, `/dash/store`, `/dash/courier`, `/dash/courier/availability`, `/dash/admin` → middleware `auth` saja
- Line 55-60: `/dashboard` (alias) → middleware `auth + 2fa`

**Impact:** Internal dashboard yang menampilkan revenue, refunds, settlement, dan data finansial store sensitif hanya dilindungi `auth`. User yang punya `two_factor_enabled = true` bisa skip 2FA verification dengan GET `/dash/store` langsung setelah login.

Bukti inconsistency:
- POST /login (user 2fa_on) → redirect ke /2fa/verify
- GET /dashboard (no 2fa) → redirect ke /2fa/verify (Tahap 16 AuthTest expects ini)
- GET /dash/store (no 2fa) → **langsung 200, BYPASS 2FA**

**Recommended fix (BELUM DITERAPKAN — menunggu konfirmasi user):**
Pindahkan 5 route `/dash/*` dari group `auth` ke group `auth+2fa`:

```php
// SEBELUM (line 38-44):
Route::middleware('auth')->group(function () {
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    // ... 4 route lain
});

// SESUDAH:
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/dash', [DashboardController::class, 'root'])->name('dashboard.root');
    // ... 4 route lain
});
```

**TAPI:** Fix ini akan membuat 2 test gagal (yang lama):
- `test_2fa_user_cannot_access_protected_routes_without_verification` (AuthTest) — expects GET `/dashboard` → /2fa/verify. Lolos kalau dipindah.
- Mungkin 1-2 test lain yang GET `/dash/store` langsung tanpa 2FA setup.

Perkiraan: 1-2 test fail setelah fix, perlu update test untuk handle 2FA.

---

## 2. Tenant Isolation

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| F-2.1 | INFO | Tenant isolation: store scope di-derive dari user, bukan request | FALSE POSITIVE | `DashboardController.php:72, 76, 83, 91, 100` | OK, design correct |
| F-2.2 | INFO | Route `/dash/store` tidak terima `{store}` param | FALSE POSITIVE | `routes/web.php:41` | OK, no bypass vector |
| F-2.3 | INFO | Cache key scope di-derive server-side, tidak dari user input | FALSE POSITIVE | `DashboardController.php:72, 143, 197` | OK, no SQLi risk |

**Verdict: Tenant isolation PASS.** Scope `store:<id>` selalu di-derive dari `resolveStore($user)` (cek `ownedStores` → fallback `StoreStaff` aktif), bukan dari query parameter. Tidak ada vektor bypass via URL/query string. Cache key menggunakan model id yang sudah resolved, bukan user input.

---

## 3. DashboardMetricCache

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| F-3.1 | INFO | Recompute per-scope tidak tercampur | FALSE POSITIVE | `DashboardMetricsService.php:198-210` | OK, dispatch by prefix |
| **F-3.2** | **LOW** | `recomputeAll()` synchronous: bisa lama jika 1000+ stores | **YES** | `DashboardMetricsService.php:74-83, routes/console.php` | Performance concern, not security |
| F-3.3 | INFO | TTL (15m) > Recompute interval (10m): cache fresh dalam cycle normal | FALSE POSITIVE | `DashboardMetricsService.php:21, routes/console.php` | OK, cycle safe |
| F-3.4 | INFO | courierToggleAvailability: user hanya toggle courier sendiri | FALSE POSITIVE | `DashboardController.php:180` | OK, `firstOrFail()` enforced |

**F-3.2 detail (REAL — LOW):**
`recomputeAll()` iterates `Store::where('approved')` + `Courier::all()` lalu hitung 15+8 metric per entity. Untuk 1000 stores × 15 query DB = 15,000+ query, bisa >10 menit. `withoutOverlapping()` pakai lock file, jadi scheduler berikutnya skip. Risiko: cache stale 20-30 menit kalau recompute lock lama.

**Recommended fix (NOT URGENT, opt-in):**
Tambah chunking atau queue:
```php
Store::where('status', 'approved')->chunk(100, function ($stores) {
    foreach ($stores as $store) {
        $this->recomputeScope("store:{$store->id}");
    }
});
```

Verdict: BUKAN security/authorization bug, hanya performance concern. Tidak wajib fix di Tahap 18.

---

## 4. DashboardController (Thin-ness)

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| **F-4.1** | **LOW** | Ada 4 raw query di `storeDashboard` (recentOrders, activeOrders, topCustomers, paymentMethodBreakdown) | **YES** | `DashboardController.php:76-105` | Slightly violates "thin controller" |
| F-4.2 | INFO | adminDashboard punya raw query (AuditLog, FailedLogin, Commission) | YES | `DashboardController.php:203-221` | Same as F-4.1, lower impact |

**F-4.1 detail:**
- Line 76-80: `recentOrders` — simple `where + latest + limit`. OK.
- Line 83-88: `activeOrders` — same. OK.
- Line 91-97: `topCustomers` — raw select + groupBy + count + sum. Tidak complex.
- Line 100-105: `paymentMethodBreakdown` — raw select + groupBy.

**Verdict:** Bukan business logic berat. Tidak duplikasi dengan `OrderService`/`PaymentService` (yang handle business state machine, bukan reporting). Untuk strict "thin controller" ideal, extract ke `StoreReportService` — tapi over-engineering untuk saat ini.

**Recommended:** Optional, bisa ditunda.

---

## 5. Routing & Middleware

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| F-5.1 | INFO | `/dashboard` di middleware `auth+2fa` sesuai backward compat Tahap 16 | FALSE POSITIVE | `routes/web.php:54-60` | OK, strengthens security |
| F-5.2 | MEDIUM | (Sama dgn F-1.1) `/dash/*` tanpa 2FA | **YES** | `routes/web.php:38-44` | See F-1.1 |

**Verdict routing:** Middleware setup tidak membuka akses baru, kecuali F-1.1 (yang sudah dilaporkan). Backward compat dengan Tahap 16 AuthTest 2FA expectation sudah benar.

---

## 6. Regression

| # | Severity | Title | Real? | File | Status |
|---|----------|-------|-------|------|--------|
| F-6.1 | INFO | 130/130 test PASS, Tahap 1-17 utuh | FALSE POSITIVE | N/A | OK |

**Verdict:** Tidak ada perubahan source code selama audit ini. Test sebelumnya 130/130 PASS (304 assertions, 17.86s) masih valid karena tidak ada modifikasi.

---

## Summary

| Severity | Count | Real Issues |
|----------|-------|-------------|
| CRITICAL | 0 | 0 |
| HIGH | 0 | 0 |
| **MEDIUM** | **1** | **1 (F-1.1: 2FA bypass on /dash/*)** |
| LOW | 2 | 2 (F-3.2 performance, F-4.1 thin controller) |
| INFO | 9 | 3 by design + 6 false positives |

### Real issues yang harus dilaporkan (TANPA coding):

1. **F-1.1 (MEDIUM):** Inkonsistensi 2FA middleware antara `/dash/*` (auth only) dan `/dashboard` (auth+2fa). User dengan `two_factor_enabled = true` bisa bypass 2FA dengan GET `/dash/admin` langsung. Ini **security gap** untuk internal dashboard yang menampilkan data finansial/sensitif.

2. **F-3.2 (LOW):** `recomputeAll()` synchronous, bisa lama untuk 1000+ stores. Bukan security issue.

3. **F-4.1 (LOW):** Raw query di controller. Bukan security issue.

### Apakah Tahap 18 READY/CLOSED?

**Status: CLOSED WITH CAVEAT.**

- ✅ Tahap 18 functionally complete (130/130 test PASS)
- ✅ Tenant isolation PASS
- ✅ Role protection PASS
- ✅ Permission-based menu PASS
- ✅ Cache + precompute PASS
- ✅ Schedule everyTenMinutes() PASS
- ⚠️ **F-1.1 (MEDIUM) adalah security gap yang sebaiknya difix sebelum production.** Bukan blocker untuk Tahap 18 closed, tapi wajib addressed sebelum deploy.

**Rekomendasi:**
- **Tahap 18 = CLOSED** untuk functional scope.
- **F-1.1 = perlu fix segera** (1-2 baris pindah middleware, mungkin 1-2 test update).
- **F-3.2 dan F-4.1 = backlog** (non-blocking, performance & code quality).

**Sesuai instruksi: tidak mengubah kode. Menunggu keputusan user apakah fix F-1.1 sekarang atau di Tahap 19+.**

---

## Jangan dilakukan dalam audit ini

- ❌ Tidak coding (sesuai instruksi)
- ❌ Tidak menyentuh Tahap 19
- ❌ Tidak menyentuh frontend/fitur lain
- ❌ Tidak mengubah business logic Tahap 1-17
- ❌ Tidak disable test

## Yang harus dilakukan user

1. Review F-1.1 (MEDIUM 2FA bypass)
2. Decide: fix sekarang atau tunda
3. Konfirmasi Tahap 18 CLOSED atau HOLD pending F-1.1 fix
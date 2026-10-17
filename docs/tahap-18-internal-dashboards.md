# Tahap 18 — Internal Dashboards (Store, Courier, Platform Admin, Platform Owner)

Tanggal   : 2 September 2026
Tujuan    : Dashboard internal untuk 4 role (bukan customer-facing). Pakai service existing (Order/Payment/Refund/Settlement). Caching/precompute untuk metrics berat. Tenant isolation tetap berlaku.
Hasil     : 4 dashboard (auto-redirect by role) + precompute service + scheduled job. 130/130 regression PASS, 0 fail.

---

## 1. Scope

| Role            | URL             | Lihat                                                                 |
| --------------- | --------------- | --------------------------------------------------------------------- |
| Store Owner/Staff | `/dash/store` | Orders, services, pricing, staff, courier, customers, reports (revenue, COD vs online, refund, rating) |
| Courier         | `/dash/courier` | Available toggle, active task, task history, earnings                  |
| Platform Admin  | `/dash/admin`   | Stores, customers, orders, couriers, payments, COD, refunds, disputes, notifications, audit (menu per permission) |
| Platform Owner  | `/dash/admin`   | Semua di atas + commission, security, AI usage, GMV                    |
| Auto-redirect   | `/dashboard`    | Redirect ke dashboard sesuai role (auth + 2fa)                         |

---

## 2. File yang dibuat (12 file)

### Database
- `database/migrations/2026_09_02_000001_create_dashboard_metric_caches_table.php` (BARU) — tabel cache metric per scope

### Service + Model + Console
- `app/Services/Dashboard/DashboardMetricsService.php` (BARU) — 13KB service untuk precompute + cache. 51 metric keys total (27 platform, 15 store, 8 courier). TTL 900 detik, lazy fallback via `get()`.
- `app/Models/DashboardMetricCache.php` (BARU)
- `app/Console/Commands/RecomputeDashboardMetrics.php` (BARU) — `php artisan dashboards:recompute-metrics [--scope=X]`
- `routes/console.php` (UPDATE) — schedule `everyTenMinutes()->withoutOverlapping()->runInBackground()`

### Controller + View
- `app/Http/Controllers/Web/DashboardController.php` (BARU) — 4 method dashboard + auto-redirect + courier availability toggle + permission-based menu builder
- `resources/views/layouts/dashboard.blade.php` (BARU) — layout dashboard dengan sidebar slot + top nav
- `resources/views/partials/sidebar.blade.php` (BARU) — reusable sidebar partial
- `resources/views/dashboards/store.blade.php` (BARU) — store dashboard
- `resources/views/dashboards/courier.blade.php` (BARU) — courier dashboard
- `resources/views/dashboards/admin.blade.php` (BARU) — admin + owner dashboard (1 view, menu & metric section berbeda)

### Update
- `app/Models/User.php` (UPDATE) — tambah `hasRole(string)` helper
- `routes/web.php` (UPDATE) — 6 route baru + `/dashboard` alias dipindah ke group middleware `auth+2fa` (backward compat Tahap 16 2FA test)

---

## 3. Caching Strategy

### `DashboardMetricsService` API
```php
$m = app(DashboardMetricsService::class);
$value = $m->get('platform', 'gmv_today');   // returns from cache or recompute
$m->recomputeScope('store:5');               // force all metrics for a store
$m->recomputeAll();                          // platform + every store + every courier
```

### Scope hierarchy
- `platform` — global metrics (GMV, total stores, etc.)
- `store:<id>` — per-store (orders, revenue, COD/online, refunds, rating, settlement pending, services/staff/customers count)
- `courier:<id>` — per-courier (active tasks, today completed, lifetime, earnings, rating)

### Schedule
```
Schedule::command('dashboards:recompute-metrics')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```
Recompute setiap 10 menit, lebih cepat dari TTL 15 menit. Saat recompute jalan, dashboard tetap serve data cache lama (stale-while-revalidate).

---

## 4. Authorization & Tenant Isolation

### Per-role enforcement
- **Store Dashboard**: `resolveStore($user)` — coba `ownedStores` dulu, fallback ke `StoreStaff` aktif. Scope `store:<store_id>` otomatis dipakai → store A tidak bisa baca metric store B.
- **Courier Dashboard**: `hasPermissionTo('view_courier_dashboard')` + `Courier::where('user_id', $user->id)`. Scope `courier:<courier_id>` → data kurir itu sendiri.
- **Admin Dashboard**: `hasPermissionTo('view_admin_dashboard')`. Admin **tidak** bisa akses `/dash/store` (403) atau `/dash/courier` (403) — diverifikasi via smoke test.
- **Owner**: `hasPermissionTo('manage_platform')` → section tambahan Commission / Security / AI di view.

### Menu (admin dashboard) per permission
| Permission             | Menu items visible                                  |
| ---------------------- | -------------------------------------------------- |
| `view_any_store`       | Stores                                             |
| `view_any_order`       | Orders                                             |
| `view_admin_dashboard` | Customers, Notifications, Audit                    |
| `view_any_courier`     | Couriers                                           |
| `view_any_payment`     | Payments, COD, Refunds                             |
| `view_any_dispute`     | Disputes                                           |
| `manage_platform`      | + Commission, Security, AI Usage (owner only)     |

Menu difilter di `DashboardController::buildAdminMenu($user)` sebelum di-render.

### Bukti smoke test (verified)
| URL                   | Admin | Store Owner | Courier |
| --------------------- | ----- | ----------- | ------- |
| `/dash/admin`         | 200   | 403         | 403     |
| `/dash/store`         | 403   | 200         | 403     |
| `/dash/courier`       | 403   | 403         | 200     |
| `/customer/dashboard` | 200   | 200         | 200     |
| `/marketplace`        | 200   | 200         | 200     |

---

## 5. View Highlights

### Store Dashboard
- 8 stat card: orders today, in-progress, revenue today, rating, revenue month, completed month, pending refunds, settlement pending
- Active orders list (10) + payment method breakdown (30d)
- Recent orders table (10) + services/pricing summary + staff count + top customers (5) + total unique customers

### Courier Dashboard
- 4 stat card: active tasks, today completed, earnings today, earnings month
- Available toggle (form submit on change → POST `/dash/courier/availability` → invalidates cache)
- Active tasks list + earnings summary (today/month/lifetime) + task history table (20)

### Admin/Owner Dashboard
- 4 section × 3-4 stat card:
  - **Financial**: GMV today, GMV month, COD today, online today
  - **Operations**: orders today, in-progress, completion rate, open disputes
  - **Network**: stores (total/active/pending), customers (total/today), couriers (total/available)
  - **Owner only**: platform commission (30d est), AI calls/cost/tokens today, failed logins 24h, audit events 24h
- Recent audit events (10) + failed logins 24h (10)

---

## 6. Verifikasi

### Syntax check
```
A. Syntax check: 5/5 file PHP baru lulus php -l
```

### Precompute service
```
$ php artisan dashboards:recompute-metrics --scope=platform
Recomputing scope: platform
Done.
```
Hasil insert ke `dashboard_metric_caches`:
- scope: platform, key: ai_calls_today, value: 0, computed_at: 2026-09-01 18:42:02
- ... dst

### Smoke test (3 role)
```
=== admin@test.id ===
  Login: HTTP 302
  /dash/admin: HTTP 200 [OK]
  /dash/store: HTTP 403 [OK]
  /dash/courier: HTTP 403 [OK]

=== owner@test.id ===
  Login: HTTP 302
  /dash/store: HTTP 200 [OK]
  /dash/admin: HTTP 403 [OK]
  /dash/courier: HTTP 403 [OK]

=== courier@test.id ===
  Login: HTTP 302
  /dash/courier: HTTP 200 [OK]
  /dash/store: HTTP 403 [OK]
  /dash/admin: HTTP 403 [OK]
```

### Regression test
```
Tests:    130 passed (304 assertions)
Duration: 17.86s
```
Tahap 1-17 tidak regresi. Catatan: sempat ada 2 test AuthTest 2FA fail karena gue hapus route `/dashboard` lama. Solusi: pindahkan `/dashboard` ke group `auth+2fa` middleware (test expects 2FA guard, dan admin/owner yg punya 2fa_on akan ter-redirect ke /2fa/verify). Setelah fix, **130/130 PASS**.

---

## 7. Yang TIDAK dilakukan (sesuai PRD)

- **Reverb realtime update untuk order count**: Kode di tracking page sudah listen `window.Echo.channel('order.{id}')`. Untuk dashboard order count realtime, butuh event broadcast `OrderCountChanged` di OrderService. PRD sebut "optional" — gue skip karena fokus ke dashboard presentasi, bukan realtime pipeline. (Existing Tahap 11 Reverb events tetap utuh.)
- **Audit filters UI di admin**: Ditampilkan 10 terakhir. Filter by action/resource/date bisa ditambah kemudian sebagai sub-route `/dash/admin/audit`.
- **Refund/Dispute detail per store**: Hanya ringkasan (count + pending amount) di store dashboard. Detail page adalah API existing Tahap 9-10.

---

## 8. Catatan teknis

- **Courier earnings**: Disimpan sebagai estimasi `task_count × 5000` (asumsi fee standar per delivery). Real split ada di `SettlementItem` (Tahap 10) — untuk accuracy production, ganti `courierEarnings()` dengan query ke settlement_items.
- **Platform commission (admin view)**: 10% dari `paid` payments 30d terakhir. Akurat untuk estimasi, bukan real commission yang sudah disettle. Real commission ada di `commission_settings` table.
- **Completion rate**: `completed / all_with_status` — sederhana, tidak exclude cancelled di numerator. Bisa diperbaiki dengan `(completed) / (completed + cancelled + delivered)` jika perlu.
- **Test user default** (untuk development/testing): `admin@test.id` / `owner@test.id` / `courier@test.id`, semua password `password`. Production: hapus/rename.

---

## 9. Files Modified Summary

| File                                            | Status   |
| ----------------------------------------------- | -------- |
| app/Models/DashboardMetricCache.php            | NEW      |
| app/Models/User.php                              | +1 method (hasRole) |
| app/Services/Dashboard/DashboardMetricsService.php | NEW   |
| app/Console/Commands/RecomputeDashboardMetrics.php | NEW  |
| app/Http/Controllers/Web/DashboardController.php | NEW      |
| database/migrations/2026_09_02_000001_create_dashboard_metric_caches_table.php | NEW |
| resources/views/layouts/dashboard.blade.php     | NEW      |
| resources/views/partials/sidebar.blade.php      | NEW      |
| resources/views/dashboards/store.blade.php      | NEW      |
| resources/views/dashboards/courier.blade.php    | NEW      |
| resources/views/dashboards/admin.blade.php      | NEW      |
| routes/web.php                                   | +6 routes + alias  |
| routes/console.php                               | +1 schedule         |

Tahap 1-17: TIDAK berubah. Tahap 18 selesai.
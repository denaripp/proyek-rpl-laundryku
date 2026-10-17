# Tahap 21 — Go-Live Checklist

**Status saat ditulis: 2026-09-02 (pre-production)**

## 1. Database migration production

- [ ] Backup database existing (kalau migrasi) sebelum jalankan migration.
- [ ] Run `php artisan migrate --force` di production.
- [ ] Run `php artisan db:seed --class=RolesAndPermissionsSeeder` di production.
- [ ] Verify zero pending migrations: `php artisan migrate:status`.
- [ ] Smoke test: `php artisan tinker --execute="echo \App\Models\User::count();"`.

## 2. Platform Owner pertama

- [ ] **PERINGATAN KEAMANAN**: Akun testing lokal `admin@laundryku.test` (password: `admin12345`) HANYA untuk development. Pastikan akun ini **dihapus** atau **password diubah** total sebelum production!
- [ ] Buat akun Platform Owner production resmi via tinker (atau `php artisan platform:owner <email-asli> <nama> --password=<password-kuat>`):

```php
$user = \App\Models\User::create([
    'name' => 'Nama Owner',
    'email' => 'owner@laundryku.id',
    'password' => \Illuminate\Support\Facades\Hash::make(str()->random(32)),
    'is_active' => true,
]);
$user->roles()->attach(\App\Models\Role::where('name', 'platform_owner')->first());
// Simpan password ke 1Password / Bitwarden. Setel 2FA wajib di user settings.
```

- [ ] Aktifkan 2FA wajib via Google Authenticator (`/2fa/enable`).
- [ ] Test login + verifikasi akses dashboard admin di `/dash/admin`.
- [ ] Verifikasi tidak ada akun dengan password default di DB (`SELECT email FROM users WHERE email LIKE '%@laundryku.test%';`).

## 3. End-to-end test dengan Xendit SANDBOX

- [ ] Set `XENDIT_API_KEY` ke **sandbox** key di `.env` production (NOT live key).
- [ ] Set `XENDIT_WEBHOOK_SECRET` ke sandbox webhook secret.
- [ ] Verify callback URL di Xendit dashboard = `https://<production-domain>/api/webhooks/xendit`.
- [ ] Jalankan smoke test penuh via UI / Postman:
    - [ ] Customer register → login → 2FA setup
    - [ ] Customer create order (online payment)
    - [ ] Xendit sandbox invoice created → customer pay dengan kartu tes
    - [ ] Webhook tiba → status payment = paid
    - [ ] Order transition ke PAID → PROCESSING → READY → DELIVERED → COMPLETED
    - [ ] Courier assignment → pickup → delivered → earnings credited
    - [ ] Refund flow (create → process)
- [ ] Cek audit_logs untuk semua aksi: `SELECT count(*) FROM audit_logs WHERE created_at > NOW() - INTERVAL 1 HOUR;`.

## 4. Security pre-flight

- [ ] `.env` ada di `.gitignore` (Tahap 21). Cek: `git check-ignore .env` → printed.
- [ ] `APP_DEBUG=false` di production.
- [ ] `APP_ENV=production` — **KRUSIAL**: Memastikan mode simulasi AI lokal (`mock_in_local`) nonaktif otomatis secara total.
- [ ] `OPENAI_API_KEY` terisi dengan API key resmi OpenAI yang valid dan memiliki kuota aktif sebelum go-live.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] HTTPS aktif + HSTS header (configure di web server / load balancer).
- [ ] Database user khusus app: privilege minimal (no DROP, no GRANT).
- [ ] File `storage/app/private/` permission 0750.
- [ ] Audit log table retention policy (jangan truncate).
- [ ] Queue connection pre-flight: Jika menggunakan `QUEUE_CONNECTION=redis`, pastikan PHP Redis extension (`ext-redis`) sudah terpasang (`php -m | grep redis`) dan daemon Redis aktif. Jika belum/tidak ada Redis, gunakan `QUEUE_CONNECTION=database` (worker: `php artisan queue:work --queue=default,notifications`).

## 5. Backup & restore

- [ ] Cron `db:backup` terpasang dan jalan tiap hari (lihat backup-restore.md).
- [ ] Test restore ke staging dari backup terbaru.
- [ ] Backup off-site (S3 / B2) scheduled daily.

## 6. Monitoring

- [ ] `/api/admin/monitoring` (Tahap 21) reachable untuk platform_owner.
- [ ] Alert setup (external — Slack/email) untuk:
    - Queue failed > 0 dalam 1 jam
    - Xendit webhook failure rate > 5%
    - AI cost > $X/day (threshold per use case)
- [ ] Database slow query log aktif.

## 7. Performance smoke test

- [ ] Homepage TTFB < 500 ms
- [ ] Marketplace search p95 < 1 s
- [ ] Order creation p95 < 800 ms
- [ ] Dashboard metrics endpoint p95 < 500 ms (cached)

## 8. Final sign-off

- [ ] Semua test pass: `php artisan test` → 100% green.
- [ ] Zero known critical bugs (lihat issues board).
- [ ] Staging sudah live 1 minggu tanpa insiden.
- [ ] On-call rotation aktif (platform_admin kontak).
- [ ] DNS pointing to production.
- [ ] SSL cert valid > 30 hari.

## Cutover steps

1. Maintenance mode on: `php artisan down --secret=ops-token`.
2. Final db backup: `php artisan db:backup`.
3. Final migration: `php artisan migrate --force`.
4. Seed platform_owner jika belum ada.
5. `php artisan up`.
6. Verify `/health` (Laravel default at `/up`) returns 200.
7. Verify `/dash/admin` accessible for platform_owner.
8. Smoke 1 order end-to-end via UI.
9. Maintenance mode off.

## Rollback

If something goes wrong post-cutover:

```bash
php artisan down
php artisan db:rollback --step=1  # rollback 1 batch
# OR
mysqldump_backup_pre_cutover.sql | mysql ...  # restore backup
php artisan up
```

For SEV-1 (data loss, security breach): preserve forensic logs,
rotate ALL secrets (Xendit, OpenAI, Google OAuth), contact legal.
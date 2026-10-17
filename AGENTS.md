# AGENTS.md — Laundryku (LANDURYKU)

File ini adalah briefing permanen untuk SEMUA coding agent (Antigravity,
Claude Code, Codex, dll). Baca dulu sebelum menyentuh apa pun.

## Apa ini
Marketplace laundry multi-tenant + AI Chat Assistant. Laravel 12, MVC,
Blade + Tailwind CSS v4 (via @tailwindcss/vite), MySQL 8.
Aktor: Customer, Store Owner, Store Admin, Courier, Platform Admin,
Platform Owner.

## Sumber kebenaran (urutan prioritas)
1. prd.md — kebutuhan produk
2. database/schema.sql — struktur database
3. docs/technical-notes.md — technical debt + decision log
4. DESIGN.md — design tokens & aturan visual UI (hasil export Google Stitch)
Jangan menebak di luar dokumen ini; kalau tidak ada, tanya.

## Environment (PENTING — tool TIDAK ada di PATH)
- PHP:    C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe
- MySQL:  C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe (root, tanpa password)
- Composer: pakai yang dari Laragon, atau absolute path bila perlu
- Contoh: C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan test
- Kalau test gagal dengan SQLSTATE[2002], MySQL belum jalan — start mysqld dulu:
  C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqld.exe --defaults-file=C:/laragon/bin/mysql/mysql-8.0.30-winx64/my.ini --console
- Server dev: http://laundryku.test (Laragon) atau php artisan serve

## Aturan keras (JANGAN dilanggar)
- JANGAN upgrade PHP ke 8.4 (keputusan sadar, lihat technical-notes T005/T006)
- JANGAN install Laravel Sanctum untuk saat ini (butuh PHP >= 8.4)
- JANGAN ubah QUEUE_CONNECTION menjadi redis; pakai database
- JANGAN mengubah migration yang sudah ada — buat migration baru
- Semua akses resource lewat Policy (Least Privilege + Zero Trust)
- Uang (payment/refund/commission) tidak boleh diubah tanpa instruksi eksplisit

## Konvensi kode
- Service layer untuk logika bisnis (controller tetap tipis)
- RBAC via role + permission + policy, bukan hardcode role di controller
- Frontend: Blade components + Tailwind v4; ikuti tokens di DESIGN.md,
  jangan mengarang warna/font sendiri
- Setiap fitur WAJIB punya feature test; jalankan `artisan test` sampai hijau
  sebelum klaim selesai

## Definition of done per task
1. Kode + migration (bila perlu) + seed bila relevan
2. Test hijau (bukti output, bukan klaim)
3. Update docs/technical-notes.md bila ada keputusan/debt baru
4. Jangan commit file .env, cookies.txt, atau kredensial
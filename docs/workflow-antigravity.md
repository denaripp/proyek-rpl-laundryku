# Workflow Vibe Coding — Laundry Marketplace dengan Google Antigravity

Dokumen ini adalah SOP langkah-demi-langkah untuk membangun/melanjutkan project
laundryku (marketplace laundry + AI chat) memakai Google Antigravity IDE.
Konsep kunci Antigravity yang dipakai di sini:

- Agent Manager: "mission control" untuk spawn & pantau agent otonom. Lu jadi
  manajer, agent yang ngetik kode.
- Artifacts: bukti kerja agent — Task Plan, Implementation Plan, Walkthrough +
  Verification (screenshot / browser recording). Ini cara lu verifikasi tanpa
  baca semua kode.
- Knowledge Base: tempat nyimpen konteks permanen (path tool, konvensi project)
  supaya agent nggak perlu di-brief ulang tiap sesi.
- Plan Mode vs Fast Mode: Plan Mode untuk fitur besar/beresiko, Fast Mode untuk
  perbaikan kecil.

---

## TAHAP 0 — Persiapan (sekali saja)

1. Install Antigravity IDE dari https://antigravity.google (Windows). Login
   pakai akun Google.
2. Buka folder project: File > Open Folder > C:\laragon\www\laundryku
3. Pastikan lingkungan lokal hidup SEBELUM nyuruh agent apa pun:
   - Start Laragon (atau minimal MySQL-nya). Cek port 3306 listening.
   - Kalau MySQL belum jalan dari terminal:
     C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe \
       --defaults-file=C:\laragon\bin\mysql\mysql-8.0.30-winx64\my.ini --console
4. Baseline git — wajib biar tiap tahap bisa di-rollback:
   git add -A && git commit -m "baseline sebelum tahap X"
   (kalau belum ada repo: git init dulu)

## TAHAP 1 — Isi Knowledge Base agent (sekali saja, krusial)

Di Antigravity, simpan fakta-fakta ini ke knowledge base / context project.
Ini mencegah agent nebak-nebak dan merusak setup:

- Project: Laravel 12, PHP 8.3.30, MySQL 8.0.30, arsitektur MVC, multi-tenant
  marketplace (Customer, Store Owner/Admin, Courier, Platform Admin/Owner).
- Sumber kebenaran: prd.md (PRD lengkap), database/schema.sql (skema),
  docs/technical-notes.md (tech debt + decision log). Baca dulu sebelum coding.
- Tool TIDAK ada di PATH. Selalu pakai path absolut:
  - PHP:      C:\laragon\bin\php\php-8.3.30-Win32-x64-vs16\php.exe
    (sesuaikan dengan folder php yang ada di C:\laragon\bin\php\)
  - Composer: C:\laragon\bin\composer\composer.phar (jalankan via php.exe)
  - MySQL:    C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe (root, tanpa password)
- Jalankan artisan/test dari folder C:\laragon\www\laundryku.
- Queue: QUEUE_CONNECTION=database (jangan redis — ext-redis belum ada).
- Batasan: JANGAN upgrade PHP ke 8.4, JANGAN install Sanctum dulu
  (keputusan sadar, lihat technical-notes T005/T006).
- Keamanan: prinsip Defense in Depth + Least Privilege + Zero Trust resource
  authorization. Semua akses resource harus lewat policy/authorization, bukan
  cuma cek login.

## TAHAP 2 — Loop kerja per fitur (ini inti vibe coding-nya)

Kerjakan SATU tahap/fitur per agent. Jangan gabung beberapa fitur dalam satu
prompt.

1. Buka Agent Manager (bukan editor chat biasa) → New Task.
2. Pakai Plan Mode untuk fitur besar. Kasih prompt dengan format ini:

   --- TEMPLATE PROMPT ---
   Konteks: Baca prd.md bagian <nomor fitur>, database/schema.sql, dan
   docs/technical-notes.md dulu.

   Tugas: Implementasikan <nama tahap/fitur>, misalnya
   "Tahap 19: Security Hardening — upload dokumen store + validasi".

   Batasan:
   - Ikuti pola service layer + policy yang sudah ada di app/.
   - Jangan ubah migrasi yang sudah ada; buat migrasi baru kalau perlu.
   - Jangan sentuh .env atau kredensial.
   - Queue driver tetap database.

   Definition of Done:
   - Fitur jalan end-to-end.
   - Test feature + unit ditulis dan PASS via:
     <php path absolut> artisan test
   - Buat artifact: Task Plan, Implementation Plan, dan Walkthrough dengan
     verifikasi (screenshot/browser recording halaman yang berubah).
   --- AKHIR TEMPLATE ---

3. Review Task Plan artifact dulu. Ini checkpoint termurah: kalau rencananya
   ngawur, kasih feedback langsung di artifact (kayak komentar di doc)
   SEBELUM agent nulis kode. Jangan approve kalau ragu.
4. Approve → agent implementasi. Pantau progress di Agent Manager; agent akan
   buka browser sendiri untuk verifikasi — biarkan.
5. Pas selesai, review:
   - Implementation Plan + code diff: cek apakah scope-nya sesuai, nggak
     nyentuh file yang nggak diminta.
   - Walkthrough/browser recording: lihat bukti visual fiturnya jalan.
   - Test artifact: pastikan `artisan test` hijau, bukan cuma "saya sudah test".
6. Ada yang salah → kasih feedback di artifact terkait, agent revisi tanpa
   kehilangan konteks. Ulangi sampai oke.
7. Accept changes → commit git:
   git add -A && git commit -m "Tahap X: <nama fitur>"

## TAHAP 3 — Urutan pengerjaan (roadmap)

Project sudah sampai sekitar Tahap 16–18 (API controllers, frontend, internal
dashboards — lihat docs/). Lanjutkan urutan dari prd.md; yang tercatat di
technical-notes masih ahead:

- Tahap 19 — Security Hardening (termasuk upload dokumen store, T003)
- Tahap 20 — (sesuai prd.md)
- Tahap 21 — Business Logic final: COD_MAX_AMOUNT (T001), MARKETPLACE_COMMISSION
  (T002), logika sponsored store (T004)
- Go-live: ikuti docs/go-live-checklist.md; hapus/ganti kredensial testing
  admin@laundryku.test sebelum production (T008).

Aturan urutan: tahap N+1 baru mulai kalau tahap N sudah commit + test hijau.

## TAHAP 4 — Parallel agents (opsional, hati-hati)

Agent Manager bisa jalanin beberapa agent sekaligus. Boleh paralel HANYA kalau
file-nya nggak bersinggungan, contoh aman:
- Agent A: frontend halaman customer   |  Agent B: laporan/dashboard store
Jangan paralel: dua agent yang sama-sama ubah routes, migrasi database, atau
model yang sama — pasti konflik. Tiap agent tetap wajib commit terpisah.

## TAHAP 5 — Kalau mentok

- Agent loop/nyasar → stop task, jangan lanjut. `git checkout .` atau revert ke
  commit baseline, lalu mulai ulang task dengan prompt yang lebih sempit.
- Error "SQLSTATE[2002] / connection refused" → MySQL belum jalan (lihat Tahap 0).
- Error "Class Redis not found" → ada kode yang maksa queue redis; suruh agent
  balikin QUEUE_CONNECTION=database.
- Fast Mode cukup untuk: fix typo, ganti copy, styling kecil.
- Plan Mode wajib untuk: apa pun yang nyentuh uang (payment/refund/commission),
  authorization, atau migrasi database.

---

Ringkasan mental model:
  Lu tulis INTENSI (prompt template) → agent bikin PLAN (artifact) → lu REVIEW
  plan → agent EKSEKUSI → agent kasih BUKTI (test + walkthrough) → lu ACCEPT
  → COMMIT. Ulangi per tahap. Jangan pernah skip review artifact — itu satu-
  satunya rem lu di vibe coding.
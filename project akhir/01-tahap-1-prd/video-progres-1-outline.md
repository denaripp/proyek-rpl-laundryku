# Panduan Presentasi Video Progres 1 — Proyek Akhir RPL (`TIKB2503021`)

- **Kelompok:** Laundry AI
- **Tema Kelompok:** Laundryku
- **Produk:** LaundryKu — Marketplace Layanan Laundry Multi-Tenant & Asisten AI Hibrida (`LANDY`)
- **Durasi Wajib:** **3–5 menit** (target ideal **4 menit 15 detik**)
- **Ketentuan Rekaman:** Wajah presenter tampil di **webcam aktif minimal resolusi 720p**, suara jernih, memperlihatkan dokumen PRD/SRS PDF + demo singkat arsitektur/aplikasi nyata yang sudah berjalan.

---

## Pembagian Segmen & Naskah Poin Utama (Target Rubrik Skor 4)

| Waktu | Segmen | Apa yang Ditampilkan di Layar | Poin Penekanan Agar Sesuai Rubrik Skor 4 |
| :--- | :--- | :--- | :--- |
| **0:00 – 0:40** | **1. Pembukaan, Identitas, & Latar Belakang** | Sampul PDF PRD (`Kelompok Laundry AI`, `Tema: Laundryku`) + Bab 1.2 & 1.3 | Perkenalkan nama/NIM, kelompok **Laundry AI**, dan tema **Laundryku**. Jelaskan singkat masalah UMKM laundry: selisih estimasi vs berat aktual, kurangnya pelacakan tahap cuci, dan menumpuknya chat pertanyaan rutin ke admin toko. |
| **0:40 – 1:35** | **2. Arsitektur Nyata & Sistem Keamanan (`Bab 2` & `Bab 5`)** | Diagram Arsitektur 2.1 + Tabel Aktor 2.2 (`Strict Role Isolation`) | Tegaskan arsitektur produksi nyata: **Next.js 16.3.5 + BFF Cookie Proxy** berkomunikasi dengan **Laravel 12 REST API + Sanctum + MySQL 8 (58 tabel)**. Soroti fitur keamanan nyata: registrasi & verifikasi **OTP WhatsApp (Fonnte)/SMTP Email** (tanpa ketergantungan login eksternal lain), **4 guard terisolasi** (`web`, `mitra`, `kurir`, `admin`), larangan switch-role, serta **wajib 2FA TOTP Super Admin**. |
| **1:35 – 2:30** | **3. Kebutuhan Fungsional, User Stories & NFR Terukur (`Bab 3` & `Bab 5`)** | Tabel `FR-01` s.d. `FR-10` + contoh *Acceptance Criteria* & Tabel NFR Bab 5 | Tunjukkan bahwa kebutuhan ditulis mengikuti format **IEEE 830 / ISO 29148** dengan *User Story* + *Given–When–Then*: 19 status order, pemisahan berat estimasi vs berat riil, peta **Leaflet + Nominatim OSM**, pembayaran **Xendit (60m/2m QRIS)** & **COD maks Rp500.000**, serta NFR terukur (TTFB OPcache `37,3 ms`, OTP `5 menit` / cooldown `60s`, polling `15s`, rate limit jelas). |
| **2:30 – 3:55** | **4. Deep-Dive Fitur AI Utama: LANDY 3-Layer + Live Demo (`/admin/settings/ai` atau Chatbot)** | Bab 4 PDF (Diagram 3-Layer, Tabel 33 Tools, Tabel Evaluasi `165/165`) + UI `/admin/settings/ai` atau widget LANDY | **Bagian penentu nilai:** Jelaskan bahwa LANDY memakai **Arsitektur Hybrid 3-Layer Intent Routing**: Layer 1 menyelesaikan panduan (21 topik) & kueri data sederhana pada **0 token LLM**; Layer 2 memanggil model OpenRouter dengan **33 read-only tools** lintas 4 peran; Layer 3 eskalasi aman ke `cs@laundryku.test`. Tunjukkan keamanan AI (**anti-mutasi & PII masking `maskEmail`/`maskPhone`**), demo menu **Dynamic Model Switcher** Super Admin, dan bukti uji objektif **165/165 (100%)** pada dataset *hold-out* (naik dari 48,48%). |
| **3:55 – 4:25** | **5. Matriks Keterlacakan & Penutup** | Bab 7 Traceability Matrix + daftar test `composer test` (76 file) | Simpulkan kesiapan keterlacakan PRD ke codebase dan test suite yang sudah berjalan, lalu tutup presentasi dengan salam profesional. |

---

## Checklist Sebelum Merekam

1. Pastikan identitas kelompok **Laundry AI** dan tema **Laundryku** tampil jelas pada sampul `project akhir/01-tahap-1-prd/prd-laundryku-tahap1.pdf`.
2. Buka `project akhir/01-tahap-1-prd/prd-laundryku-tahap1.pdf` di PDF viewer dengan zoom nyaman terbaca (mis. `100%–125%`) sebelum menekan rekam.
3. Jika ingin memperlihatkan demo UI singkat (misal `/auth`, `/stores`, atau `/admin/settings/ai`), jalankan Laragon + MySQL + `npm run dev` lebih dulu agar transisi antar-tab mulus dan durasi tetap di rentang **3–5 menit**.

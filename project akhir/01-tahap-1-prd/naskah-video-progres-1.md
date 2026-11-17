# Naskah Teleprompter Video Progres 1 (Tahap 1: PRD)
**Mata Kuliah:** Rekayasa Perangkat Lunak (`TIKB2503021`) — STMIK IKMI Cirebon  
**Mahasiswa:** Muhammad Najwa Syarif (NIM: 41257212)  
**Kelompok:** Laundry AI | **Tema:** Laundryku  
**Target Durasi:** 3 – 5 Menit (Ideal: 4 Menit 15 Detik)  
**Tampilan Layar:** Dokumen PDF `prd-laundryku-tahap1.pdf`  

---

## [00:00 – 00:40] Segmen 1: Pembukaan & Latar Belakang Masalah
*(Layar: Tampilkan Sampul PDF PRD `prd-laundryku-tahap1.pdf`, lalu scroll ke Bab 1 Latar Belakang)*

> "Assalamu’alaikum Warahmatullahi Wabarakatuh,  
> Selamat pagi/siang kepada Bapak Yudhistira Arie Wijaya, M.Kom., CDS. dan rekan-rekan sekalian.  
> 
> Perkenalkan, saya **Muhammad Najwa Syarif**, NIM **41257212**, mahasiswa S1 Teknik Informatika STMIK IKMI Cirebon dari kelompok **Laundry AI** dengan tema **Laundryku**.  
> 
> Pada kesempatan Video Progres 1 ini, saya akan mempresentasikan dokumen *Product Requirement Document* atau PRD untuk sistem **LaundryKu**—sebuah platform marketplace laundry multi-tenant yang diintegrasikan dengan asisten AI hibrida bernama LANDY.  
> 
> Proyek ini dilatarbelakangi oleh permasalahan nyata UMKM laundry kiloan dan satuan: seringnya terjadi perselisihan selisih timbangan antara estimasi pelanggan dengan timbangan riil toko, tidak adanya transparansi status pengerjaan pakaian secara real-time, serta tingginya beban admin toko dalam menjawab pertanyaan rutin pelanggan secara manual."

---

## [00:40 – 01:35] Segmen 2: Arsitektur Sistem & Standar Keamanan
*(Layar: Scroll ke Bab 2 — Diagram Arsitektur 2.1 & Tabel Aktor 2.2)*

> "Memasuki Bab 2 mengenai arsitektur sistem. LaundryKu dirancang dengan arsitektur modern berstandar enterprise:  
> - Frontend menggunakan **Next.js 16** dengan pola *Backend-for-Frontend* (BFF) cookie proxy.  
> - Backend menggunakan **Laravel 12 REST API** yang terhubung ke database **MySQL 8** dengan 58 tabel terelasi penuh.  
> 
> Dari aspek otentikasi dan keamanan, kami menerapkan prinsip *Zero Trust* dan *Strict Role Isolation* lintas 4 guard mandiri: `web` untuk Customer, `mitra` untuk Pemilik & Staf Laundry, `kurir` untuk Driver Antar-Jemput, dan `admin` untuk Pengelola Platform.  
> 
> Verifikasi akun menggunakan **OTP WhatsApp via Fonnte** serta SMTP Email. Khusus untuk Super Admin, kami mewajibkan autentikasi ganda berbasis waktu atau **2FA TOTP**, guna menjamin data transaksi dan komisi UMKM terlindungi secara maksimal."

---

## [01:35 – 02:30] Segmen 3: Kebutuhan Fungsional & NFR Terukur
*(Layar: Scroll ke Bab 3 — Kebutuhan Fungsional FR-01 s.d. FR-10 & Bab 5 NFR)*

> "Pada Bab 3, kebutuhan fungsional disusun ketat mengacu standar IEEE 830 dan ISO 29148 menggunakan format *User Story* dan kriteria penerimaan *Given–When–Then*.  
> 
> Ruang lingkup mencakup 10 modul utama, di antaranya:  
> 1. Manajemen siklus pesanan dengan **19 status order presisi**, membedakan berat estimasi di awal dan berat riil setelah penimbangan toko.  
> 2. Penentuan zonasi dan ongkir dinamis menggunakan **Leaflet dan OpenStreetMap**.  
> 3. Integrasi gerbang pembayaran otomatis via **Xendit (QRIS & Virtual Account)** serta opsi **COD** dengan batasan risiko maksimal Rp500.000.  
> 
> Kebutuhan non-fungsional di Bab 5 juga ditetapkan terukur: waktu respons server (TTFB) rata-rata di bawah **40 milidetik**, masa berlaku OTP tepat 5 menit dengan *cooldown rate-limit* 60 detik, serta pencadangan database harian otomatis."

---

## [02:30 – 03:55] Segmen 4: Fitur AI Hibrida LANDY (Inovasi Utama)
*(Layar: Scroll ke Bab 4 — Diagram 3-Layer Intent Routing & Tabel Evaluasi AI)*

> "Bagian paling krusial dari proyek ini adalah inovasi kecerdasan buatan pada Bab 4: asisten virtual **LANDY**.  
> 
> LANDY kami bangun menggunakan **Arsitektur Hybrid 3-Layer Intent Routing**:  
> - **Layer 1 (Heuristic Rule-Based)**: Menyelesaikan 21 kategori pertanyaan umum dan status pesanan dasar secara instan dengan **0 biaya token LLM**.  
> - **Layer 2 (LLM OpenRouter + Function Calling)**: Menangani konsultasi perawatan bahan pakaian dan rekomendasi paket menggunakan **33 tools *read-only*** terisolasi.  
> - **Layer 3 (Eskalasi Human Support)**: Jika ada keluhan berat atau anomali data, tiket otomatis dialihkan ke staf operasional.  
> 
> Untuk keamanan data pengguna, seluruh prompt dilindungi guardrail anti-mutasi database dan penyensoran data sensitif (*PII Masking*) pada nomor HP dan email.  
> 
> Hasil pengujian objektif membuktikan, pipeline evaluasi kami berhasil meraih skor akurasi **165 dari 165 skenario uji atau 100% lulus** pada dataset *hold-out*."

---

## [03:55 – 04:20] Segmen 5: Matriks Keterlacakan & Penutup
*(Layar: Scroll ke Bab 7 — Traceability Matrix & Daftar Pengujian)*

> "Sebagai penutup, pada Bab 7 seluruh kebutuhan dari FR-01 hingga FR-10 telah dipetakan secara terukur ke dalam matriks keterlacakan (*Traceability Matrix*), yang langsung terhubung ke 76 berkas pengujian otomatis pada repositori kode kami.  
> 
> Dokumen PRD ini menjadi fondasi yang kokoh sebelum melangkah ke perancangan teknis UML pada Tahap 2 dan implementasi sistem pada Tahap 3.  
> 
> Demikian presentasi Video Progres 1 dari saya. Terima kasih atas perhatian Bapak Yudhistira Arie Wijaya dan rekan-rekan semua.  
> Wassalamu’alaikum Warahmatullahi Wabarakatuh."

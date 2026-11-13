# Panduan Presentasi Video Progres 2 — Proyek Akhir RPL (`TIKB2503021`)

- **Kelompok:** Laundry AI
- **Tema Kelompok:** Laundryku
- **Produk:** LaundryKu — Marketplace Layanan Laundry Multi-Tenant & Asisten AI Hibrida (`LANDY`)
- **Durasi Wajib:** **5–7 menit** (target ideal **6 menit 15 detik**)
- **Ketentuan Rekaman:** Wajah presenter tampil di **webcam aktif minimal resolusi 720p**, suara jernih, membedah dokumen **SDD PDF (`project akhir/02-tahap-2-sdd-uml-erd-figma/sdd-laundryku-tahap2.pdf`)** beserta **4 diagram UML + ERD** secara runut, lalu melakukan *live click-through walkthrough* **prototipe interaktif** di Figma / FigCraft.

---

## Pembagian Segmen & Naskah Poin Utama (Target Rubrik Skor 4)

| Waktu | Segmen | Apa yang Ditampilkan di Layar | Poin Penekanan Agar Sesuai Rubrik Skor 4 |
| :--- | :--- | :--- | :--- |
| **0:00 – 0:40** | **1. Pembukaan, Identitas, & Pengantar SDD** | Sampul `sdd-laundryku-tahap2.pdf` (`Kelompok Laundry AI`, `Tema: Laundryku`) + Bab 1 Arsitektur 4-Layer | Perkenalkan nama/NIM, kelompok **Laundry AI**, dan tema **Laundryku**. Jelaskan transisi dari **Tahap 1 (PRD)** ke **Tahap 2 (SDD)**: menerjemahkan kebutuhan menjadi rancangan arsitektur 4-lapisan (**Next.js BFF → Laravel 12 API → MySQL 8 → OpenRouter/Xendit/Fonnte**). |
| **0:40 – 1:35** | **2. UML 1 — Use Case Diagram & Skenario (`Bab 2`)** | `uml-diagrams/01_use_case.svg` + Tabel Aktor & Skenario `UC-01` s.d. `UC-11` | Tunjukkan batas sistem yang menghubungkan **5 aktor manusia** (*Guest, Customer, Kurir, Mitra Toko, Super Admin*) dan **3 sistem eksternal** (*OpenRouter, Xendit, Fonnte/Nominatim*). Soroti hubungan `<<include>> SUB-01 (33 AI Tools)` & `SUB-02 (Webhook Idempoten Xendit)`, serta `<<extend>> SUB-03 (Eskalasi CS cs@laundryku.test)`. Bacakan ringkas 1 contoh skenario (`UC-03` selisih berat atau `UC-11` LANDY AI). |
| **1:35 – 2:25** | **3. UML 2 — Activity Diagram Bisnis & AI (`Bab 3`)** | `uml-diagrams/02a_activity_order.svg` & `02b_activity_ai.svg` | Pada **Activity A**, telusuri alur paralel lintas 5 *swimlane* dari checkout `OrderService::createOrder()` → kurir jemput → Mitra panggil `setActualWeight()` → keputusan ambang selisih harga → cabang bayar **Online Xendit vs COD (maks Rp500rb)** → `SettlementService`. Pada **Activity B**, tekankan *guardrail* anti-injeksi, jalur cepat **0-token Layer 1 (21 topik KB + 165/165 hold-out)**, lalu *tool-calling loop* terkontrol. |
| **2:25 – 3:20** | **4. UML 3 — Class Diagram Berprinsip SOLID (`Bab 4`)** | `uml-diagrams/03a_class_domain.svg`, `03b_class_solid_ai.svg`, & Matriks S-O-L-I-D §4.3 | Buktikan kepatuhan **5 prinsip SOLID** dengan kelas nyata kode: **[S]** `OrderController` tipis memanggil `OrderService`/`XenditService`; **[O]** `NotificationService::addChannel()` & `AiToolRegistry` tanpa ubah inti; **[L]** substitusi `OpenAiClient`, `NominatimGeocodingService`, `LogOtpSender`; **[I]** *interface* ramping (`AiClientInterface`, `OtpSender`, `NotificationChannel`, `GeocodingServiceInterface`); **[D]** *dependency injection* kontrak. |
| **3:20 – 4:10** | **5. UML 4 — Sequence Diagram (`Bab 5`)** | `uml-diagrams/04a_sequence_ai.svg` & `04b_sequence_payment.svg` | Bedah urutan pesan sinkron pada **Sequence A**: Browser → Next.js `/api/chat` (injeksi *HTTP-only cookie* ke *Bearer*) → `AiChatController` baca `settings(active_model)` MySQL → blok `alt` (Layer 1 0-token vs OpenRouter `/chat/completions` + `AiToolRegistry` *PII masking*) → simpan `ai_usage_logs`. Lalu pada **Sequence B**, tunjukkan transaksi atomik `DB::transaction` & idempotensi `xendit_event_id`. |
| **4:10 – 4:50** | **6. ERD Basis Data MySQL 8 — 58 Tabel (`Bab 6`)** | `erd/05a_erd_core.svg`, `erd/05b_erd_domain_sub.svg`, & Ringkasan 6 Domain §6.4 | Tunjukkan notasi *Crow's Foot* relasi PK/FK inti (`users` → `stores`/`orders` → `order_items`, `payments`/`payment_events`, `courier_tasks`/`courier_gps`, `settlement_periods`/`settlement_items`, `ai_conversations`/`ai_usage_logs`) dan tegaskan inventaris penuh **58 tabel aktual** dalam **6 domain**. |
| **4:50 – 6:15** | **7. Walkthrough Prototipe Interaktif Figma / FigCraft & Penutup** | Mode *Present (Play)* Figma (`ui-ux-prototipe/laundryku-uiux-prototype.fig`) atau FigCraft (`ui-ux-prototipe/laundryku_design_system.figcraft`) | Demonstrasikan **klik langsung prototipe interaktif** lintas peran (**4 Flow, 282 interaksi klik, 48 frame terhubung**): **(1) Flow Customer** (Beranda → Cari Toko Terdekat → Detail/Order → Pesanan Saya + AI Widget); **(2) Flow Customer/Kurir Mobile 390px**; **(3) Flow Mitra Toko** (Sidebar Dashboard → Pesanan → Timbang Berat → Settlement); **(4) Flow Super Admin** (Sidebar → Verifikasi Mitra → **Konfigurasi Model AI OpenRouter** → Audit Log). Tutup dengan rangkuman kesiapan menuju Tahap 3. |

---

## Checklist Sebelum Merekam

1. Pastikan identitas kelompok **Laundry AI** dan tema **Laundryku** tampil jelas pada sampul `project akhir/02-tahap-2-sdd-uml-erd-figma/sdd-laundryku-tahap2.pdf`.
2. Buka `project akhir/02-tahap-2-sdd-uml-erd-figma/sdd-laundryku-tahap2.pdf` pada tab/jendela pertama dengan zoom pas lebar halaman (`Fit Width`).
3. Siapkan tab/jendela kedua pada mode **Play / Prototype**:
   - **Jika memakai Figma:** Impor terlebih dahulu `project akhir/02-tahap-2-sdd-uml-erd-figma/ui-ux-prototipe/laundryku-uiux-prototype.fig` ke akun Figma Web/Desktop, periksa keterbacaan font *Plus Jakarta Sans / Inter* serta fungsi klik hotspot pada **4 Flow** (`Customer Desktop`, `Customer & Kurir Mobile`, `Mitra Toko`, `Super Admin & AI Config`).
   - **Jika memakai FigCraft Desktop:** Buka `project akhir/02-tahap-2-sdd-uml-erd-figma/ui-ux-prototipe/laundryku_design_system.figcraft` dan uji masing-masing Flow pada jendela Prototype Play sebelum menekan tombol rekam.
4. Uji coba simulasi dengan stopwatch agar seluruh penjelasan 4 diagram UML, ERD, dan walkthrough klik prototipe tuntas di rentang **5–7 menit**.

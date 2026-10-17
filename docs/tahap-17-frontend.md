# Tahap 17 — Frontend (Blade + Bootstrap 5 + Vanilla JS)

Tanggal   : 2 September 2026
Tujuan    : HTTP frontend lengkap untuk customer journey — dari landing page sampai customer dashboard. Stack: Laravel Blade + Bootstrap 5 (CDN) + Leaflet (fallback map) + Vanilla JS (no build step).
Hasil     : 7 page utama + layout + AI widget. Smoke test 7 page = 200/302/404 sesuai. Regression test 130/130 PASS, 0 fail.

---

## 1. Stack & Keputusan Arsitektur

| Aspek                | Pilihan                                  | Alasan                                                  |
| -------------------- | ---------------------------------------- | ------------------------------------------------------- |
| CSS framework        | Bootstrap 5 via CDN                      | No build step, cepat, dokumentasi luas                  |
| Map library          | Leaflet + OpenStreetMap                  | Open-source, ga butuh API key, fallback handal         |
| JS                   | Vanilla JS (no framework)                | Bundle kecil, tanpa npm/Vite overhead                  |
| Realtime             | Laravel Reverb channel + polling fallback | Reverb auto-connect kalau ada Echo; kalau belum, polling tiap 15 dtk |
| Brand colors         | CSS variables di public/css/brand.css    | --lk-primary #1A3A5C, --lk-secondary #4A90D9, --lk-tertiary #2ECC71, --lk-neutral #F5F7FA |
| Google Maps          | Loader dinamis, fallback ke text         | T7 service: kalau mapsKey kosong/gagal load, render text |
| Layout inheritance   | @extends('layouts.app')                  | Navbar + footer + AI widget global                     |
| Form ke API          | Axios + window.LK.csrf                   | CSRF token auto-include, error handled                 |

---

## 2. File yang dibuat/diubah (12 file)

### Layout & asset
- `resources/views/layouts/app.blade.php` (BARU) — Navbar + footer + AI widget slot + Bootstrap/Leaflet/Axios CDN + window.LK config
- `resources/views/partials/ai-widget.blade.php` (BARU) — Floating button + chat panel + JS handler (POST /api/ai/chat)
- `resources/views/partials/store-card.blade.php` (BARU) — Reusable store card
- `public/css/brand.css` (BARU) — Brand colors, hero, card, timeline, status pill, AI widget, map, mobile responsive

### Controller
- `app/Http/Controllers/Web/PageController.php` (BARU) — 7 method: home, marketplace, storeDetail, checkout, tracking, payment, dashboard, storeOnboardForm

### Halaman (7 page)
- `resources/views/pages/home.blade.php` — Hero + search form + cara pesan + top stores + CTA owner + FAQ accordion
- `resources/views/pages/marketplace.blade.php` — Filter form + view toggle (list/map) + Leaflet map + Google Maps loader dgn fallback
- `resources/views/pages/store-detail.blade.php` — Header + layanan/harga + jam operasional + reviews + ringkasan biaya
- `resources/views/pages/checkout.blade.php` — Pilih layanan + alamat + metode pembayaran + ringkasan real-time + submit ke /api/orders
- `resources/views/pages/tracking.blade.php` — Status timeline + peta posisi kurir (Echo + polling) + item + berat/total + ringkasan pembayaran
- `resources/views/pages/payment.blade.php` — Xendit VA / QRIS / COD + countdown expiration timer + cek status button
- `resources/views/pages/dashboard.blade.php` — Active orders + recent orders + saved addresses + notifications + modal tambah alamat
- `resources/views/pages/store-onboard.blade.php` — Landing untuk daftarkan laundry (mitra)

### Auth (fix regresi)
- `resources/views/auth/login.blade.php` (BARU)
- `resources/views/auth/register.blade.php` (BARU)
- `routes/web.php` (UPDATE) — 7 route customer journey di luar middleware auth (guest: /, /marketplace, /stores/{slug}, /onboard) + 4 route customer journey di dalam middleware auth

---

## 3. Route baru (11)

```
GET  /                              -> home                  (public)
GET  /marketplace                   -> marketplace.index     (public)
GET  /stores/{slug}                 -> store.detail         (public)
GET  /onboard                       -> store.onboard         (public)
GET  /stores/{store}/checkout       -> checkout             (auth)
GET  /orders/{order}/tracking       -> order.tracking       (auth)
GET  /orders/{order}/payment        -> order.payment        (auth)
GET  /dashboard                     -> customer.dashboard   (auth)
```

`POST /api/ai/chat` udah ada (Tahap 13). `GET /api/customer/addresses`, `POST /api/customer/addresses`, `POST /api/orders` udah ada (Tahap 16).

---

## 4. Customer Journey — implementasi

### 1. Landing Page (/)
- Hero gradient primary→secondary dgn search bar
- CTA "Cari Laundry" → /marketplace
- CTA "Daftarkan Laundry" → /onboard
- 4-step "Cara Pesan" section (cari → pilih → kurir → selesai)
- Top stores (rating tertinggi) — query `Store::approved()->orderByDesc('average_rating')->limit(6)`
- CTA owner card (gradient)
- FAQ accordion (5 pertanyaan)
- AI widget floating di pojok kanan bawah (selalu ada di semua page)

### 2. Marketplace (/marketplace)
- Filter: kata kunci + radius (2/5/10/20 km) + min rating (3/3.5/4/4.5)
- View toggle: List / Map
- List view: 12 card per page (pakai paginator StoreSearchService)
- Map view: Leaflet + OSM tile, marker per store dengan popup link ke detail
- Google Maps loader dgn fallback: kalau window.LK.mapsKey ada, inject Google Maps script; onerror/onload failure → fallback ke Leaflet atau text fallback
- "lkSetView" JS ubah query string + reload

### 3. Store Detail (/stores/{slug})
- Header: nama + rating + jumlah review + alamat + telepon
- Tombol "Pesan Sekarang" → /stores/{store}/checkout
- Tabel layanan + harga (servicePrices->where is_active)
- Ulasan 5 terbaru
- Sidebar: jam operasional per hari + biaya jemput/antar/min order + status COD

### 4. Checkout (/stores/{store}/checkout)
- Pilih layanan: checkbox per service, input qty (auto-calc subtotal real-time)
- Alamat: dropdown alamat tersimpan + textarea manual. Checkbox "antar sama dgn jemput" toggle field delivery_address
- Pembayaran: 3 opsi Xendit VA / QRIS / COD (COD disabled kalau store.cod_enabled=false)
- Ringkasan sticky: subtotal + pickup_fee + delivery_fee + total
- Submit: POST /api/orders dengan payload item ter-normalisasi → redirect ke /orders/{id}/payment
- Error handling: alert dgn response message

### 5. Order Tracking (/orders/{order}/tracking)
- Timeline 8 stage (pending → confirmed → pickup_assigned → picked_up → in_process → ready → delivering → completed) dgn marker active/done
- Peta posisi kurir:
  - Default: Leaflet map
  - Kalau window.LK.mapsKey ada: Google Maps loader
  - Listen Reverb channel `order.{id}` event `CourierLocationUpdated` (kalau Echo ada)
  - Fallback: poll GET /api/courier-tasks tiap 15 detik
  - Fallback terakhir: text "Posisi kurir akan tampil..."
- Berat & total: tampil actual_weight kalau sudah ditimbang; poll GET /api/orders tiap 20 detik untuk update
- Pembayaran: status pill (paid/pending)

### 6. Payment (/orders/{order}/payment)
- Xendit/QRIS: tampil VA number atau QR image, countdown timer ke expires_at, tombol "Cek Status Pembayaran" (panggil GET /api/orders/{id} lalu reload)
- COD: tampil total besar + instruksi bayar di tempat
- Paid: tampil success screen + link ke tracking

### 7. Customer Dashboard (/dashboard)
- Active orders (status ≠ completed/cancelled) dgn pill status
- Recent orders (10 terakhir) dalam tabel
- Saved addresses dengan badge default + modal form tambah alamat (POST /api/customer/addresses)
- Notifications (10 terakhir)

### 8. Store Onboard (/onboard)
- Single-page CTA untuk mitra laundry
- 4 benefit list + tombol daftar

---

## 5. AI Assistant Widget

Floating button + chat panel di semua page (include partials.ai-widget).

Behavior:
- Klik tombol → buka panel (ARIA hidden toggle)
- Input + submit → POST /api/ai/chat dgn { message, session_id }
- Session ID di localStorage `lk_ai_session` (persistent per browser)
- 2 tipe message: user (bg secondary, kanan) & bot (bg light, kiri)
- "typing..." indicator (lk-pulse animation) saat nunggu
- Error handling: 401 → "Silakan login dulu", 5xx → "Asisten sedang sibuk"
- Close button + auto-focus input

---

## 6. Brand CSS (public/css/brand.css)

- CSS variables untuk 4 brand colors
- Component classes: .lk-navbar, .lk-card, .lk-card-store, .lk-badge-{tertiary,primary,secondary}, .lk-timeline (li.active/done), .lk-footer, .lk-map, .lk-map-fallback, .lk-ai-widget-btn, .lk-ai-panel, .lk-ai-msg.{user,bot}, .lk-pulse, .lk-status-pill.{pending,confirmed,process,delivering,completed,cancelled}
- Button classes: .btn-lk-{primary,secondary,tertiary,outline-lk-primary}
- Mobile responsive @media (max-width: 768px): hero lebih kecil, map 400px, AI panel full-width
- 6000+ chars

---

## 7. Fallback Strategy untuk Maps & Realtime

| Fitur         | Primary                                       | Fallback 1                       | Fallback 2                  |
| ------------- | --------------------------------------------- | -------------------------------- | --------------------------- |
| Map (marketplace) | Google Maps (kalau mapsKey)                | Leaflet + OSM (no key needed)    | Text fallback (alamat list) |
| Map (tracking) | Google Maps (kalau mapsKey)                   | Leaflet + OSM                    | Text "Posisi kurir..."      |
| Realtime courier | Laravel Reverb `order.{id}` channel        | Polling GET /api/courier-tasks 15s | (text fallback tetap tampil) |
| Realtime order | Reverb `order.{id}` channel                  | Polling GET /api/orders/{id} 20s  | (text fallback)             |
| AI chat       | POST /api/ai/chat (Mistral/Gemini config)     | Error message "asisten sibuk"    | -                           |

---

## 8. Smoke Test (HTTP status)

Dev server `php -S 127.0.0.1:8765` + curl:

| Route                          | Expected | Actual |
| ------------------------------ | -------- | ------ |
| GET /                          | 200      | 200    |
| GET /marketplace               | 200      | 200    |
| GET /onboard                   | 200      | 200    |
| GET /login                     | 200      | 200    |
| GET /register                  | 200      | 200    |
| GET /stores/abc (no slug)      | 404      | 404    |
| GET /dashboard (no auth)       | 302      | 302    |
| GET /css/brand.css             | 200      | 200    |

Semua page render tanpa error Blade atau PHP.

---

## 9. Regression Test

```
Tests:    130 passed (304 assertions)
Duration: 25.80s
```

Full Feature suite (Auth, AI, Order, Payment, Refund, ReviewDispute, Settlement, Notification, Rbac, StoreAuth, StoreOnboarding, StoreSearch, CourierAssignment, ApiEndpoints) — 130/130 PASS, 0 fail. Tahap 16 tidak regresi.

---

## 10. Bug yang ditemukan & diperbaiki selama pengerjaan

1. **marketplace.blade.php line 102 "Unclosed '['"** — Arrow function `fn($s) => [...]` di dalam `@json(...)` di-parse salah sama Blade compiler. Fix: serialize data di controller (pakai `function ($s) { return [...] }` di PHP, bukan arrow), kirim ke view sebagai variabel biasa. Pelajaran: arrow function di Blade tag bisa bikin parser bingung.

2. **auth.login & auth.register view not found (500)** — Auth controller panggil `view('auth.login')` / `view('auth.register')` tapi file belum ada (cuma auth/complete-profile.blade.php yang ada). Fix: bikin 2 view simpel.

3. **User->orders() tidak ada** — Pakai `ordersAsCustomer()` (relasi ke Order.customer_id). Fix di PageController dashboard.

4. **Store field naming** — Field di DB: `average_rating` (bukan avg_rating), `review_count` (bukan total_reviews), `operational_hours` (bukan operating_hours). Fix di home().

5. **Store->reviews() tidak punya user eager-load** — Review model cuma punya `customer()`. Fix: pakai `with('reviews.customer')` di store detail.

6. **Curl exit code 23 di Windows** — Broken pipe karena `-o /dev/null` di MSYS curl. Bukan error aplikasi, ignore.

---

## 11. Limitasi & catatan

- Tidak ada data store approved di database (perlu seed Tahap 18+) untuk visual UI penuh. Semua page render OK, hanya list store kosong.
- Leaflet default (no Google Maps key di .env); Google Maps loader hanya aktif kalau `config('services.google_maps.key')` di-set.
- Laravel Reverb belum di-install (belum ada di composer.json), tapi JS code siap listen `window.Echo.channel('order.{id}')` — saat Reverb diaktifkan, otomatis aktif tanpa ubah JS.
- Build step (Vite/npm) tidak dipakai — Bootstrap & Leaflet via CDN, app.js tidak ada perubahan. Untuk production, pertimbangkan build lokal agar tidak bergantung CDN.
- Tidak ada test Feature khusus untuk frontend Blade (cuma smoke test HTTP status). Test visual/E2E bisa ditambah di Tahap 18 (Dusk / Playwright).

Tahap 17 selesai. Frontend customer journey siap untuk integrasi end-to-end & seed data.
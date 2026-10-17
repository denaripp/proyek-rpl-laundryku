# Problems Audit — Tahap 16-17 Diagnostic Cleanup

Tanggal   : 2 September 2026
Tujuan    : Audit dan perbaikan 7 Problems/diagnostics IDE di codebase LANDURYKU
Hasil     : 7/7 diperbaiki. 130/130 regression test PASS, 0 fail. Tahap 16 tidak berubah behavior.

---

## Problems Audit

| #  | Problem                                                              | Root Cause                                                                                  | Fix                                                                                          | Status      |
| -- | -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- | ----------- |
| 1  | Unused import `use Illuminate\Support\Facades\DB` di AdminController.php | Impor dari template boilerplate, tidak ada transaksi DB di controller                       | Hapus baris `use Illuminate\Support\Facades\DB;`                                              | FIXED       |
| 2  | Unused import `use Illuminate\Support\Facades\DB` di OrderController.php | Impor boilerplate, semua DB logic sudah delegasi ke OrderService                            | Hapus baris `use Illuminate\Support\Facades\DB;`                                              | FIXED       |
| 3  | Unused import `use Illuminate\Support\Facades\DB` di StoreOwnerController.php | Impor boilerplate, tidak ada DB::transaction langsung di controller                        | Hapus baris `use Illuminate\Support\Facades\DB;`                                              | FIXED       |
| 4  | Unused import `use App\Models\Order` di CourierController.php        | Impor dari draft awal, controller Courier tidak mereferensikan model Order                   | Hapus baris `use App\Models\Order;`                                                          | FIXED       |
| 5  | Unused import `use Illuminate\Support\Facades\Gate` di CourierController.php | Impor dari draft awal; otorisasi dilakukan manual via `hasPermissionTo()` (bukan Gate)     | Hapus baris `use Illuminate\Support\Facades\Gate;`                                           | FIXED       |
| 6  | Undefined relationship `storeStaff` di StoreOwnerController.php line 44 | Salah nama: Store model punya relasi `staff()` (bukan `storeStaff()`). `storeStaff()` ada di **User** model, bukan Store. | Ganti `$store->load(['servicePrices.service', 'storeStaff.user:id,name,email'])` → `$store->load(['servicePrices.service', 'staff.user:id,name,email'])` | FIXED (real bug) |
| 7  | Filter mismatch `lat`/`lng` di StoreController.php line 28           | Controller kirim `lat`/`lng` ke `StoreSearchService`, tapi service baca `latitude`/`longitude`. Akibatnya filter radius & sort by distance **diabaikan** di marketplace search API. | Ganti `$request->only(['q', 'service_id', 'min_rating', 'lat', 'lng', 'radius_km', 'sort'])` → `$request->only(['q', 'service_id', 'min_rating', 'latitude', 'longitude', 'radius_km', 'sort'])` + sinkronkan `SearchStoresRequest` rules dari `lat`/`lng` ke `latitude`/`longitude` | FIXED (real bug) |

---

## Ringkasan

| Metrik                                    | Nilai |
| ----------------------------------------- | ----- |
| Problems awal (IDE diagnostics)           | 7     |
| Problems diperbaiki                       | 7     |
| Problems yang merupakan false positive    | 0     |
| Problems berupa warning (unused import)   | 5     |
| Problems berupa real bug                  | 2     |
| File yang diubah                          | 6     |
| Test PASS (regression)                    | 130/130 (304 assertions) |
| Durasi test                               | 17.80s |
| Tahap 16 behavior berubah                 | TIDAK |

---

## File yang diubah

1. `app/Http/Controllers/Api/AdminController.php` — hapus unused import `DB`
2. `app/Http/Controllers/Api/OrderController.php` — hapus unused import `DB`
3. `app/Http\Controllers/Web/PageController.php` — **TIDAK diubah** (Tahap 17, di luar scope)
4. `app/Http/Controllers/Api/StoreOwnerController.php` — hapus unused import `DB` + fix undefined relasi `storeStaff` → `staff`
5. `app/Http/Controllers/Api/CourierController.php` — hapus unused import `Order` + hapus unused import `Gate`
6. `app/Http/Controllers/Api/StoreController.php` — fix filter mismatch `lat`/`lng` → `latitude`/`longitude`
7. `app/Http/Requests/Store/SearchStoresRequest.php` — sinkronkan validation rules `lat`/`lng` → `latitude`/`longitude`

---

## Dampak perbaikan

### Real bug yang diperbaiki (#6 dan #7)

- **#6 StoreOwnerController::show()**: Sebelumnya saat endpoint `GET /api/store-owner/stores/{store}` dipanggil (setelah policy `view` lolos), `$store->load(['storeStaff.user:...'])` akan melempar `BadMethodCallException: Call to undefined relationship [storeStaff] on model [App\Models\Store]`. Sekarang pakai `staff()` (relasi yang benar) dan endpoint jalan tanpa error.

- **#7 StoreController::index() (marketplace search)**: Sebelumnya request `GET /api/stores?lat=-6.2&lng=106.8&radius_km=5` di-ignore — `lat`/`lng` di-pass ke service yang baca `latitude`/`longitude`, sehingga:
  - Filter radius tidak diterapkan (semua store approved ditampilkan)
  - Distance tidak dihitung
  - Sort by distance tidak jalan
  Sekarang filter radius dan distance ranking berjalan sesuai design.

### Warning yang diperbaiki (#1-#5)

- Menghapus 5 unused import statements. Tidak ada perubahan behavior, hanya kebersihan kode.

---

## Test results

```
Tests:    130 passed (304 assertions)
Duration: 17.80s
```

Test suite Feature (semua Tahap 1-16) — 130/130 PASS, 0 fail, 0 error.

---

## Catatan

- Tahap 16 tidak berubah behavior. Test Tahap 16 (`ApiEndpointsTest` 34 test) tetap PASS, termasuk `test_customer_can_search_stores` yang tidak kirim `lat`/`lng` (path default, tidak lewat filter radius — tetap aman).
- Tahap 17 (frontend Blade) **TIDAK** disentuh sesuai aturan.
- Tidak ada `@SuppressWarnings`, ignore rule, atau workaround.
- Tidak ada test yang dihapus atau dinonaktifkan.
- Tidak ada refactor besar-besaran — hanya minimal fix per masalah.
- File `SearchStoresRequest.php` validation rules ikut disinkronkan agar FormRequest dan controller sekarang sepakat pada nama field `latitude`/`longitude`. Frontend (Tahap 17) tidak mengirim field ini, jadi tidak ada efek samping.
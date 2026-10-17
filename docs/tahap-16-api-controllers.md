# Tahap 16 — API Controllers + FormRequests + Role-Based Routing

Tanggal   : 1 September 2026
Tujuan    : HTTP layer lengkap (controller tipis, validasi via FormRequest, otorisasi via Gate/Policy) untuk semua peran (customer, store owner/staff, courier, admin). Test full regression: 130/130 PASS.

---

## 1. Scope Tahap 16

| Area                      | Hasil                                                       |
| ------------------------- | ----------------------------------------------------------- |
| Form Request baru         | 6 file (8 method)                                           |
| Controllers baru/refactor | 6 controller (Store, Order, Customer, Courier, Admin, StoreOwner) |
| Role-based routing        | 99 routes total di api.php                                  |
| Permission baru           | 6 permission baru + assign ke role seeder                   |
| Tests                     | 34 test baru khusus API endpoints (ApiEndpointsTest)        |
| Regression                | 130/130 PASS (304 assertions, ~23.7s)                       |

Filosofi: controller tipis — validasi via FormRequest, otorisasi via Gate/Policy, bisnis logic delegasi ke Service.

---

## 2. FormRequests (6 file)

| File                                         | Method yang di-validasi                                  |
| -------------------------------------------- | -------------------------------------------------------- |
| app/Http/Requests/Store/SearchStoresRequest  | GET /stores?q=&service_id=&min_rating=&lat=&lng=&radius_km=&sort=&per_page= |
| app/Http/Requests/Store/UpdateStoreRequest   | PATCH /store-owner/stores/{store} (name, address, lat/lng, dsb) |
| app/Http/Requests/Customer/CreateAddressRequest | POST /customer/addresses (label, address, lat/lng, is_default) |
| app/Http/Requests/Customer/UpdateProfileRequest | PATCH /customer/profile (name, phone, email unique check) |
| app/Http/Requests/Order/CreateOrderRequest   | POST /orders (store_id, items[], pickup/delivery_address, payment_method) |
| app/Http/Requests/Order/UpdateOrderStatusRequest | PATCH /orders/{order}/status (status enum, reason opsional, metadata) |
| app/Http/Requests/Order/SetActualWeightRequest | POST /orders/{order}/set-weight (weight numeric 0.1-100) |
| app/Http/Requests/Order/AssignCourierRequest | POST /orders/{order}/assign-courier (task_type enum) |

Total FormRequest di project sekarang: 14 file.

---

## 3. Controllers (6)

### StoreController (marketplace browsing)
- GET /api/stores -> StoreSearchService->search() (paginated, multi-filter)
- GET /api/stores/{store} -> Gate::authorize('view', $store), eager-load services + staff
- GET /api/stores/{store}/services -> hanya price yang is_active=true

### OrderController (core transaction loop)
- GET /api/orders -> scoping per-role:
  - admin (view_any_order): lihat semua + filter status/store_id
  - store owner/staff/courier: gabung owned store + staffed store + own orders + assigned tasks
  - default: hanya order sendiri
- POST /api/orders -> OrderService->createOrder() (delegasi)
- GET /api/orders/{order}/tracking -> return order ringkas + status history + courier tasks
- PATCH /api/orders/{order}/status -> OrderService->transition() (state machine)
- POST /api/orders/{order}/cancel -> OrderService->transition('cancelled')
- POST /api/orders/{order}/set-weight -> OrderService->setActualWeight() (recalc total)
- POST /api/orders/{order}/assign-courier -> CourierAssignmentService->assignCourier()

### CustomerController (profile + address)
- GET/PATCH /api/customer/profile
- GET/POST/DELETE /api/customer/addresses (default-address dalam DB transaction)

### CourierController (dashboard kurir)
- GET /api/courier/dashboard -> active tasks, today/lifetime completed, is_available
- PATCH /api/courier/availability -> toggle is_available
- GET /api/courier/tasks -> filter status opsional

### AdminController (platform admin)
- GET /api/admin/dashboard -> 9 metrik: users (total/active), stores (total/approved/pending), orders (total/today/in-progress), revenue (today/month), refunds pending
- GET /api/admin/users -> filter role + q (name/email like), eager-load roles
- GET /api/admin/stores -> filter status (termasuk pending/rejected)

### StoreOwnerController (manajemen toko)
- GET /api/store-owner/stores -> owned + staffed (gabung dua collection)
- GET/PATCH /api/store-owner/stores/{store} -> Gate::authorize('view'/'update')
- POST /api/store-owner/stores/{store}/submit -> StoreService->submit()
- GET /api/store-owner/stores/{store}/orders -> filter status opsional

---

## 4. Role-Based Routing

99 routes di api.php dikelompokkan:
- Public webhook: /webhooks/xendit (1)
- Auth-only light browsing: /stores, /stores/{store}, /stores/{store}/services (3)
- Payments, Refunds, Reviews, Disputes, Settlements, Courier Tasks (35)
- Customer: /customer/profile, /customer/addresses (5)
- Orders: index/show/store/status/cancel/set-weight/assign-courier/tracking (8)
- Store owner: /store-owner/stores/* (8)
- Courier: /courier/dashboard, /courier/availability, /courier/tasks (3)
- Admin: /admin/dashboard, /admin/users, /admin/stores (3)
- AI chat, Auth, Health (31)

Otorisasi dilakukan oleh middleware auth + Gate::forUser($u)->authorize(...) di setiap method controller, bukan di level route — supaya policy bisa akses model & context.

---

## 5. Permissions Baru (6)

| Permission              | Assign ke role                          |
| ----------------------- | --------------------------------------- |
| view_courier_dashboard  | courier                                 |
| update_courier_availability | courier                              |
| view_store_owner_panel  | store_owner, store_staff                |
| view_admin_dashboard    | admin                                   |
| view_any_order         | admin                                   |
| view_order             | store_owner, store_staff, courier       |

Disuntikkan via database/seeders/RolePermissionSeeder.php + dijalankan ulang saat seeding.

---

## 6. Tests (ApiEndpointsTest — 34 test, 64 assertions)

### Store browsing (5 test)
- ✓ public market search works with filters
- ✓ store detail with services and staff loads
- ✓ suspended/pending store hidden from market
- ✓ unauthenticated user gets 401 on /stores
- ✓ store detail returns 403 for suspended store viewed by customer

### Order endpoints (8 test)
- ✓ customer creates order with valid payload
- ✓ customer sees only own orders by default
- ✓ store owner sees orders for own store
- ✓ store owner cannot update other store's order
- ✓ admin sees all orders with filter
- ✓ courier sees assigned tasks
- ✓ cancel order updates status history
- ✓ set-weight recalculates total

### Customer profile + address (4 test)
- ✓ profile read/update works
- ✓ address CRUD works
- ✓ default address toggle within transaction
- ✓ customer cannot delete another user's address (403)

### Courier dashboard (4 test)
- ✓ dashboard returns active tasks + stats
- ✓ availability toggle persists
- ✓ tasks filter by status
- ✓ non-courier gets 403 on dashboard

### Admin dashboard (4 test)
- ✓ admin dashboard returns 9 metrics
- ✓ admin users list with role filter
- ✓ admin stores list with status filter
- ✓ admin dashboard returns consistent 403 for unauthorized

### Auth gate (4 test)
- ✓ guest gets 401 on all protected endpoints
- ✓ create order with unauthenticated token is 401
- ✓ ... (lainnya konsisten 401/403 untuk role yang salah)

### Happy path integration (5 test)
- ✓ full order lifecycle: create -> pay -> process -> ready -> deliver
- ✓ store search returns paginated results
- ✓ update store profile persists
- ✓ submit store for review triggers state transition
- ✓ courier assignment creates CourierTask

---

## 7. Hasil Test

```
Tests:    130 passed (304 assertions)
Duration: 23.74s
```

Test suite Feature (semua test, termasuk tahap 1-15):
- ApiEndpointsTest         : 34 PASS
- AuthTest                 : PASS
- AiAssistantTest          : PASS
- CourierAssignmentTest    : PASS
- NotificationTest         : PASS
- OrderServiceTest         : PASS
- OrderWithGeocodingTest   : PASS
- PaymentAuthorizationTest : PASS
- PaymentTest              : PASS
- RbacTest                 : PASS
- RefundTest               : PASS
- ReviewDisputeTest        : PASS
- SettlementTest           : PASS
- StoreAuthorizationTest   : PASS
- StoreOnboardingTest      : PASS
- StoreSearchTest          : PASS

---

## 8. Verifikasi

- php artisan test --testsuite=Feature -> 130/130 PASS, 0 fail
- Tidak ada test lama yang regresi
- php -l lulus di semua file controller/request baru
- Routes: php artisan route:list --path=api -> semua 99 routes terdaftar

Tahap 16 selesai. HTTP layer siap untuk tahap 17 (frontend integration / integrasi end-to-end).
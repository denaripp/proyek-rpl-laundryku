<?php

namespace App\Http\Controllers\Web;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Services\Store\StoreSearchService;
use App\Services\StoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tahap 17 — Frontend customer journey pages (Blade).
 * Controller ringan: orchestrator ke service / Eloquent. Render Blade.
 */
class PageController extends Controller
{
    public function __construct(
        protected StoreSearchService $searchService,
        protected StoreService $storeService
    ) {}

    public function home()
    {
        $topStores = Store::query()
            ->approved()
            ->orderByDesc('average_rating')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'average_rating', 'review_count', 'address', 'latitude', 'longitude']);

        return view('pages.home', compact('topStores'));
    }

    public function marketplace(Request $request)
    {
        $results = $this->searchService->search(
            $request->only(['q', 'service_id', 'min_rating', 'latitude', 'longitude', 'radius_km', 'sort']),
            page: (int) $request->input('page', 1),
            perPage: 12,
        );

        // Serialize for map (Blade parser can choke on arrow-fn in @json)
        $mapStores = $results->getCollection()->map(function ($s) {
            return [
                'name' => $s->name,
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'slug' => $s->slug,
                'address' => $s->address,
            ];
        })->filter(fn($s) => $s['lat'] && $s['lng'])->values();

        return view('pages.marketplace', [
            'results' => $results,
            'mapStores' => $mapStores,
            'filters' => $request->only(['q', 'service_id', 'min_rating', 'latitude', 'longitude', 'radius_km', 'sort']),
        ]);
    }

    public function storeDetail(string $slug)
    {
        $store = Store::with([
            'servicePrices.service',
            'staff.user:id,name,email',
            'reviews' => fn($q) => $q->where('is_visible', true)->latest()->limit(5),
        ])
            ->where('slug', $slug)
            ->where('status', 'approved')
            ->firstOrFail();

        $services = $store->servicePrices()->where('is_active', true)->with('service')->get();
        $hours = $store->operational_hours ?? [];
        $reviews = $store->reviews;

        return view('pages.store-detail', compact('store', 'services', 'hours', 'reviews'));
    }

    public function checkout(Request $request, Store $store)
    {
        abort_unless($store->isApproved(), 404);
        $services = $store->servicePrices()->where('is_active', true)->with('service')->get();
        $addresses = Auth::user()?->addresses()->orderByDesc('is_default')->get() ?? collect();
        return view('pages.checkout', compact('store', 'services', 'addresses'));
    }

    public function tracking(Order $order)
    {
        abort_unless(
            Auth::id() === $order->customer_id || Auth::user()?->can('view', $order),
            403
        );
        $order->load(['items.service', 'store', 'payment', 'statusHistories', 'courierTasks.courier']);
        return view('pages.tracking', compact('order'));
    }

    public function payment(Order $order)
    {
        abort_unless(Auth::id() === $order->customer_id, 403);
        $order->load('payment');
        return view('pages.payment', compact('order'));
    }

    public function dashboard()
    {
        $user = Auth::user();
        $activeOrders = $user->ordersAsCustomer()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with('store:id,name')
            ->latest()
            ->limit(5)
            ->get();
        $recentOrders = $user->ordersAsCustomer()
            ->with('store:id,name')
            ->latest()
            ->limit(10)
            ->get();
        $addresses = $user->addresses()->orderByDesc('is_default')->get();
        $notifications = $user->notifications()->latest()->limit(10)->get();

        return view('pages.dashboard', compact('activeOrders', 'recentOrders', 'addresses', 'notifications'));
    }

    public function storeOnboardForm(Request $request)
    {
        $user = Auth::user();
        $store = null;
        $userStores = collect();
        $availableServices = collect();
        $isNewMode = $request->boolean('new') || $request->input('mode') === 'new';

        if ($user) {
            $userStores = $user->ownedStores()->latest()->with(['application', 'servicePrices.service'])->get();

            if ($isNewMode) {
                // User explicitly wants to create a new store
                $store = null;
            } elseif ($request->filled('store_id')) {
                // User wants to view/edit a specific store
                $store = $userStores->firstWhere('id', (int) $request->input('store_id')) ?? $userStores->first();
            } else {
                // Default: show the latest store (or null if none)
                $store = $userStores->first();
            }

            $availableServices = Service::where('is_active', true)->get();
            if ($availableServices->isEmpty()) {
                $defaultNames = ['Cuci Komplit (Kiloan)', 'Cuci Kering (Kiloan)', 'Setrika Saja (Kiloan)', 'Bed Cover (Satuan)', 'Dry Clean (Satuan)'];
                foreach ($defaultNames as $dName) {
                    $availableServices->push(Service::firstOrCreate(['name' => $dName], ['is_active' => true]));
                }
            }
        }

        return view('pages.store-onboard', compact('store', 'userStores', 'availableServices', 'isNewMode'));
    }

    public function storeOnboardSubmit(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $action = $request->input('action', 'submit'); // 'draft' or 'submit'

        if ($action === 'submit') {
            // Full validation for final submission
            $validated = $request->validate([
                'store_id' => 'nullable|integer',
                'owner_phone' => 'nullable|string|max:20',
                'owner_whatsapp' => 'nullable|string|max:20',
                'name' => 'required|string|max:100',
                'description' => 'nullable|string|max:1000',
                'phone' => 'required|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'address' => 'required|string|max:500',
                'rt_rw' => 'nullable|string|max:20',
                'kelurahan' => 'nullable|string|max:100',
                'kecamatan' => 'nullable|string|max:100',
                'city' => 'required|string|max:100',
                'province' => 'required|string|max:100',
                'postal_code' => 'required|string|max:10',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'service_radius_km' => 'nullable|numeric|min:1|max:50',
                'minimum_order_amount' => 'nullable|numeric|min:0',
                'pickup_fee' => 'nullable|numeric|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'cod_enabled' => 'nullable|boolean',
                'open_time' => 'nullable|string',
                'close_time' => 'nullable|string',
                'services' => 'required|array|min:1',
                'services.*.selected' => 'nullable|boolean',
                'services.*.price' => 'nullable|numeric|min:0',
                'services.*.unit' => 'nullable|string|in:kg,item',
            ]);
        } else {
            // Partial validation for DRAFT (loose, only type checks)
            $validated = $request->validate([
                'store_id' => 'nullable|integer',
                'owner_phone' => 'nullable|string|max:20',
                'owner_whatsapp' => 'nullable|string|max:20',
                'name' => 'nullable|string|max:100',
                'description' => 'nullable|string|max:1000',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'rt_rw' => 'nullable|string|max:20',
                'kelurahan' => 'nullable|string|max:100',
                'kecamatan' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'province' => 'nullable|string|max:100',
                'postal_code' => 'nullable|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'service_radius_km' => 'nullable|numeric|min:1|max:50',
                'minimum_order_amount' => 'nullable|numeric|min:0',
                'pickup_fee' => 'nullable|numeric|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'cod_enabled' => 'nullable|boolean',
                'open_time' => 'nullable|string',
                'close_time' => 'nullable|string',
                'services' => 'nullable|array',
            ]);
        }

        return DB::transaction(function () use ($user, $request, $action, $validated) {
            // 1. Update user phone if provided
            if (!empty($validated['owner_phone']) && !$user->phone) {
                $user->update(['phone' => $validated['owner_phone']]);
            }

            // 2. Determine target store: existing by ID, or active draft, or new store
            $store = null;
            if (!empty($validated['store_id'])) {
                $store = $user->ownedStores()->where('id', (int) $validated['store_id'])->first();
            }

            if (!$store) {
                if ($request->boolean('is_new') || $action === 'submit') {
                    $store = new Store(['owner_user_id' => $user->id, 'status' => StoreStatus::DRAFT->value]);
                } else {
                    // Reuse existing draft if available, otherwise new
                    $store = $user->ownedStores()->where('status', StoreStatus::DRAFT->value)->latest()->first()
                        ?? new Store(['owner_user_id' => $user->id, 'status' => StoreStatus::DRAFT->value]);
                }
            }

            // Cannot edit if already in terminal/submitted status unless DRAFT, REVISION_REQUIRED, or REJECTED
            if (!$store->exists || in_array($store->status, [StoreStatus::DRAFT->value, StoreStatus::REVISION_REQUIRED->value, StoreStatus::REJECTED->value])) {
                $storeName = !empty($validated['name']) ? $validated['name'] : ($store->name ?: 'Draft Toko ' . ($user->ownedStores()->count() + 1));

                // Generate slug if new or name changed
                if (!$store->slug || $store->name !== $storeName) {
                    $baseSlug = Str::slug($storeName);
                    $slug = $baseSlug ?: 'toko-' . Str::lower(Str::random(5));
                    $counter = 1;
                    while (Store::where('slug', $slug)->where('id', '!=', $store->id ?? 0)->exists()) {
                        $slug = $baseSlug . '-' . $counter++;
                    }
                    $store->slug = $slug;
                }

                $store->name = $storeName;
                $store->description = $validated['description'] ?? $store->description;
                $store->phone = $validated['phone'] ?? ($store->phone ?: ($user->phone ?: '081200000000'));
                $store->whatsapp = $validated['whatsapp'] ?? $store->whatsapp;
                $store->address = $validated['address'] ?? ($store->address ?: 'Alamat belum diisi');
                $store->rt_rw = $validated['rt_rw'] ?? $store->rt_rw;
                $store->kelurahan = $validated['kelurahan'] ?? $store->kelurahan;
                $store->kecamatan = $validated['kecamatan'] ?? $store->kecamatan;
                $store->city = $validated['city'] ?? ($store->city ?: 'Jakarta');
                $store->province = $validated['province'] ?? ($store->province ?: 'DKI Jakarta');
                $store->postal_code = $validated['postal_code'] ?? ($store->postal_code ?: '10000');
                $store->latitude = $validated['latitude'] ?? ($store->latitude ?: -6.200000);
                $store->longitude = $validated['longitude'] ?? ($store->longitude ?: 106.816666);
                $store->service_radius_km = $validated['service_radius_km'] ?? ($store->service_radius_km ?: 5.0);
                $store->minimum_order_amount = $validated['minimum_order_amount'] ?? $store->minimum_order_amount;
                $store->pickup_fee = $validated['pickup_fee'] ?? ($store->pickup_fee ?: 0);
                $store->delivery_fee = $validated['delivery_fee'] ?? ($store->delivery_fee ?: 0);
                $store->cod_enabled = $request->boolean('cod_enabled');

                // Operational hours
                $openTime = $request->input('open_time', '08:00');
                $closeTime = $request->input('close_time', '20:00');
                $store->operational_hours = [
                    'monday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'tuesday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'wednesday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'thursday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'friday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'saturday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                    'sunday' => ['open' => $openTime, 'close' => $closeTime, 'is_open' => true],
                ];

                $store->save();
            }

            // 3. Assign role store_owner immediately upon store creation/draft/submission
            $role = Role::where('name', 'store_owner')->first();
            if ($role) {
                $hasStoreRole = $user->roles()
                    ->where('roles.id', $role->id)
                    ->wherePivot('store_id', $store->id)
                    ->exists();

                if (!$hasStoreRole) {
                    $user->roles()->attach($role->id, [
                        'store_id' => $store->id,
                        'assigned_at' => now(),
                    ]);
                }
            }

            // 4. Save service prices
            if ($request->has('services')) {
                foreach ($request->input('services', []) as $serviceId => $sData) {
                    $hasSelection = !empty($sData['selected']) || (isset($sData['price']) && $sData['price'] !== '');
                    if ($hasSelection) {
                        $price = isset($sData['price']) && $sData['price'] !== '' ? (float) $sData['price'] : 10000;
                        $unit = !empty($sData['unit']) ? $sData['unit'] : 'kg';
                        ServicePrice::updateOrCreate(
                            ['store_id' => $store->id, 'service_id' => $serviceId],
                            ['price_per_unit' => $price, 'unit' => $unit, 'is_active' => $price > 0]
                        );
                    }
                }
            }

            // 5. Submit if action === 'submit' (Runs StoreService::submit with its built-in full validation)
            if ($action === 'submit') {
                if ($store->status === StoreStatus::REVISION_REQUIRED->value) {
                    $this->storeService->resubmit($store);
                } elseif ($store->status === StoreStatus::DRAFT->value) {
                    $this->storeService->submit($store);
                }

                // Conditional auto-approval check
                $store->refresh();
                $eligibility = $this->storeService->checkAutoApprovalEligibility($store);

                if ($eligibility['is_eligible']) {
                    $this->storeService->autoApprove($store, [
                        'criteria_met' => [
                            'profile_complete' => true,
                            'coordinates_valid' => true,
                            'active_services_count' => $store->servicePrices()->where('is_active', true)->where('price_per_unit', '>', 0)->count(),
                        ],
                    ]);

                    return redirect()->route('store.onboard')
                        ->with('status', 'Pendaftaran berhasil! Toko Anda telah otomatis disetujui dan langsung aktif.');
                }

                // If some conditions are missing, store stays in SUBMITTED status for manual review
                $reasonText = implode(', ', $eligibility['missing_reasons']);
                return redirect()->route('store.onboard')
                    ->with('status', "Pendaftaran laundry berhasil dikirim! Aplikasi Anda sedang ditinjau manual oleh admin (catatan: {$reasonText}).");
            }

            return redirect()->route('store.onboard')
                ->with('status', 'Draft pendaftaran berhasil disimpan.');
        });
    }
}

<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\ServiceCategory;
use App\Models\Store;
use App\Models\StoreService;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase13Test extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $storeOwner;
    protected Store $store;
    protected StoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $customerRole = Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);
        $storeAdminRole = Role::firstOrCreate(['id' => 3, 'code' => 'store_admin'], ['name' => 'Admin Toko']);

        // Create Customer
        $this->customer = User::factory()->create([
            'name' => 'Budi Customer',
            'email' => 'budi@example.com',
            'phone' => '+6281234567890',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->customer->id]);
        UserRole::create(['user_id' => $this->customer->id, 'role_id' => $customerRole->id, 'status' => 'active']);

        // Create Store Owner
        $this->storeOwner = User::factory()->create([
            'name' => 'Pak Haji Laundry',
            'email' => 'owner@laundryku.id',
            'phone' => '+6281987654321',
            'status' => 'active',
            'verified_at' => now(),
        ]);
        UserProfile::create(['user_id' => $this->storeOwner->id]);
        UserRole::create(['user_id' => $this->storeOwner->id, 'role_id' => $storeAdminRole->id, 'status' => 'active']);

        // Create Category & Store
        $cat = ServiceCategory::firstOrCreate(['name' => 'Kiloan', 'slug' => 'kiloan', 'status' => 'active']);
        $this->store = Store::create([
            'owner_user_id' => $this->storeOwner->id,
            'name' => 'Berkah Jaya Laundry',
            'address_text' => 'Jl. Dipati Ukur No. 15',
            'latitude' => -6.890000,
            'longitude' => 107.610000,
            'service_radius_km' => 10.0,
            'approval_status' => 'approved',
            'operational_status' => 'active',
        ]);

        $this->service = StoreService::create([
            'store_id' => $this->store->id,
            'category_id' => $cat->id,
            'name' => 'Cuci Kering Setrika',
            'pricing_unit' => 'kg',
            'price_per_unit' => 8000,
            'minimum_charge' => 0,
            'is_active' => true,
        ]);

        PlatformSetting::updateOrCreate(
            ['key' => 'platform_commission_flat'],
            ['value_json' => ['amount' => 1000], 'description' => 'Flat commission']
        );
    }

    /**
     * 1. Get and update notification preferences.
     */
    public function test_user_can_get_and_update_notification_preferences(): void
    {
        // 1. Get default preferences
        $getRes = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/notifications/preferences');

        $getRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 2. Disable WhatsApp for ORDER_STATUS_CHANGED
        $putRes = $this->actingAs($this->customer, 'sanctum')
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    [
                        'channel' => 'whatsapp',
                        'event_type' => 'ORDER_STATUS_CHANGED',
                        'is_enabled' => false,
                    ],
                ],
            ]);

        $putRes->assertStatus(200)
            ->assertJsonPath('data.updated', true);

        // Verify in DB
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->customer->id,
            'channel' => 'whatsapp',
            'event_type' => 'ORDER_STATUS_CHANGED',
            'is_enabled' => false,
        ]);
    }

    /**
     * 2. Order creation triggers multi-channel notifications for customer and store owner.
     */
    public function test_order_creation_triggers_notifications(): void
    {
        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Cisitu No. 8',
                'latitude' => -6.890100,
                'longitude' => 107.610200,
            ],
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 2.5],
            ],
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        // Customer should have in_app, whatsapp, and email notifications
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'in_app',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'email',
        ]);

        // Store owner should also receive notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->storeOwner->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'in_app',
        ]);
    }

    /**
     * 3. Order status transition triggers notifications (PRICE_ESTIMATED, COMPLETED, etc.).
     */
    public function test_order_status_transitions_trigger_notifications(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20260926-TEST',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test'],
            'status' => 'AT_STORE',
            'payment_status' => 'UNPAID',
            'estimated_amount' => 20000,
            'delivery_fee' => 0,
            'commission_amount' => 1000,
            'courier_wage' => 0,
        ]);

        // Transition: AT_STORE -> WEIGHED -> AWAITING_PRICE_APPROVAL
        $weighedRes = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'WEIGHED',
                'actual_weight_kg' => 3.0,
            ]);
        $weighedRes->assertStatus(200);

        $awaitingRes = $this->actingAs($this->storeOwner, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/transition", [
                'to_status' => 'AWAITING_PRICE_APPROVAL',
            ]);
        $awaitingRes->assertStatus(200);

        // Verify PRICE_ESTIMATED notification was sent to customer
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'PRICE_ESTIMATED',
        ]);
    }

    /**
     * 4. Complaint creation and status update trigger notifications.
     */
    public function test_complaint_lifecycle_triggers_notifications(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20260926-COMP',
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'pickup_address_snapshot' => ['address_text' => 'Jl. Test'],
            'delivery_address_snapshot' => ['address_text' => 'Jl. Test'],
            'status' => 'COMPLETED',
            'payment_status' => 'PAID',
            'estimated_amount' => 25000,
            'final_amount' => 25000,
            'delivery_fee' => 0,
            'commission_amount' => 1000,
            'courier_wage' => 0,
        ]);

        // Customer files a complaint
        $complaintRes = $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/complaints", [
                'category' => 'DAMAGED_ITEM',
                'description' => 'Ada kancing baju yang lepas saat dicuci.',
            ]);

        $complaintRes->assertStatus(201);
        $complaintId = $complaintRes->json('data.id');

        // Store Owner notified
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->storeOwner->id,
            'event_type' => 'COMPLAINT_UPDATED',
        ]);

        // Store Admin resolves complaint
        $resolveRes = $this->actingAs($this->storeOwner, 'sanctum')
            ->patchJson("/api/v1/store-admin/complaints/{$complaintId}/status", [
                'status' => 'RESOLVED',
                'resolution_note' => 'Kancing baju pengganti telah dipasangkan kembali.',
            ]);

        $resolveRes->assertStatus(200);

        // Customer notified of resolution
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'COMPLAINT_UPDATED',
        ]);
    }

    /**
     * 5. Notification failure NEVER cancels or blocks the main transaction.
     */
    public function test_notification_failure_does_not_break_main_transaction(): void
    {
        // Mock NotificationService to simulate an internal exception
        $mock = $this->mock(NotificationService::class);
        $mock->shouldReceive('notifyOrderCreated')
            ->andThrow(new \RuntimeException('Third-party SMS/WhatsApp gateway connection timed out.'));

        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Cisitu No. 8',
                'latitude' => -6.890100,
                'longitude' => 107.610200,
            ],
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 2.0],
            ],
        ];

        // The order MUST still be created successfully (HTTP 201)
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
        ]);
    }

    /**
     * 6. Notification preferences disable specific channels for specific events.
     */
    public function test_disabled_channel_preference_prevents_notification_dispatch(): void
    {
        // Customer disables email for ORDER_CREATED
        NotificationPreference::create([
            'user_id' => $this->customer->id,
            'channel' => 'email',
            'event_type' => 'ORDER_CREATED',
            'is_enabled' => false,
        ]);

        $payload = [
            'store_id' => $this->store->id,
            'pickup_address' => [
                'address_text' => 'Jl. Cisitu No. 8',
                'latitude' => -6.890100,
                'longitude' => 107.610200,
            ],
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 2.0],
            ],
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201);

        // In-app and WhatsApp should be created, Email should NOT
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'in_app',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'whatsapp',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->customer->id,
            'event_type' => 'ORDER_CREATED',
            'channel' => 'email',
        ]);
    }
}

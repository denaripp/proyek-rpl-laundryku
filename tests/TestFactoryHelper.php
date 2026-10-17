<?php

namespace Tests;

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\SettlementPeriod;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Tahap 20 — Shared factory helpers for integration tests.
 *
 * Each helper is independent (doesn't rely on a global setUp chain) so
 * tests can run in any order and don't bleed state.
 */
class TestFactoryHelper
{
    /**
     * Resolve a role by name (requires seeder run).
     */
    public static function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    /**
     * Attach a role to a user.
     */
    public static function makeUser(string $roleName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->roles()->attach(self::role($roleName));
        return $user;
    }

    /**
     * Build an approved, active store + service + price for an owner.
     * Returns the Store; service + price are also persisted.
     */
    public static function makeStore(User $owner, array $overrides = []): array
    {
        $store = Store::factory()->create(array_merge([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ], $overrides));

        $service = Service::create(['name' => 'Laundry ' . Str::random(4), 'is_active' => true]);

        $price = ServicePrice::create([
            'store_id' => $store->id,
            'service_id' => $service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        return ['store' => $store, 'service' => $service, 'price' => $price];
    }

    /**
     * Build a verified courier attached to the given user.
     */
    public static function makeCourier(User $user, array $overrides = []): Courier
    {
        return Courier::create(array_merge([
            'user_id' => $user->id,
            'is_available' => true,
            'is_verified' => true,
            'vehicle_type' => 'motorcycle',
        ], $overrides));
    }

    /**
     * Add a single OrderItem line to an order (FK to a service).
     */
    public static function addOrderItem(Order $order, ServicePrice $price, float $quantity = 5): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'service_id' => $price->service_id,
            'quantity' => $quantity,
            'unit_price' => $price->price_per_unit,
            'subtotal' => $price->price_per_unit * $quantity,
        ]);
    }

    /**
     * Record a paid online payment for an order.
     */
    public static function makePaidPayment(Order $order, ?float $amount = null): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'online',
            'amount' => $amount ?? (float) $order->total_amount,
            'status' => 'paid',
            'xendit_invoice_id' => 'inv_' . Str::random(10),
            'xendit_payment_id' => 'pay_' . Str::random(10),
            'expires_at' => now()->subHour(),
            'paid_at' => now(),
        ]);
    }

    /**
     * Count rows of a model. Convenience for assertions.
     */
    public static function count(string $modelClass): int
    {
        return $modelClass::count();
    }

    /**
     * Mark queue/notification side-effects without actually dispatching.
     */
    public static function fakeSideEffects(): void
    {
        Queue::fake();
    }

    /**
     * Find the audit log entry for a given action + resource id.
     */
    public static function findAudit(string $action, string $resourceType, int $resourceId): ?AuditLog
    {
        return AuditLog::where('action', $action)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->latest('id')
            ->first();
    }

    /**
     * Find notification(s) for a user by type.
     */
    public static function findNotification(User $user, string $type): ?Notification
    {
        return Notification::where('user_id', $user->id)
            ->where('type', $type)
            ->latest('id')
            ->first();
    }
}
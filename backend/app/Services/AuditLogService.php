<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;

class AuditLogService
{
    /**
     * Log authentication events.
     */
    public static function logAuth(
        ?User $user,
        string $action,
        array $metadata = [],
        ?string $ip = null
    ): AuditLog {
        return AuditLog::record(
            actor: $user,
            action: $action,
            entityType: 'auth',
            entityId: $user?->id,
            metadata: $metadata,
            ip: $ip ?? request()->ip()
        );
    }

    /**
     * Log order lifecycle events (Creation, Transition, Cancellation, Expiration).
     */
    public static function logOrder(
        Order $order,
        string $action,
        User|string|null $actor = null,
        array $extraMetadata = [],
        ?string $ip = null
    ): AuditLog {
        $meta = array_merge([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'final_amount' => $order->final_amount,
            'store_id' => $order->store_id,
            'customer_id' => $order->customer_id,
        ], $extraMetadata);

        return AuditLog::record(
            actor: $actor,
            action: strtoupper($action),
            entityType: 'orders',
            entityId: $order->id,
            metadata: $meta,
            ip: $ip ?? request()->ip()
        );
    }

    /**
     * Log payment events (Created, Paid, Failed, Expired, COD Confirmed).
     */
    public static function logPayment(
        Payment $payment,
        string $action,
        User|string|null $actor = null,
        array $extraMetadata = [],
        ?string $ip = null
    ): AuditLog {
        $meta = array_merge([
            'payment_number' => $payment->payment_number,
            'order_id' => $payment->order_id,
            'method' => $payment->payment_method,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'xendit_id' => $payment->xendit_invoice_id ?? $payment->xendit_charge_id,
        ], $extraMetadata);

        return AuditLog::record(
            actor: $actor,
            action: strtoupper($action),
            entityType: 'payments',
            entityId: $payment->id,
            metadata: $meta,
            ip: $ip ?? request()->ip()
        );
    }

    /**
     * Log store administrative events (Submitted, Approved, Rejected, Suspended).
     */
    public static function logStore(
        Store $store,
        string $action,
        User|string|null $actor = null,
        array $extraMetadata = [],
        ?string $ip = null
    ): AuditLog {
        $meta = array_merge([
            'store_name' => $store->name,
            'owner_id' => $store->owner_user_id,
            'approval_status' => $store->approval_status,
            'operational_status' => $store->operational_status,
        ], $extraMetadata);

        return AuditLog::record(
            actor: $actor,
            action: strtoupper($action),
            entityType: 'stores',
            entityId: $store->id,
            metadata: $meta,
            ip: $ip ?? request()->ip()
        );
    }

    /**
     * Log platform configuration changes.
     */
    public static function logSetting(
        string $key,
        mixed $oldVal,
        mixed $newVal,
        User|string|null $actor = null,
        ?string $ip = null
    ): AuditLog {
        return AuditLog::record(
            actor: $actor,
            action: 'UPDATE_PLATFORM_SETTING',
            entityType: 'platform_settings',
            entityId: $key,
            metadata: [
                'setting_key' => $key,
                'old_value' => $oldVal,
                'new_value' => $newVal,
            ],
            ip: $ip ?? request()->ip()
        );
    }

    /**
     * Log store service events (Created, Updated, Status Changed, Deleted, Bulk Status).
     */
    public static function logStoreService(
        \App\Models\StoreService $service,
        string $action,
        User|string|null $actor = null,
        array $extraMetadata = [],
        ?string $ip = null
    ): AuditLog {
        $meta = array_merge([
            'service_name' => $service->name,
            'store_id' => $service->store_id,
            'category_id' => $service->category_id,
            'pricing_unit' => $service->pricing_unit,
            'price_per_unit' => $service->price_per_unit,
            'minimum_charge' => $service->minimum_charge,
            'is_active' => $service->is_active,
        ], $extraMetadata);

        return AuditLog::record(
            actor: $actor,
            action: strtoupper($action),
            entityType: 'store_services',
            entityId: $service->id,
            metadata: $meta,
            ip: $ip ?? request()->ip()
        );
    }
}


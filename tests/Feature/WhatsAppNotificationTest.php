<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppJob;
use App\Models\Courier;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Notifications\NotificationTrigger;
use App\Events\OrderStatusChangedEvent;
use App\Listeners\SendOrderNotification;
use App\Services\Notification\NotificationService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_normalization(): void
    {
        // 0812.. -> 62812..
        $this->assertEquals('628123456789', WhatsAppService::normalizePhone('08123456789'));

        // +62 812-.. -> 62812..
        $this->assertEquals('628123456789', WhatsAppService::normalizePhone('+62 812-3456-789'));

        // 62812.. -> 62812..
        $this->assertEquals('628123456789', WhatsAppService::normalizePhone('628123456789'));

        // Formats with hyphens and spaces
        $this->assertEquals('6281234567890', WhatsAppService::normalizePhone('0812-3456-7890'));

        // Null, empty, or invalid input returns null
        $this->assertNull(WhatsAppService::normalizePhone(null));
        $this->assertNull(WhatsAppService::normalizePhone(''));
        $this->assertNull(WhatsAppService::normalizePhone('   '));
        $this->assertNull(WhatsAppService::normalizePhone('invalid_phone'));
        $this->assertNull(WhatsAppService::normalizePhone('12345')); // too short
    }

    public function test_whatsapp_disabled_skips_silently_without_job_and_without_log(): void
    {
        config(['services.whatsapp.enabled' => false]);
        Queue::fake();

        $user = User::factory()->create(['phone' => '08123456789']);

        $service = app(NotificationService::class);
        $notif = $service->notify($user, 'order_status', 'Pesanan Dibuat', 'Pesanan Anda sedang diproses.');

        $this->assertNotNull($notif);
        Queue::assertNotPushed(SendWhatsAppJob::class);
        $this->assertDatabaseCount('whatsapp_logs', 0);
    }

    public function test_whatsapp_enabled_with_log_driver_records_sent_status(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $user = User::factory()->create(['phone' => '08123456789']);

        $service = app(NotificationService::class);
        $notif = $service->notify($user, 'order_status', 'Pesanan Selesai', 'Cucian Anda sudah selesai.');

        $this->assertNotNull($notif);
        $this->assertContains('whatsapp', $notif->delivered_via ?? []);

        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '628123456789',
            'status' => 'sent',
            'driver' => 'log',
            'event' => 'order_status',
        ]);

        $log = WhatsAppLog::where('recipient_phone', '628123456789')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->error);
    }

    public function test_empty_or_invalid_phone_records_skipped_status_and_other_channels_continue(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $user = User::factory()->create(['phone' => null, 'email' => 'user@example.test']);

        $service = app(NotificationService::class);
        $notif = $service->notify($user, 'order_status', 'Update', 'Isi notifikasi');

        $this->assertNotNull($notif);
        // Channel in_app remains working
        $this->assertContains('in_app', $notif->delivered_via ?? []);
        $this->assertNotContains('whatsapp', $notif->delivered_via ?? []);

        $this->assertDatabaseHas('whatsapp_logs', [
            'user_id' => $user->id,
            'status' => 'skipped',
            'event' => 'order_status',
        ]);
    }

    public function test_fonnte_driver_failure_records_failed_status_and_does_not_throw(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'fonnte',
            'services.whatsapp.fonnte_token' => 'dummy-secret-token',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => false, 'reason' => 'Internal Server Error'], 500),
        ]);

        $user = User::factory()->create(['phone' => '08129876543']);

        $service = app(NotificationService::class);

        // Must not throw any exception
        $notif = $service->notify($user, 'order_status', 'Info Penting', 'Cucian telah ditimbang.');

        $this->assertNotNull($notif);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '628129876543',
            'status' => 'failed',
            'driver' => 'fonnte',
            'event' => 'order_status',
        ]);

        $log = WhatsAppLog::where('recipient_phone', '628129876543')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('500', $log->error);
        // Ensure secret token was NEVER written to the error or response payload
        $this->assertStringNotContainsString('dummy-secret-token', (string) $log->error);
        $this->assertStringNotContainsString('dummy-secret-token', json_encode($log->response_payload));
    }

    public function test_store_owner_resolution_order_of_precedence(): void
    {
        $owner = User::factory()->create(['role' => 'store_admin', 'phone' => '0811111111']);

        // 1. When store has whatsapp number
        $store1 = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'whatsapp' => '0822222222',
            'phone' => '0833333333',
        ]);
        $phone1 = WhatsAppService::resolveRecipientPhone($owner, ['store_id' => $store1->id]);
        $this->assertEquals('0822222222', $phone1);

        // 2. When store has only phone (no whatsapp)
        $store2 = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'whatsapp' => null,
            'phone' => '0833333333',
        ]);
        $phone2 = WhatsAppService::resolveRecipientPhone($owner, ['store_id' => $store2->id]);
        $this->assertEquals('0833333333', $phone2);

        // 3. When store has neither whatsapp nor phone -> fallback to owner users.phone
        $store3 = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'whatsapp' => null,
            'phone' => '',
        ]);
        $phone3 = WhatsAppService::resolveRecipientPhone($owner, ['store_id' => $store3->id]);
        $this->assertEquals('0811111111', $phone3);
    }

    /**
     * Celah 1: Pesanan dibuat -> owner toko (listener SendOrderNotification, HANYA status awal)
     */
    public function test_celah_1_new_order_notifies_store_owner_and_customer_without_duplicates(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $owner = User::factory()->create(['phone' => '081211111111', 'role' => 'store_admin']);
        $customer = User::factory()->create(['phone' => '081222222222', 'role' => 'customer']);
        $store = Store::factory()->create([
            'owner_user_id' => $owner->id,
            'whatsapp' => '081233333333',
            'phone' => '081244444444',
        ]);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CELAH-1',
            'status' => 'created',
        ]);
        $order->setRelation('store', $store);
        $order->setRelation('customer', $customer);

        // 1. Initial order creation event (oldStatus: created, newStatus: created)
        $event = new OrderStatusChangedEvent($order, 'created', 'created', $customer);
        $listener = app()->make(SendOrderNotification::class);
        $listener->handle($event);

        // Customer gets in-app + WhatsApp
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_status',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281222222222',
            'event' => 'order_status',
            'status' => 'sent',
        ]);

        // Store owner gets in-app + WhatsApp (via store whatsapp: 6281233333333)
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'new_order_store',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281233333333',
            'event' => 'new_order_store',
            'status' => 'sent',
        ]);

        // Exactly 1 new_order_store notification for owner
        $this->assertEquals(1, Notification::where('user_id', $owner->id)->where('type', 'new_order_store')->count());

        // 2. Subsequent status transition (created -> processing) should NOT send new_order_store to owner
        $transitionEvent = new OrderStatusChangedEvent($order, 'created', 'processing', $customer);
        $listener->handle($transitionEvent);

        $this->assertEquals(1, Notification::where('user_id', $owner->id)->where('type', 'new_order_store')->count());
    }

    /**
     * Celah 2: CourierPortalController::transitionTask -> picked_up & out_for_delivery -> customer
     */
    public function test_celah_2_courier_task_transition_notifies_customer(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $customer = User::factory()->create(['phone' => '081299990001', 'role' => 'customer']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CELAH-2',
            'status' => 'picked_up',
        ]);
        $order->setRelation('customer', $customer);

        // Picked up
        NotificationTrigger::courierTaskTransitioned($order, 'picked_up');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'picked_up',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990001',
            'event' => 'picked_up',
            'status' => 'sent',
        ]);

        // Out for delivery
        NotificationTrigger::courierTaskTransitioned($order, 'out_for_delivery');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'out_for_delivery',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990001',
            'event' => 'out_for_delivery',
            'status' => 'sent',
        ]);
    }

    /**
     * Celah 3: CourierPortalController::confirmCod -> customer (pembayaran COD diterima)
     */
    public function test_celah_3_courier_confirm_cod_notifies_customer(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $customer = User::factory()->create(['phone' => '081299990002', 'role' => 'customer']);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CELAH-3',
            'total_amount' => 50000,
            'payment_status' => 'paid',
        ]);
        $order->setRelation('customer', $customer);

        NotificationTrigger::codPaymentReceived($order);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'cod_payment_received',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990002',
            'event' => 'cod_payment_received',
            'status' => 'sent',
        ]);
    }

    /**
     * Celah 4: Pembayaran lunas, selesai, dibatalkan -> owner toko
     */
    public function test_celah_4_order_paid_completed_cancelled_notifies_store_owner(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $owner = User::factory()->create(['phone' => '081299990004', 'role' => 'store_admin']);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'whatsapp' => '081299990004', 'phone' => '081299990004']);
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'order_number' => 'ORD-CELAH-4',
        ]);
        $order->setRelation('store', $store);

        // Paid
        NotificationTrigger::orderPaidForStore($order);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'order_paid_store',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990004',
            'event' => 'order_paid_store',
            'status' => 'sent',
        ]);

        // Completed
        NotificationTrigger::orderCompletedForStore($order);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'order_completed_store',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990004',
            'event' => 'order_completed_store',
            'status' => 'sent',
        ]);

        // Cancelled
        NotificationTrigger::orderCancelledForStore($order, 'Pelanggan membatalkan');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'order_cancelled_store',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990004',
            'event' => 'order_cancelled_store',
            'status' => 'sent',
        ]);
    }

    /**
     * Celah 5: Dispute opened -> owner toko + admin; dispute resolved -> owner toko
     */
    public function test_celah_5_dispute_notifications_to_store_and_admins(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $owner = User::factory()->create(['phone' => '081299990005', 'role' => 'store_admin']);
        $admin = User::factory()->create(['phone' => '081299990006', 'role' => 'super_admin']);
        $store = Store::factory()->create(['owner_user_id' => $owner->id, 'whatsapp' => '081299990005', 'phone' => '081299990005']);

        $customer = User::factory()->create();
        $order = Order::factory()->create(['store_id' => $store->id, 'customer_id' => $customer->id, 'order_number' => 'ORD-DISP-1']);
        $order->setRelation('store', $store);
        $order->setRelation('customer', $customer);

        $dispute = Dispute::create([
            'complaint_number' => 'DSP-001',
            'order_id' => $order->id,
            'opened_by' => $customer->id,
            'reason' => 'item_damaged',
            'description' => 'Dispute description',
            'status' => 'complaint_submitted',
        ]);
        $dispute->setRelation('order', $order);

        // 1. Dispute opened
        NotificationTrigger::disputeOpenedForStoreAndAdmins($order, $dispute);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'dispute_opened_store',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'dispute_opened_admin',
        ]);

        // 2. Dispute resolved
        NotificationTrigger::disputeResolvedForStore($order, $dispute, 'refunded');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'dispute_refunded_store',
        ]);
    }

    /**
     * Celah 6: ready_for_delivery -> kurir yang ditugaskan (jika ada)
     */
    public function test_celah_6_ready_for_delivery_notifies_courier_if_assigned(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $courierUser = User::factory()->create(['phone' => '081299990007', 'role' => 'courier']);
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'courier_id' => $courierUser->id,
            'order_number' => 'ORD-READY-1',
        ]);
        $order->setRelation('courier', $courierUser);
        $order->setRelation('store', $store);

        NotificationTrigger::readyForDeliveryCourier($order);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $courierUser->id,
            'type' => 'order_ready_courier',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990007',
            'event' => 'order_ready_courier',
            'status' => 'sent',
        ]);

        // When order has no courier, skips silently without throwing
        $unassignedOrder = Order::factory()->create(['store_id' => $store->id, 'courier_id' => null]);
        NotificationTrigger::readyForDeliveryCourier($unassignedOrder);
        $this->assertTrue(true);
    }

    /**
     * Celah 7: MitraPortalController recruitCourier & rejectCourier -> user kurir
     */
    public function test_celah_7_courier_recruitment_and_rejection_notifies_courier(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'log',
        ]);

        $courierUser = User::factory()->create(['phone' => '081299990008', 'role' => 'courier']);
        $courier = Courier::factory()->create(['user_id' => $courierUser->id]);
        $courier->setRelation('user', $courierUser);
        $store = Store::factory()->create(['name' => 'Toko Laundry Bersih']);

        // Recruited
        NotificationTrigger::courierRecruited($courier, $store);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $courierUser->id,
            'type' => 'courier_recruited',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990008',
            'event' => 'courier_recruited',
            'status' => 'sent',
        ]);

        // Rejected
        NotificationTrigger::courierRejected($courier, $store, 'Kuota kurir penuh');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $courierUser->id,
            'type' => 'courier_rejected',
        ]);
        $this->assertDatabaseHas('whatsapp_logs', [
            'recipient_phone' => '6281299990008',
            'event' => 'courier_rejected',
            'status' => 'sent',
        ]);
    }
}

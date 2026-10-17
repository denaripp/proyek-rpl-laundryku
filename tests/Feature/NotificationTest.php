<?php

namespace Tests\Feature;

use App\Jobs\SendUserNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationTrigger;
use App\Services\Notification\EmailChannel;
use App\Services\Notification\InAppChannel;
use App\Services\Notification\NotificationChannel;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tahap 15 — Notification System coverage:
 *  - Trigger helpers only ENQUEUE; they never block business state.
 *  - Channel abstraction: extra channel plugged in via addChannel().
 *  - Delivery failure does NOT break the order/transaction.
 *  - SendUserNotificationJob retries on failure; final failure logs only.
 *  - Listener (OrderStatusChanged) re-uses the queued pipeline.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_registration_trigger_queues_job_and_does_not_throw(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationTrigger::registered($user);

        Queue::assertPushed(SendUserNotificationJob::class, function ($job) use ($user) {
            return $job->userId === $user->id && $job->type === 'registration';
        });
    }

    public function test_store_lifecycle_triggers_are_all_queued(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $store = new \App\Models\Store(['name' => 'Toko Tes', 'owner_user_id' => $owner->id]);
        $store->id = 1;

        NotificationTrigger::storeSubmitted($owner, $store);
        NotificationTrigger::storeApproved($owner, $store);
        NotificationTrigger::storeRevisionRequested($owner, $store, 'fix alamat');
        NotificationTrigger::storeRejected($owner, $store, 'izin tidak valid');

        Queue::assertPushed(SendUserNotificationJob::class, 4);
    }

    public function test_courier_and_order_milestone_triggers_queue(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $courier = User::factory()->create();
        $order = new \App\Models\Order(['order_number' => 'ORD-T15-001']);
        $order->id = 99;
        $order->customer_id = $customer->id;
        $order->setRelation('customer', $customer);

        NotificationTrigger::courierAssigned($courier, $order, 'pickup');
        NotificationTrigger::courierArriving($customer, $order);
        NotificationTrigger::pickupCompleted($customer, $order);
        NotificationTrigger::laundryReceived($customer, $order);
        NotificationTrigger::laundryWeighed($customer, $order, 3.5);
        NotificationTrigger::finalPriceUpdated($customer, $order, 35000, 42000);
        NotificationTrigger::processing($customer, $order);
        NotificationTrigger::ready($customer, $order);
        NotificationTrigger::deliveryOngoing($customer, $order);
        NotificationTrigger::completed($customer, $order);

        Queue::assertPushed(SendUserNotificationJob::class, 10);
    }

    public function test_payment_triggers_queue(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $order = new \App\Models\Order(['order_number' => 'ORD-PAY-1']);
        $order->id = 1;
        $order->setRelation('customer', $customer);

        NotificationTrigger::paymentRequired($customer, $order, 50000);
        NotificationTrigger::paymentSuccess($customer, $order);
        NotificationTrigger::paymentFailed($customer, $order);
        NotificationTrigger::paymentExpired($customer, $order);
        NotificationTrigger::codConfirmation($customer, $order);

        Queue::assertPushed(SendUserNotificationJob::class, 5);
    }

    public function test_refund_and_dispute_triggers_queue(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $order = new \App\Models\Order(['order_number' => 'ORD-REF-1']);
        $order->id = 1;
        $order->setRelation('customer', $customer);

        $refund = new \App\Models\Refund(['amount' => 20000]);
        $refund->id = 7;
        NotificationTrigger::refundRequested($customer, $order, $refund);
        NotificationTrigger::refundCompleted($customer, $order, $refund);

        $dispute = new \App\Models\Dispute(['case_number' => 'DSP-1']);
        $dispute->id = 11;
        NotificationTrigger::disputeOpened($customer, $order, $dispute);
        NotificationTrigger::disputeResolved($customer, $order, $dispute, 'refunded');

        Queue::assertPushed(SendUserNotificationJob::class, 4);
    }

    public function test_null_user_is_silently_ignored(): void
    {
        Queue::fake();

        NotificationTrigger::toUser(null, 'x', 't', 'c');

        Queue::assertNothingPushed();
        $this->assertTrue(true); // never throws
    }

    public function test_send_job_persists_notification_and_records_in_app_channel(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'cust@example.test']);

        $service = new NotificationService();
        $service->notify($user, 'order_status', 'Test', 'Hello body', ['x' => 1]);

        $row = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($row, 'Notification row must be persisted');
        $this->assertContains('in_app', $row->delivered_via ?? []);
    }

    public function test_email_channel_failure_does_not_break_persistence(): void
    {
        // Email channel returns false when user has no email, but row stays persisted.
        $user = User::factory()->create(['email' => '']);

        $service = new NotificationService();
        $row = $service->notify($user, 'system', 'Hi', 'Body');

        $this->assertNotNull($row);
        $this->assertContains('in_app', $row->delivered_via ?? []);
        $this->assertNotContains('email', $row->delivered_via ?? []);
    }

    public function test_new_channel_can_be_plugged_in_without_touching_triggers(): void
    {
        Mail::fake();

        $customCalled = false;
        $custom = new class($customCalled) implements NotificationChannel {
            public bool $called = false;
            public function __construct(&$flag) { $this->called = &$flag; }
            public function key(): string { return 'webhook'; }
            public function send(\App\Models\Notification $notification): bool
            {
                $this->called = true;
                return true;
            }
        };
        $custom->called = &$customCalled;

        $service = new NotificationService();
        $service->addChannel($custom);

        $user = User::factory()->create(['email' => 'a@b.test']);
        $row = $service->notify($user, 'order_status', 'T', 'C');

        $this->assertTrue($customCalled, 'Plugged-in channel must be invoked');
        $this->assertContains('webhook', $row->delivered_via ?? []);
    }

    public function test_failing_channel_does_not_affect_other_channels(): void
    {
        Mail::fake();

        $alwaysFail = new class implements NotificationChannel {
            public function key(): string { return 'broken'; }
            public function send(\App\Models\Notification $notification): bool
            {
                throw new \RuntimeException('channel down');
            }
        };

        $service = new NotificationService(['in_app' => new InAppChannel(), 'broken' => $alwaysFail]);
        $user = User::factory()->create(['email' => 'x@y.test']);
        $row = $service->notify($user, 'order_status', 'T', 'C');

        $this->assertNotNull($row);
        $this->assertContains('in_app', $row->delivered_via ?? []);
        $this->assertNotContains('broken', $row->delivered_via ?? []);
    }

    public function test_queued_job_handles_user_id_and_persists_via_service(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'q@w.test']);

        $job = new SendUserNotificationJob($user->id, 'registration', 'Welcome', 'Hello!', ['k' => 'v']);
        $job->handle(app(NotificationService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'registration',
            'title' => 'Welcome',
        ]);
    }

    public function test_failed_job_logs_and_does_not_rethrow(): void
    {
        // User vanishes between dispatch and handle(): job should silently no-op.
        $job = new SendUserNotificationJob(999999, 'registration', 'T', 'C');
        $job->handle(app(NotificationService::class));

        $job->failed(new \RuntimeException('boom'));
        $this->assertTrue(true); // would throw on failure
    }

    public function test_listener_uses_queued_pipeline_for_order_status_event(): void
    {
        Queue::fake();

        $customer = User::factory()->create();
        $order = new \App\Models\Order(['order_number' => 'ORD-LIS-1', 'status' => 'created']);
        $order->id = 1;
        $order->setRelation('customer', $customer);

        $event = new \App\Events\OrderStatusChangedEvent($order, 'created', 'processing', $customer, null, []);

        // Resolve the listener through the container so it sees the same Queue facade.
        $listener = app()->make(\App\Listeners\SendOrderNotification::class);
        $listener->handle($event);

        Queue::assertPushed(SendUserNotificationJob::class, 1);
    }

    public function test_notification_trigger_catches_and_suppresses_queue_dispatch_exception(): void
    {
        $user = User::factory()->create();

        // Mock Queue to throw an exception on push
        Queue::shouldReceive('connection')->andReturnSelf();
        Queue::shouldReceive('pushOn')->andThrow(new \RuntimeException('Queue server down'));
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Queue server down'));

        // NotificationTrigger must NEVER throw or break caller
        NotificationTrigger::toUser($user, 'test_type', 'Test Title', 'Test Content');
        NotificationTrigger::orderStatusChanged($user, new \App\Models\Order(), 'created', 'processing');
        NotificationTrigger::paymentSuccess($user, new \App\Models\Order());
        NotificationTrigger::refundCompleted($user, new \App\Models\Order(), new \App\Models\Refund());

        $this->assertTrue(true);
    }

    public function test_order_completion_succeeds_even_if_notification_dispatch_fails(): void
    {
        $owner = User::factory()->create();
        $roleOwner = \App\Models\Role::where('name', 'store_owner')->first();
        if ($roleOwner) {
            $owner->roles()->attach($roleOwner);
        }

        $customer = User::factory()->create();
        $store = \App\Models\Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $order = \App\Models\Order::factory()->create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'total_amount' => 50000,
        ]);
        \App\Models\Payment::create([
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 50000,
            'payment_method' => 'xendit',
            'xendit_invoice_id' => 'INV-TEST-RESILIENCE',
        ]);

        // Break Queue push to simulate queue/Redis offline
        Queue::shouldReceive('connection')->andReturnSelf();
        Queue::shouldReceive('pushOn')->andThrow(new \RuntimeException('Redis unavailable'));
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Redis unavailable'));

        $this->actingAs($owner);
        $orderService = app(\App\Services\Order\OrderService::class);
        $updatedOrder = $orderService->transition($order, 'completed', $owner, 'Order completed successfully');

        $this->assertEquals('completed', $updatedOrder->fresh()->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_store_submission_succeeds_even_if_notification_dispatch_fails(): void
    {
        $owner = User::factory()->create();
        $roleOwner = \App\Models\Role::where('name', 'store_owner')->first();
        if ($roleOwner) {
            $owner->roles()->attach($roleOwner);
        }

        $store = \App\Models\Store::factory()->create([
            'owner_user_id' => $owner->id,
            'status' => 'draft',
            'name' => 'Resilience Laundry',
            'phone' => '08123456789',
            'address' => 'Jl. Uji Ketahanan No. 1',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        // Break Queue push
        Queue::shouldReceive('connection')->andReturnSelf();
        Queue::shouldReceive('pushOn')->andThrow(new \RuntimeException('Redis or Queue server down'));
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('Redis or Queue server down'));

        $this->actingAs($owner);
        $storeService = app(\App\Services\StoreService::class);
        $submittedStore = $storeService->submit($store);

        $this->assertEquals('submitted', $submittedStore->fresh()->status);
        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'status' => 'submitted',
        ]);
        $this->assertDatabaseHas('store_applications', [
            'store_id' => $store->id,
        ]);
    }
}

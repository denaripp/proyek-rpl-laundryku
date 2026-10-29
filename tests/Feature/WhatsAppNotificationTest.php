<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppJob;
use App\Models\Notification;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppLog;
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
}

<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpSecurityAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['id' => 1, 'code' => 'customer'], ['name' => 'Customer']);
        Role::firstOrCreate(['id' => 2, 'code' => 'store_admin'], ['name' => 'Store Admin']);
        Role::firstOrCreate(['id' => 3, 'code' => 'courier'], ['name' => 'Courier']);
        Role::firstOrCreate(['id' => 4, 'code' => 'super_admin'], ['name' => 'Super Admin']);
    }

    public function test_registration_otp_does_not_store_plain_code_in_notifications_table(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Security Test User',
            'channel' => 'email',
            'destination' => 'security.test@laundryku.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        // Verify that no notification with in_app channel or plain OTP was stored
        $inAppOtpNotifications = Notification::where('event_type', 'like', '%OTP%')
            ->where('channel', 'in_app')
            ->get();

        $this->assertCount(0, $inAppOtpNotifications, 'OTP should not generate in-app notification records (FR-NOTIF-04).');

        // Check that absolutely no notification row contains 6-digit plain OTP in message or payload
        $allNotifications = Notification::all();
        $plainOtpNotifications = $allNotifications->filter(function ($n) {
            return ($n->message && preg_match('/[0-9]{6}/', $n->message)) ||
                ($n->payload && is_array($n->payload) && isset($n->payload['otp']) && $n->payload['otp'] !== '[REDACTED]');
        });

        $this->assertCount(0, $plainOtpNotifications, 'No notification message or payload should contain plain OTP code.');
    }

    public function test_get_notifications_api_never_returns_plain_otp_codes(): void
    {
        $user = User::create([
            'name' => 'Notification Tester',
            'email' => 'notif.user@laundryku.test',
            'phone' => '+6281234567800',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'verified_at' => now(),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => 1,
            'status' => 'active',
        ]);

        // Insert standard order notification
        Notification::create([
            'user_id' => $user->id,
            'channel' => 'in_app',
            'event_type' => 'ORDER_CREATED',
            'title' => 'Pesanan Berhasil Dibuat',
            'message' => 'Pesanan laundry #ORD-123456 berhasil dibuat.',
            'payload' => ['order_id' => '123456'],
            'status' => 'unread',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $responseContent = $response->getContent();

        // Ensure no OTP references in response
        $this->assertStringNotContainsString('Kode OTP rahasia Anda adalah', $responseContent);
        $this->assertStringNotContainsString('"otp":', $responseContent);
    }

    public function test_otp_is_only_sent_to_designated_registration_channel(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Email Channel User',
            'channel' => 'email',
            'destination' => 'channel.test@laundryku.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        // Ensure OtpMail was queued/sent to the specific email
        Mail::assertSent(OtpMail::class, function ($mail) {
            return $mail->hasTo('channel.test@laundryku.test');
        });

        // Ensure no in-app notification created for this user
        $user = User::where('email', 'channel.test@laundryku.test')->first();
        $this->assertNotNull($user);

        $notifCount = Notification::where('user_id', $user->id)->count();
        $this->assertEquals(0, $notifCount, 'In-app notification must not be created for registration OTP.');
    }
}

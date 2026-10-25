<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Services\Notification\EmailChannel;
use App\Services\OtpService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfilePlaceholderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(SuperAdminSeeder::class);
    }

    public function test_user_model_recognizes_placeholder_and_real_emails(): void
    {
        $this->assertTrue(User::isPlaceholderEmail('wa_6283150684755@laundryku.test'));
        $this->assertTrue(User::isPlaceholderEmail('WA_08123456789@laundryku.test'));
        
        // Seeder / test accounts must NOT be treated as placeholder
        $this->assertFalse(User::isPlaceholderEmail('customer@laundryku.test'));
        $this->assertFalse(User::isPlaceholderEmail('mitra@laundryku.test'));
        $this->assertFalse(User::isPlaceholderEmail('kurir@laundryku.test'));
        $this->assertFalse(User::isPlaceholderEmail('admin@laundryku.test'));
        $this->assertFalse(User::isPlaceholderEmail('someone@laundryku.test'));
        $this->assertFalse(User::isPlaceholderEmail('budi@gmail.com'));
        $this->assertFalse(User::isPlaceholderEmail('customer@yahoo.co.id'));
        $this->assertFalse(User::isPlaceholderEmail(null));

        $whatsappUser = User::create([
            'name' => 'WhatsApp User',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $this->assertFalse($whatsappUser->has_real_email);
        $this->assertNull($whatsappUser->display_email);
        $this->assertSame('+6283150684755', $whatsappUser->display_phone);

        $realEmailUser = User::create([
            'name' => 'Email User',
            'email' => 'real.email@example.com',
            'phone' => '6281234567890',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $this->assertTrue($realEmailUser->has_real_email);
        $this->assertSame('real.email@example.com', $realEmailUser->display_email);
        $this->assertSame('+6281234567890', $realEmailUser->display_phone);
    }

    public function test_customer_profile_api_returns_null_email_and_flags_for_whatsapp_account(): void
    {
        $user = User::create([
            'name' => 'Arieef Customer',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $user->roles()->syncWithoutDetaching([$customerRole->id => ['assigned_at' => now()]]);
        }

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Arieef Customer',
                    'email' => null,
                    'display_email' => null,
                    'has_real_email' => false,
                    'phone' => '083150684755',
                    'display_phone' => '+6283150684755',
                ],
            ]);
    }

    public function test_customer_can_add_real_email_via_update_profile_api(): void
    {
        $user = User::create([
            'name' => 'Arieef Customer',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $user->roles()->syncWithoutDetaching([$customerRole->id => ['assigned_at' => now()]]);
        }

        Sanctum::actingAs($user, ['*']);

        $updateResponse = $this->putJson('/api/v1/profile', [
            'name' => 'Arieef Updated',
            'email' => 'arieef.real@gmail.com',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Arieef Updated',
                    'email' => 'arieef.real@gmail.com',
                    'display_email' => 'arieef.real@gmail.com',
                    'has_real_email' => true,
                ],
            ]);

        $user->refresh();
        $this->assertSame('arieef.real@gmail.com', $user->email);
        $this->assertTrue($user->has_real_email);
    }

    public function test_api_me_returns_clean_display_email_for_whatsapp_account(): void
    {
        $user = User::create([
            'name' => 'Arieef Me Test',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => null,
                    'display_email' => null,
                    'has_real_email' => false,
                    'display_phone' => '+6283150684755',
                ],
            ]);
    }

    public function test_email_notification_channel_skips_users_with_placeholder_email(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Placeholder User',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'ORDER_UPDATE',
            'title' => 'Pesanan Diproses',
            'content' => 'Pesanan Anda sedang diproses oleh toko.',
        ]);

        $channel = new EmailChannel();
        $result = $channel->send($notification);

        $this->assertFalse($result);
        Mail::assertNothingSent();
    }

    public function test_otp_service_blocks_dispatch_to_placeholder_email(): void
    {
        $user = User::create([
            'name' => 'Placeholder User',
            'email' => 'wa_6283150684755@laundryku.test',
            'phone' => '083150684755',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $otpService = app(OtpService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Alamat email placeholder tidak dapat digunakan');

        $otpService->generateOtp($user, 'email', 'wa_6283150684755@laundryku.test');
    }
}

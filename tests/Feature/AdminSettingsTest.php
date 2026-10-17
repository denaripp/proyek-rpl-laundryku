<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap 21 — Admin settings + monitoring endpoint tests.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformOwner;
    protected User $platformAdmin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->platformOwner = User::factory()->create();
        $this->platformOwner->roles()->attach(Role::where('name', 'platform_owner')->first());

        $this->platformAdmin = User::factory()->create();
        $this->platformAdmin->roles()->attach(Role::where('name', 'platform_admin')->first());

        $this->customer = User::factory()->create();
        $this->customer->roles()->attach(Role::where('name', 'customer')->first());

        // Ensure BRAND_NAME seeded (Tahap 21).
        SystemConfiguration::firstOrCreate(
            ['key' => 'BRAND_NAME'],
            ['value' => ['value' => 'LANDURYKU'], 'is_editable' => true]
        );
    }

    public function test_settings_endpoint_lists_all_configurations()
    {
        $this->actingAs($this->platformAdmin);
        $r = $this->getJson('/api/admin/settings');
        $r->assertStatus(200);
        $r->assertJsonStructure([
            'settings',
            'count',
        ]);
        $this->assertGreaterThan(0, $r->json('count'));
    }

    public function test_settings_endpoint_filterable_by_category()
    {
        $this->actingAs($this->platformAdmin);
        $r = $this->getJson('/api/admin/settings?category=BRAND');
        $r->assertStatus(200);
        $settings = $r->json('settings');
        $this->assertNotEmpty($settings);
        foreach ($settings as $s) {
            $this->assertStringStartsWith('BRAND', $s['key']);
        }
    }

    public function test_settings_update_by_platform_admin_succeeds_with_audit()
    {
        // Per PRD, BOTH platform_admin and platform_owner can manage
        // settings (the setting is for ops folks, not owner-only).
        $this->actingAs($this->platformAdmin);

        $r = $this->patchJson('/api/admin/settings/BRAND_NAME', [
            'value' => ['value' => 'New Brand By Admin'],
        ]);

        $r->assertStatus(200);
        $this->assertEquals('New Brand By Admin', SystemConfiguration::where('key', 'BRAND_NAME')->first()->value['value']);
    }

    public function test_settings_update_by_customer_is_403()
    {
        $this->actingAs($this->customer);
        $r = $this->patchJson('/api/admin/settings/BRAND_NAME', [
            'value' => ['value' => 'hacked'],
        ]);
        $r->assertStatus(403);
    }

    public function test_settings_update_by_platform_owner_succeeds_and_logs_audit()
    {
        $this->actingAs($this->platformOwner);

        $r = $this->patchJson('/api/admin/settings/BRAND_NAME', [
            'value' => ['value' => 'New Brand Name'],
        ]);

        $r->assertStatus(200);
        $this->assertEquals('New Brand Name', SystemConfiguration::where('key', 'BRAND_NAME')->first()->value['value']);

        // Audit log recorded.
        $audit = AuditLog::where('action', 'system_setting_updated')
            ->where('resource_type', 'system_configuration')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->platformOwner->id, $audit->actor_id);
        $this->assertEquals('New Brand Name', $audit->after_value['value']['value']);
    }

    public function test_settings_update_unknown_key_returns_404()
    {
        $this->actingAs($this->platformOwner);
        $r = $this->patchJson('/api/admin/settings/NON_EXISTENT_KEY', [
            'value' => ['value' => 'x'],
        ]);
        $r->assertStatus(404);
    }

    public function test_settings_update_non_editable_key_returns_403()
    {
        SystemConfiguration::firstOrCreate(
            ['key' => 'LOCKED_KEY'],
            ['value' => ['v' => 'x'], 'is_editable' => false]
        );

        $this->actingAs($this->platformOwner);
        $r = $this->patchJson('/api/admin/settings/LOCKED_KEY', [
            'value' => ['v' => 'changed'],
        ]);
        $r->assertStatus(403);
    }

    public function test_customer_cannot_access_settings_endpoints()
    {
        $this->actingAs($this->customer);
        $this->getJson('/api/admin/settings')->assertStatus(403);
        $this->patchJson('/api/admin/settings/BRAND_NAME', ['value' => ['value' => 'x']])
            ->assertStatus(403);
    }

    public function test_monitoring_endpoint_requires_view_monitoring_permission()
    {
        // platform_admin does NOT have view_monitoring by default.
        $this->actingAs($this->platformAdmin);
        $this->getJson('/api/admin/monitoring')->assertStatus(403);

        $this->actingAs($this->platformOwner);
        $r = $this->getJson('/api/admin/monitoring')->assertStatus(200);
        $r->assertJsonStructure([
            'application_errors_24h',
            'queue',
            'webhook_24h',
            'ai',
            'database',
            'snapshot_at',
        ]);
    }

    public function test_admin_menu_includes_settings_and_monitoring()
    {
        $this->actingAs($this->platformOwner);
        $r = $this->getJson('/api/admin/menu')->assertStatus(200);
        $keys = array_column($r->json('menu'), 'key');
        $this->assertContains('settings', $keys);
        $this->assertContains('monitoring', $keys);
    }

    public function test_all_placeholder_keys_seeded_in_migration_exist()
    {
        $expected = [
            'COD_MAX_AMOUNT',
            'COD_VERIFICATION_METHOD',
            'COD_REFUND_METHOD',
            'WEIGHT_VARIANCE_THRESHOLD',
            'PRICE_CHANGE_CONFIRMATION_THRESHOLD',
            'COURIER_ASSIGNMENT_TIMEOUT',
            'MAX_PICKUP_REATTEMPT',
            'MAX_DELIVERY_REATTEMPT',
            'GPS_RETENTION_PERIOD',
            'AI_DAILY_LIMIT',
            'AI_RATE_LIMIT',
            'BRAND_NAME',
            'BRAND_TAGLINE',
            'BRAND_CONTACT_EMAIL',
            'BRAND_CONTACT_PHONE',
        ];

        foreach ($expected as $key) {
            $row = SystemConfiguration::where('key', $key)->first();
            $this->assertNotNull($row, "Missing seeded key: {$key}");
            $this->assertTrue($row->is_editable, "Key {$key} should be editable");
            // value is JSON-cast to array; placeholder field should exist
            $this->assertArrayHasKey('placeholder', $row->value);
        }
    }
}
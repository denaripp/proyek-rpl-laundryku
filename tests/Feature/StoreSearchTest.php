<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Review;
use App\Models\User;
use App\Models\Role;
use App\Services\Store\StoreSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->customer = User::factory()->create();
        $role = Role::where('name', 'customer')->first();
        $this->customer->roles()->attach($role);

        $this->service1 = Service::create(['name' => 'Laundry Reguler', 'is_active' => true]);
        $this->service2 = Service::create(['name' => 'Dry Clean', 'is_active' => true]);

        $this->store1 = Store::factory()->create([
            'name' => 'Laundry A',
            'status' => 'approved',
            'is_active' => true,
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'service_radius_km' => 5,
        ]);
        ServicePrice::create([
            'store_id' => $this->store1->id,
            'service_id' => $this->service1->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);

        $this->store2 = Store::factory()->create([
            'name' => 'Laundry B',
            'status' => 'submitted',
            'is_active' => true,
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);
        ServicePrice::create([
            'store_id' => $this->store2->id,
            'service_id' => $this->service1->id,
            'price_per_unit' => 12000,
            'is_active' => true,
        ]);

        $this->store3 = Store::factory()->create([
            'name' => 'Laundry C',
            'status' => 'approved',
            'is_active' => true,
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'service_radius_km' => 5,
        ]);
        ServicePrice::create([
            'store_id' => $this->store3->id,
            'service_id' => $this->service2->id,
            'price_per_unit' => 15000,
            'is_active' => true,
        ]);

        $this->store4 = Store::factory()->create([
            'name' => 'Laundry D',
            'status' => 'suspended',
            'is_active' => false,
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        Review::factory()->create(['store_id' => $this->store1->id, 'rating' => 5, 'is_visible' => true]);
        Review::factory()->create(['store_id' => $this->store1->id, 'rating' => 4, 'is_visible' => true]);
        Review::factory()->create(['store_id' => $this->store3->id, 'rating' => 3, 'is_visible' => true]);
    }

    public function test_search_returns_only_approved_active_stores()
    {
        $service = new StoreSearchService();
        $result = $service->search([]);

        $names = $result->pluck('name')->toArray();
        $this->assertContains('Laundry A', $names);
        $this->assertContains('Laundry C', $names);
        $this->assertNotContains('Laundry B', $names);
        $this->assertNotContains('Laundry D', $names);
    }

    public function test_search_filters_by_service()
    {
        $service = new StoreSearchService();
        $result = $service->search(['service_id' => $this->service1->id]);

        $names = $result->pluck('name')->toArray();
        $this->assertContains('Laundry A', $names);
        $this->assertNotContains('Laundry C', $names);
    }

    public function test_search_filters_by_name()
    {
        $service = new StoreSearchService();
        $result = $service->search(['q' => 'Laundry A']);

        $this->assertCount(1, $result);
        $this->assertEquals('Laundry A', $result->first()->name);
    }

    public function test_search_filters_by_radius()
    {
        $service = new StoreSearchService();
        $result = $service->search([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
            'radius_km' => 3,
        ]);

        $names = $result->pluck('name')->toArray();
        $this->assertNotContains('Laundry B', $names);
        $this->assertNotContains('Laundry A', $names);
        $this->assertNotContains('Laundry C', $names);
    }

    public function test_search_ranking_weights_distance_and_rating()
    {
        $service = new StoreSearchService();
        $storeHighRating = Store::factory()->create([
            'name' => 'Laundry High',
            'status' => 'approved',
            'is_active' => true,
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);
        ServicePrice::create([
            'store_id' => $storeHighRating->id,
            'service_id' => $this->service1->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);
        Review::factory()->create(['store_id' => $storeHighRating->id, 'rating' => 5, 'is_visible' => true]);
        Review::factory()->create(['store_id' => $storeHighRating->id, 'rating' => 5, 'is_visible' => true]);

        $result = $service->search([]);

        $this->assertEquals('Laundry High', $result->first()->name);
        $this->assertEquals('Laundry A', $result->get(1)->name);
    }

    public function test_search_respects_min_rating_config()
    {
        config(['search.min_rating' => 4.0]);
        $service = new StoreSearchService();
        $result = $service->search([]);

        $names = $result->pluck('name')->toArray();
        $this->assertNotContains('Laundry C', $names);
        $this->assertContains('Laundry A', $names);
    }

    public function test_search_does_not_show_suspended_or_pending_stores()
    {
        $service = new StoreSearchService();
        $result = $service->search([]);

        $this->assertFalse($result->contains('name', 'Laundry D'));
        $this->assertFalse($result->contains('name', 'Laundry B'));
    }
}
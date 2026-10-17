<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Services\Maps\GoogleMapsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OrderWithGeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // Set up a store and service for testing
        $this->customer = User::factory()->create();
        $roleCustomer = \App\Models\Role::where('name', 'customer')->first();
        $this->customer->roles()->attach($roleCustomer);

        $this->store = Store::factory()->create([
            'status' => 'approved',
            'is_active' => true,
            'service_radius_km' => 10,
        ]);

        $this->service = Service::create(['name' => 'Laundry', 'is_active' => true]);

        $this->servicePrice = ServicePrice::create([
            'store_id' => $this->store->id,
            'service_id' => $this->service->id,
            'price_per_unit' => 10000,
            'is_active' => true,
        ]);
    }

    /**
     * Test order creation when geocoding fails — should still succeed.
     */
    public function test_order_created_even_when_geocoding_fails()
    {
        // Fake Google Maps API to return error status for geocoding
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'results' => [],
            ], 200),
        ]);

        // Use the actual service (will call fake HTTP)
        $service = new GoogleMapsService();
        $this->app->instance(GoogleMapsService::class, $service);

        // Create order — should not throw, even though geocoding fails
        $orderData = [
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $orderService = app(\App\Services\Order\OrderService::class);
        $order = $orderService->createOrder($orderData, $this->customer);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderStatus::CREATED->value, $order->status);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals($this->store->id, $order->store_id);

        // Order should be created regardless of geocoding outcome; no assertion on HTTP call.
    }

    /**
     * Test that geocoding results are cached.
     */
    public function test_geocoding_caches_results()
    {
        Cache::flush();

        // Set a dummy API key so the service attempts HTTP calls
        config(['services.google_maps.api_key' => 'test']);

        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => ['lat' => -6.2088, 'lng' => 106.8456],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GoogleMapsService();
        $this->app->instance(GoogleMapsService::class, $service);

        // First call should hit API
        $result = $service->geocode('Jakarta');
        $this->assertEquals(['lat' => -6.2088, 'lng' => 106.8456], $result);
        Http::assertSentCount(1);

        // Second call should use cache (no additional HTTP request)
        $result2 = $service->geocode('Jakarta');
        $this->assertEquals(['lat' => -6.2088, 'lng' => 106.8456], $result2);
        Http::assertSentCount(1); // Still only 1 request
    }

    /**
     * Test distance calculation with fallback to Haversine when API fails.
     */
    public function test_distance_fallback_to_haversine_when_api_fails()
    {
        // Fake Distance Matrix API to return error
        Http::fake([
            'maps.googleapis.com/maps/api/distancematrix/json*' => Http::response([
                'status' => 'ZERO_RESULTS',
                'rows' => [],
            ], 200),
        ]);

        $service = new GoogleMapsService();

        $origin = ['lat' => -6.2088, 'lng' => 106.8456];
        $destination = ['lat' => -6.1754, 'lng' => 106.8272];

        $distance = $service->distance($origin, $destination);

        // Should return a number (Haversine fallback)
        $this->assertIsFloat($distance);
        $this->assertGreaterThan(0, $distance);

        // Should be roughly ~5 km (actual distance between those points)
        $this->assertEqualsWithDelta(4.5, $distance, 1.0);
    }

    /**
     * Test that order creation does NOT throw even if geocoding service is completely down (timeout).
     */
    public function test_order_created_when_geocoding_service_times_out()
    {
        // Simulate timeout by faking a slow response that exceeds timeout
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => function () {
                sleep(6); // More than 5 second timeout
                return Http::response(['status' => 'OK'], 200);
            },
        ]);

        $service = new GoogleMapsService();
        $this->app->instance(GoogleMapsService::class, $service);

        $orderData = [
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $orderService = app(\App\Services\Order\OrderService::class);

        // Should not throw despite timeout
        $order = $orderService->createOrder($orderData, $this->customer);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderStatus::CREATED->value, $order->status);
    }

    /**
     * Test that order creation does NOT throw when Google Maps API returns HTTP 500.
     */
    public function test_order_created_when_maps_api_returns_500()
    {
        // Simulate Google Maps API returning HTTP 500 — server-side failure,
        // not a graceful ZERO_RESULTS. The order flow MUST still succeed.
        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'error_message' => 'Internal server error',
                'status' => 'UNKNOWN_ERROR',
            ], 500),
        ]);

        $service = new GoogleMapsService();
        $this->app->instance(GoogleMapsService::class, $service);

        $orderData = [
            'store_id' => $this->store->id,
            'estimated_weight' => 5,
            'estimated_price' => 50000,
            'items' => [
                [
                    'service_id' => $this->service->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $ordersBefore = Order::count();

        $orderService = app(\App\Services\Order\OrderService::class);

        // Should NOT throw despite Maps API 500.
        $order = $orderService->createOrder($orderData, $this->customer);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderStatus::CREATED->value, $order->status);
        $this->assertEquals($this->customer->id, $order->customer_id);
        $this->assertEquals($this->store->id, $order->store_id);
        $this->assertEquals($ordersBefore + 1, Order::count());
    }
}
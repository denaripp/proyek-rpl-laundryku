<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\AdminUser;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\CourierUser;
use App\Models\CustomerAddress;
use App\Models\CustomerUser;
use App\Models\MitraUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class E2ESeeder extends Seeder
{
    public const CUSTOMER_EMAIL = 'customer@e2e.laundryku.test';
    public const MITRA_EMAIL = 'mitra@e2e.laundryku.test';
    public const KURIR_EMAIL = 'kurir@e2e.laundryku.test';
    public const ADMIN_EMAIL = 'admin@e2e.laundryku.test';

    public function run(): void
    {
        if (!app()->environment('local', 'testing')) {
            $this->command?->warn('E2ESeeder hanya boleh dijalankan di environment local atau testing.');
            return;
        }

        // 0. Clean previous E2E orders & tasks to ensure pristine state
        self::cleanOrdersAndTasks();

        // 1. Roles
        $customerRole = Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer', 'category' => 'customer']);
        $storeOwnerRole = Role::whereIn('name', ['store_owner', 'store_admin'])->first() ?? Role::create(['name' => 'store_owner', 'display_name' => 'Store Owner', 'category' => 'store']);
        $courierRole = Role::firstOrCreate(['name' => 'courier'], ['display_name' => 'Courier', 'category' => 'courier']);
        $superAdminRole = Role::whereIn('name', ['platform_owner', 'super_admin'])->first() ?? Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin', 'category' => 'admin']);

        // 2. Customer
        $customer = CustomerUser::withoutGlobalScopes()->where('email', self::CUSTOMER_EMAIL)->first();
        if (!$customer) {
            $customer = new CustomerUser();
            $customer->email = self::CUSTOMER_EMAIL;
            $customer->role = User::ROLE_CUSTOMER;
        }
        $customer->name = 'Customer E2E Cirebon';
        $customer->phone = '081299990001';
        $customer->password = Hash::make('password123');
        $customer->is_active = true;
        $customer->email_verified_at = now();
        $customer->save();

        if (!$customer->roles()->where('roles.id', $customerRole->id)->exists()) {
            $customer->roles()->attach($customerRole->id, ['assigned_at' => now()]);
        }

        // Customer Address in Cirebon
        CustomerAddress::updateOrCreate(
            ['user_id' => $customer->id, 'label' => 'Rumah Cirebon'],
            [
                'recipient_name' => 'Customer E2E Cirebon',
                'phone' => '081299990001',
                'address' => 'Jl. Siliwangi No. 10, Cirebon',
                'city' => 'Cirebon',
                'province' => 'Jawa Barat',
                'postal_code' => '45121',
                'latitude' => -6.705300,
                'longitude' => 108.555500,
                'is_default' => true,
            ]
        );

        // 3. Mitra
        $mitra = MitraUser::withoutGlobalScopes()->where('email', self::MITRA_EMAIL)->first();
        if (!$mitra) {
            $mitra = new MitraUser();
            $mitra->email = self::MITRA_EMAIL;
            $mitra->role = User::ROLE_STORE_ADMIN;
        }
        $mitra->name = 'Mitra E2E Cirebon';
        $mitra->phone = '081299990002';
        $mitra->password = Hash::make('password123');
        $mitra->is_active = true;
        $mitra->email_verified_at = now();
        $mitra->save();

        if (!$mitra->roles()->where('roles.id', $storeOwnerRole->id)->exists()) {
            $mitra->roles()->attach($storeOwnerRole->id, ['assigned_at' => now()]);
        }

        // Store in Cirebon
        $store = Store::updateOrCreate(
            ['slug' => 'laundry-berkah-e2e-cirebon'],
            [
                'owner_user_id' => $mitra->id,
                'name' => 'Laundry Berkah E2E Cirebon',
                'phone' => '081299990002',
                'whatsapp' => '081299990002',
                'address' => 'Jl. Kartini No. 25, Cirebon',
                'city' => 'Cirebon',
                'province' => 'Jawa Barat',
                'postal_code' => '45122',
                'latitude' => -6.708000,
                'longitude' => 108.558000,
                'is_active' => true,
                'status' => 'approved',
                'approved_at' => now(),
                'cod_enabled' => true,
                'qris_enabled' => true,
                'va_enabled' => true,
                'service_radius_km' => 15,
                'commission_rate' => 10.0,
                'minimum_order_amount' => 5000,
                'pickup_fee' => 5000,
                'delivery_fee' => 5000,
            ]
        );

        // Catalog Service
        $service = Service::firstOrCreate(
            ['name' => 'Cuci Komplit Reguler'],
            ['description' => 'Cuci, kering, dan setrika rapi', 'is_active' => true]
        );

        ServicePrice::updateOrCreate(
            ['store_id' => $store->id, 'service_id' => $service->id],
            ['price_per_unit' => 7000, 'unit' => 'kg', 'is_active' => true]
        );

        // 4. Kurir
        $kurir = CourierUser::withoutGlobalScopes()->where('email', self::KURIR_EMAIL)->first();
        if (!$kurir) {
            $kurir = new CourierUser();
            $kurir->email = self::KURIR_EMAIL;
            $kurir->role = User::ROLE_COURIER;
        }
        $kurir->name = 'Kurir E2E Cirebon';
        $kurir->phone = '081299990003';
        $kurir->password = Hash::make('password123');
        $kurir->is_active = true;
        $kurir->email_verified_at = now();
        $kurir->save();

        if (!$kurir->roles()->where('roles.id', $courierRole->id)->exists()) {
            $kurir->roles()->attach($courierRole->id, ['assigned_at' => now()]);
        }

        Courier::updateOrCreate(
            ['user_id' => $kurir->id],
            [
                'store_id' => $store->id,
                'vehicle_type' => 'motorcycle',
                'vehicle_plate' => 'E 1234 E2E',
                'is_verified' => true,
                'is_available' => true,
                'current_latitude' => -6.706000,
                'current_longitude' => 108.556000,
                'last_location_update' => now(),
            ]
        );

        // 5. Super Admin
        $admin = AdminUser::withoutGlobalScopes()->where('email', self::ADMIN_EMAIL)->first();
        if (!$admin) {
            $admin = new AdminUser();
            $admin->email = self::ADMIN_EMAIL;
            $admin->role = User::ROLE_SUPER_ADMIN;
        }
        $admin->name = 'Super Admin E2E';
        $admin->phone = '081299990004';
        $admin->password = Hash::make('admin12345');
        $admin->is_active = true;
        $admin->two_factor_enabled = true;
        $admin->two_factor_confirmed_at = now();
        $admin->google2fa_secret = 'JBSWY3DPEHPK3PXP';
        $admin->save();

        if (!$admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id, ['assigned_at' => now()]);
        }
    }

    /**
     * Clean up previous E2E orders & tasks only.
     */
    public static function cleanOrdersAndTasks(): void
    {
        $emails = [self::CUSTOMER_EMAIL, self::MITRA_EMAIL, self::KURIR_EMAIL, self::ADMIN_EMAIL];
        $userIds = User::withoutGlobalScopes()->whereIn('email', $emails)->pluck('id')->toArray();
        $storeIds = Store::whereIn('owner_user_id', $userIds)->pluck('id')->toArray();
        $orderIds = Order::where(function ($q) use ($userIds, $storeIds) {
            $q->whereIn('customer_id', $userIds)->orWhereIn('store_id', $storeIds);
        })->pluck('id')->toArray();

        if (!empty($orderIds)) {
            OrderStatusHistory::whereIn('order_id', $orderIds)->delete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            CourierTask::whereIn('order_id', $orderIds)->delete();
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->forceDelete();
        }

        $courierProfileIds = Courier::whereIn('user_id', $userIds)->pluck('id')->toArray();
        if (!empty($courierProfileIds)) {
            CourierTask::whereIn('courier_id', $courierProfileIds)->delete();
            Courier::whereIn('id', $courierProfileIds)->update(['is_available' => true]);
        }
    }

    /**
     * Clean up all E2E test data only.
     */
    public static function cleanup(): array
    {
        $emails = [self::CUSTOMER_EMAIL, self::MITRA_EMAIL, self::KURIR_EMAIL, self::ADMIN_EMAIL];
        $e2eUsers = User::withoutGlobalScopes()->where(function ($q) use ($emails) {
            $q->whereIn('email', $emails)->orWhere('email', 'like', '%@e2e.laundryku.test');
        })->get();

        $userIds = $e2eUsers->pluck('id')->toArray();
        $storeIds = Store::whereIn('owner_user_id', $userIds)->pluck('id')->toArray();
        $orderIds = Order::where(function ($q) use ($userIds, $storeIds) {
            $q->whereIn('customer_id', $userIds)->orWhereIn('store_id', $storeIds);
        })->pluck('id')->toArray();

        $countsBefore = [
            'users' => User::withoutGlobalScopes()->count(),
            'stores' => Store::count(),
            'orders' => Order::count(),
            'courier_tasks' => CourierTask::count(),
            'order_status_history' => OrderStatusHistory::count(),
            'customer_addresses' => CustomerAddress::count(),
        ];

        // 1. Delete order related data
        if (!empty($orderIds)) {
            OrderStatusHistory::whereIn('order_id', $orderIds)->delete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            CourierTask::whereIn('order_id', $orderIds)->delete();
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->forceDelete();
        }

        // 2. Delete courier profile & tasks
        $courierProfileIds = Courier::whereIn('user_id', $userIds)->pluck('id')->toArray();
        if (!empty($courierProfileIds)) {
            CourierTask::whereIn('courier_id', $courierProfileIds)->delete();
            Courier::whereIn('id', $courierProfileIds)->delete();
        }

        // 3. Delete store & prices
        if (!empty($storeIds)) {
            ServicePrice::whereIn('store_id', $storeIds)->forceDelete();
            Store::whereIn('id', $storeIds)->forceDelete();
        }

        // 4. Delete addresses
        if (!empty($userIds)) {
            CustomerAddress::whereIn('user_id', $userIds)->forceDelete();
            DB::table('user_roles')->whereIn('user_id', $userIds)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $userIds)->delete();
            User::withoutGlobalScopes()->whereIn('id', $userIds)->forceDelete();
        }

        $countsAfter = [
            'users' => User::withoutGlobalScopes()->count(),
            'stores' => Store::count(),
            'orders' => Order::count(),
            'courier_tasks' => CourierTask::count(),
            'order_status_history' => OrderStatusHistory::count(),
            'customer_addresses' => CustomerAddress::count(),
        ];

        return [
            'before' => $countsBefore,
            'after' => $countsAfter,
        ];
    }
}

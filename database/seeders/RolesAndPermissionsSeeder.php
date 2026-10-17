<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create roles
        $roles = [
            ['name' => 'platform_owner', 'display_name' => 'Platform Owner', 'is_system' => true],
            ['name' => 'platform_admin', 'display_name' => 'Platform Admin', 'is_system' => true],
            ['name' => 'store_owner', 'display_name' => 'Store Owner', 'is_system' => false],
            ['name' => 'store_staff', 'display_name' => 'Store Staff', 'is_system' => false],
            ['name' => 'courier', 'display_name' => 'Courier', 'is_system' => false],
            ['name' => 'customer', 'display_name' => 'Customer', 'is_system' => false],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(['name' => $roleData['name']], $roleData);
        }

        // Create permissions (optional, add as needed)
        $permissions = [
            ['name' => 'manage_platform', 'display_name' => 'Manage Platform', 'category' => 'admin'],
            ['name' => 'manage_stores', 'display_name' => 'Manage Stores', 'category' => 'admin'],
            ['name' => 'manage_orders', 'display_name' => 'Manage Orders', 'category' => 'store'],
            ['name' => 'manage_couriers', 'display_name' => 'Manage Couriers', 'category' => 'store'],
            ['name' => 'approve_store', 'display_name' => 'Approve Store', 'category' => 'admin'],
            ['name' => 'view_courier', 'display_name' => 'View Courier', 'category' => 'admin'],
            ['name' => 'update_courier', 'display_name' => 'Update Courier', 'category' => 'admin'],
            ['name' => 'delete_courier', 'display_name' => 'Delete Courier', 'category' => 'admin'],
            ['name' => 'view_any_courier', 'display_name' => 'View Any Courier', 'category' => 'admin'],
            ['name' => 'update_order', 'display_name' => 'Update Order', 'category' => 'order'],
            ['name' => 'view_payment', 'display_name' => 'View Payment', 'category' => 'payment'],
            ['name' => 'view_any_payment', 'display_name' => 'View Any Payment', 'category' => 'payment'],
            ['name' => 'view_any_refund', 'display_name' => 'View Any Refund', 'category' => 'payment'],
            ['name' => 'create_payment', 'display_name' => 'Create Payment', 'category' => 'payment'],
            ['name' => 'cancel_payment', 'display_name' => 'Cancel Payment', 'category' => 'payment'],
            ['name' => 'moderate_review', 'display_name' => 'Moderate Review', 'category' => 'admin'],
            ['name' => 'view_any_dispute', 'display_name' => 'View Any Dispute', 'category' => 'admin'],
            ['name' => 'review_dispute', 'display_name' => 'Review Dispute', 'category' => 'admin'],
            ['name' => 'resolve_dispute', 'display_name' => 'Resolve Dispute', 'category' => 'admin'],
            ['name' => 'create_dispute', 'display_name' => 'Create Dispute', 'category' => 'dispute'],
            ['name' => 'view_dispute', 'display_name' => 'View Dispute', 'category' => 'dispute'],
            ['name' => 'view_any_settlement', 'display_name' => 'View Any Settlement', 'category' => 'admin'],
            ['name' => 'generate_settlement', 'display_name' => 'Generate Settlement', 'category' => 'admin'],
            ['name' => 'verify_settlement', 'display_name' => 'Verify Settlement', 'category' => 'admin'],
            // Tahap 16
            ['name' => 'view_order', 'display_name' => 'View Order', 'category' => 'order'],
            ['name' => 'view_any_order', 'display_name' => 'View Any Order', 'category' => 'order'],
            ['name' => 'create_order', 'display_name' => 'Create Order', 'category' => 'order'],
            ['name' => 'cancel_order', 'display_name' => 'Cancel Order', 'category' => 'order'],
            ['name' => 'view_store', 'display_name' => 'View Store', 'category' => 'store'],
            ['name' => 'update_store', 'display_name' => 'Update Store', 'category' => 'store'],
            ['name' => 'delete_store', 'display_name' => 'Delete Store', 'category' => 'store'],
            ['name' => 'view_any_store', 'display_name' => 'View Any Store', 'category' => 'store'],
            ['name' => 'view_courier_dashboard', 'display_name' => 'View Courier Dashboard', 'category' => 'courier'],
            ['name' => 'view_admin_dashboard', 'display_name' => 'View Admin Dashboard', 'category' => 'admin'],
            // Tahap 18
            ['name' => 'view_store_dashboard', 'display_name' => 'View Store Dashboard', 'category' => 'store'],
            ['name' => 'view_commission', 'display_name' => 'View Commission', 'category' => 'admin'],
            ['name' => 'view_security', 'display_name' => 'View Security Metrics', 'category' => 'admin'],
            ['name' => 'view_ai_usage', 'display_name' => 'View AI Usage', 'category' => 'admin'],
            // Tahap 21
            ['name' => 'manage_settings', 'display_name' => 'Manage System Settings', 'category' => 'admin'],
            ['name' => 'view_monitoring', 'display_name' => 'View Monitoring', 'category' => 'admin'],
            // Internal user provisioning (admin/owner only — NOT for public self-register).
            ['name' => 'manage_internal_users', 'display_name' => 'Manage Internal Users', 'category' => 'admin'],
            ['name' => 'create_review', 'display_name' => 'Create Review', 'category' => 'review'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(['name' => $permData['name']], $permData);
        }

        // Assign permissions to roles (example)
        $platformOwner = Role::where('name', 'platform_owner')->first();
        $platformAdmin = Role::where('name', 'platform_admin')->first();
        if ($platformOwner) {
            // Sync without timestamps because role_permissions has no updated_at
            DB::table('role_permissions')->insertOrIgnore(
                Permission::all()->map(fn($p) => [
                    'role_id' => $platformOwner->id,
                    'permission_id' => $p->id,
                    'created_at' => now(),
                ])->toArray()
            );
        }
        if ($platformAdmin) {
            // Sync without timestamps
            // Tahap 18: exclude owner-only permissions from platform_admin grant.
            // `manage_platform` is owner-only by PRD (admin is ops, not owner).
            // Tahap 21: also exclude view_monitoring (owner-only by PRD).
            DB::table('role_permissions')->insertOrIgnore(
                Permission::where('category', 'admin')
                    ->whereNotIn('name', [
                        'manage_platform',
                        'view_commission',
                        'view_security',
                        'view_ai_usage',
                        'view_monitoring',
                    ])
                    ->get()
                    ->map(fn($p) => [
                        'role_id' => $platformAdmin->id,
                        'permission_id' => $p->id,
                        'created_at' => now(),
                    ])->toArray()
            );
            // Also give update_order permission
            $updateOrderPerm = Permission::where('name', 'update_order')->first();
            if ($updateOrderPerm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $platformAdmin->id,
                    'permission_id' => $updateOrderPerm->id,
                    'created_at' => now(),
                ]);
            }
            // Give payment permissions
            $paymentPerms = Permission::whereIn('name', ['view_payment', 'view_any_payment', 'view_any_refund', 'create_payment', 'cancel_payment'])->get();
            foreach ($paymentPerms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $platformAdmin->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }
            // Give settlement permissions
            $settlementPerms = Permission::whereIn('name', ['view_any_settlement', 'generate_settlement', 'verify_settlement'])->get();
            foreach ($settlementPerms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $platformAdmin->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }

            // Tahap 16: admin dashboard + view any order/store permissions
            $t16AdminPerms = Permission::whereIn('name', [
                'view_admin_dashboard',
                'view_any_order',
                'view_any_store',
                'view_order',
                'view_store',
            ])->get();
            foreach ($t16AdminPerms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $platformAdmin->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }
            // Tahap 18: explicitly exclude owner-only permissions
            // (view_commission, view_security, view_ai_usage) — those
            // belong to platform_owner only.
        }

        // Tahap 16: customers get create_order, view_order, view_store, create_review
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $customerPerms = Permission::whereIn('name', [
                'create_order',
                'view_order',
                'view_store',
                'create_review',
                'cancel_order',
            ])->get();
            foreach ($customerPerms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $customerRole->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }
        }

        // Tahap 16: couriers get view_courier_dashboard + view_order for assigned orders
        $courierRole = Role::where('name', 'courier')->first();
        if ($courierRole) {
            $courierPerms = Permission::whereIn('name', [
                'view_courier_dashboard',
                'view_order',
                'update_order',
            ])->get();
            foreach ($courierPerms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $courierRole->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }
        }

        // Tahap 16: store owner/staff get update_order, view_order, view_store, update_store, view_any_store
        $storePerms = ['update_order', 'view_order', 'view_store', 'update_store', 'view_store_dashboard'];
        // SECURITY (Tahap 20): 'view_any_store' is PLATFORM-ADMIN / OWNER-only
        // (allows operating on any store). Do NOT grant to regular
        // store_owner/store_staff — that turns every store_owner into a
        // super-admin who can update any store.
        $storeRoles = Role::whereIn('name', ['store_owner', 'store_staff'])->get();
        foreach ($storeRoles as $role) {
            $perms = Permission::whereIn('name', $storePerms)->get();
            foreach ($perms as $perm) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $perm->id,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
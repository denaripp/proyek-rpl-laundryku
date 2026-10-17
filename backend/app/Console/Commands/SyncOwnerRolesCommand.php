<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Store;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncOwnerRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stores:sync-owner-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Idempotently sync store_admin roles for all approved store owners';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Memulai sinkronisasi role store_admin untuk seluruh mitra approved...');

        $storeAdminRole = Role::firstOrCreate(
            ['code' => 'store_admin'],
            ['name' => 'Admin Toko', 'level' => 50]
        );

        $approvedStores = Store::where('approval_status', 'approved')->get();
        $this->line("Ditemukan {$approvedStores->count()} toko berstatus approved.");

        $syncedCount = 0;
        $alreadySyncedCount = 0;

        foreach ($approvedStores as $store) {
            $userRole = UserRole::where('user_id', $store->owner_user_id)
                ->where('role_id', $storeAdminRole->id)
                ->where('store_id', $store->id)
                ->first();

            if (!$userRole || $userRole->status !== 'active') {
                UserRole::updateOrCreate(
                    [
                        'user_id' => $store->owner_user_id,
                        'role_id' => $storeAdminRole->id,
                        'store_id' => $store->id,
                    ],
                    [
                        'status' => 'active',
                    ]
                );

                $this->info("✓ Role store_admin disinkronkan untuk Toko: {$store->name} (Owner ID: {$store->owner_user_id})");
                $syncedCount++;
            } else {
                $alreadySyncedCount++;
            }
        }

        $this->newLine();
        $this->info("Sinkronisasi selesai!");
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total Toko Approved', $approvedStores->count()],
                ['Role Baru Disinkronkan', $syncedCount],
                ['Role Sudah Sesuai Sebelumnya', $alreadySyncedCount],
            ]
        );

        return Command::SUCCESS;
    }
}

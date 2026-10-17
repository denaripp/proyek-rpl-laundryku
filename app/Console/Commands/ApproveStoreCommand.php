<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Console\Command;

class ApproveStoreCommand extends Command
{
    protected $signature = 'store:approve {store_id?} {--admin-email=} {--note=Toko disetujui oleh platform admin}';
    protected $description = 'Approve a submitted store application via StoreService';

    public function handle(StoreService $storeService): int
    {
        $storeId = $this->argument('store_id');
        
        if (!$storeId) {
            $pendingStores = Store::whereIn('status', ['submitted', 'resubmitted', 'under_review', 'draft'])->latest()->get();
            if ($pendingStores->isEmpty()) {
                $this->info('Tidak ada toko yang perlu diapprove.');
                return 0;
            }

            $this->table(
                ['ID', 'Nama Toko', 'Status', 'Owner User ID', 'Dibuat'],
                $pendingStores->map(fn($s) => [$s->id, $s->name, $s->status, $s->owner_user_id, $s->created_at->format('Y-m-d H:i')])
            );

            $storeId = $this->ask('Masukkan ID Toko yang ingin disetujui');
        }

        $store = Store::find($storeId);
        if (!$store) {
            $this->error("Toko dengan ID {$storeId} tidak ditemukan.");
            return 1;
        }

        $adminEmail = $this->option('admin-email') ?? 'admin@laundryku.test';
        $admin = User::where('email', $adminEmail)->first();

        if (!$admin) {
            $admin = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['platform_owner', 'platform_admin']);
            })->first();
        }

        if (!$admin) {
            $this->error("Tidak ditemukan akun Platform Owner / Platform Admin. Jalankan 'php artisan platform:owner' terlebih dahulu.");
            return 1;
        }

        $note = $this->option('note');

        try {
            // If still draft, we can inform the user
            if ($store->status === 'draft') {
                $this->warn("Toko masih berstatus 'draft' (belum disubmit oleh pemilik).");
                if (!$this->confirm('Apakah Anda ingin langsung men-submit dan menyetujui toko ini?', true)) {
                    return 0;
                }
                $storeService->submit($store);
                $store->refresh();
            }

            if (in_array($store->status, ['submitted', 'resubmitted'])) {
                $storeService->startReview($store, $admin);
                $store->refresh();
                $this->info("Status toko beralih ke 'under_review'.");
            }

            if ($store->status === 'under_review') {
                $storeService->approve($store, $admin, $note);
                $store->refresh();
                $this->info("✓ Toko '{$store->name}' (ID: {$store->id}) BERHASIL DISETUJUI!");
                $this->table(
                    ['ID', 'Nama Toko', 'Status', 'Aktif', 'Approved At'],
                    [[$store->id, $store->name, $store->status, $store->is_active ? 'Ya' : 'Tidak', $store->approved_at]]
                );
                return 0;
            }

            $this->error("Status toko saat ini adalah '{$store->status}' (tidak dapat diapprove).");
            return 1;
        } catch (\Throwable $e) {
            $this->error("Gagal menyetujui toko: " . $e->getMessage());
            return 1;
        }
    }
}

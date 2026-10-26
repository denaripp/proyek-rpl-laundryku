<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MitraChangeEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mitra:change-email {old} {new}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Koreksi atau perbarui alamat email akun mitra (store_admin)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $oldEmail = strtolower(trim((string) $this->argument('old')));
        $newEmail = strtolower(trim((string) $this->argument('new')));

        // Validasi format email baru
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("Format email baru '{$newEmail}' tidak valid.");
            return 1;
        }

        if ($oldEmail === $newEmail) {
            $this->warn("Email baru sama dengan email lama. Tidak ada perubahan yang dilakukan.");
            return 0;
        }

        // Cari user dengan email lama dan role store_admin
        $users = User::whereRaw('LOWER(TRIM(email)) = ?', [$oldEmail])
            ->where('role', User::ROLE_STORE_ADMIN)
            ->get();

        if ($users->isEmpty()) {
            $this->error("User dengan email '{$oldEmail}' dan role 'store_admin' tidak ditemukan.");
            return 1;
        }

        if ($users->count() > 1) {
            $this->error("Ditemukan lebih dari 1 user dengan email '{$oldEmail}' dan role 'store_admin'. Operasi dihentikan untuk keamanan.");
            return 1;
        }

        $user = $users->first();

        // Pastikan email baru belum dipakai user lain dengan role yang sama
        $alreadyTaken = User::whereRaw('LOWER(TRIM(email)) = ?', [$newEmail])
            ->where('role', User::ROLE_STORE_ADMIN)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyTaken) {
            $this->error("Email baru '{$newEmail}' sudah terdaftar untuk user dengan role 'store_admin'.");
            return 1;
        }

        // Minta konfirmasi sebelum memperbarui
        $this->table(
            ['ID User', 'Nama', 'Role', 'Email Lama', 'Email Baru'],
            [[$user->id, $user->name, $user->role, $oldEmail, $newEmail]]
        );

        $confirmed = $this->confirm(
            "Apakah Anda yakin ingin memperbarui email user #{$user->id} ({$user->name}) dari '{$oldEmail}' menjadi '{$newEmail}'?",
            false
        );

        if (!$confirmed) {
            $this->info("Operasi dibatalkan.");
            return 0;
        }

        // Update HANYA kolom email tanpa menyentuh password, hash, role, atau kolom lain
        $user->email = $newEmail;
        $user->save();

        $this->info("Email user #{$user->id} diperbarui.");

        return 0;
    }
}

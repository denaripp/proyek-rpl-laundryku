# Panduan Menjalankan E2E Testing (Playwright)

Dokumen ini menjelaskan tata cara menjalankan pengujian *End-to-End* (E2E) alur lengkap pesanan, kurir, pembayaran, dan autentikasi multi-portal di LaundryKu menggunakan Playwright.

---

## 1. Prasyarat Lingkungan

Pastikan layanan dev lokal berjalan normal sebelum memulai pengujian:
1. **Laragon (Windows)**:
   - Apache berjalan melayani `http://laundryku.test`
   - MySQL 8.0 berjalan di port `3306` (user: `root`, tanpa password)
   - PHP CLI: `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
2. **Frontend (Next.js)**:
   - Server frontend berjalan di port `3000` (`http://localhost:3000`):
     ```bash
     cd frontend
     npm run build
     npm run start
     ```
     *(Playwright `playwright.config.ts` telah dikonfigurasi untuk menjalankan `npm run start` secara otomatis jika port 3000 belum aktif).*

---

## 2. Langkah Persiapan Pengujian

Demi keamanan dan isolasi, notifikasi WhatsApp dialihkan ke driver `log` agar tidak mengirim pesan riil melalui gateway Fonnte:

1. **Backup file `.env`**:
   ```powershell
   Copy-Item .env .env.backup-e2e
   ```
2. **Ubah driver WhatsApp ke log sementara**:
   Ubah baris berikut di `.env`:
   ```env
   WHATSAPP_DRIVER=log
   ```
3. **Stabilkan konfigurasi & restart antrean**:
   ```powershell
   composer dev:cache
   ```
   *(Menjalankan `php artisan config:cache && php artisan queue:restart`. Wajib dijalankan setiap kali file `.env` diedit agar worker Apache thread-safe tidak mengalami race condition pembacaan file environment).*

---

## 3. Menjalankan Pengujian E2E

Jalankan suite Playwright dari direktori `frontend`:
```powershell
cd frontend
npm run test:e2e
```

Playwright akan secara otomatis mengeksekusi 11 skenario pengujian berurutan (*serial*):
1. Customer login & membuat pesanan
2. Mitra menugaskan kurir penjemputan
3. Kurir menjemput cucian dan tiba di outlet
4. Mitra menimbang cucian, Customer menyetujui harga final & simulasi bayar (QRIS)
5. Mitra memproses pencucian hingga cucian siap diantar
6. Super Admin menugaskan kurir pengantaran dari dashboard admin
7. Kurir mengantar cucian dan menyelesaikan pesanan
8. Customer membuat order kedua dan mitra menugaskan kembali kurir yang sama (verifikasi *courier re-assignment*)
9. Logout dari 4 role (Customer, Mitra, Kurir, Super Admin) dipastikan selalu berakhir di `/`
10. Cari toko sebagai customer: request `/stores/nearby` dengan koordinat Cirebon tanpa teks default "Bandung"
11. Verifikasi job notifikasi WhatsApp tersimpan di database (`whatsapp_logs`)

---

## 4. Langkah Pemulihan Pasca Pengujian

Setelah pengujian selesai (seluruh skenario *passed*):

1. **Bersihkan data uji E2E**:
   ```powershell
   C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan tinker --execute="dump(Database\Seeders\E2ESeeder::cleanup());"
   ```
   *Catatan: Method ini secara ketat hanya menghapus data berakhiran `@e2e.laundryku.test` dan tidak menyentuh data produksi/seed lain.*

2. **Kembalikan berkas `.env` asli**:
   ```powershell
   Copy-Item .env.backup-e2e .env -Force
   Remove-Item .env.backup-e2e -Force
   ```

3. **Stabilkan konfigurasi & restart antrean kembali**:
   ```powershell
   composer dev:cache
   ```

4. **Verifikasi driver WhatsApp**:
   ```powershell
   C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe artisan tinker --execute="dump(config('services.whatsapp.driver'));"
   ```
   *Harus menghasilkan `"fonnte"`.*

---

## 5. Pengujian Unit & Fitur Backend

Untuk menjalankan test suite Laravel, **selalu gunakan script composer**:
```powershell
composer test
```
*(Script ini memanggil runner `bin/test-runner.php` yang membersihkan config cache sebelum pengujian, menjalankan PHPUnit dengan database uji `laundryku_test` dari `phpunit.xml`, dan menggunakan blok `try ... finally` untuk menjamin pemulihan config cache (`php artisan config:cache`) meskipun test gagal atau terjadi error, dengan tetap mempertahankan exit code test. Selain itu, `tests/TestCase.php` dilengkapi database guard yang secara otomatis menolak eksekusi test jika mendeteksi koneksi mengarah ke database utama `laundryku` sebelum operasi destruktif/RefreshDatabase berjalan).*

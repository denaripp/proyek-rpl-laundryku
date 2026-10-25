<?php

namespace App\Console\Commands;

use App\Mail\OtpMail;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FonnteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestOtpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:test-send 
                            {channel : Kanal pengiriman: email atau whatsapp} 
                            {destination : Alamat email atau nomor WhatsApp tujuan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim satu kode OTP uji nyata untuk memverifikasi gateway Email/WhatsApp';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $channel = strtolower(trim($this->argument('channel')));
        $rawDestination = trim($this->argument('destination'));

        if (!in_array($channel, ['email', 'whatsapp'])) {
            $this->error("Kanal tidak valid. Gunakan 'email' atau 'whatsapp'.");
            return 1;
        }

        // Generate test OTP (numeric 6 digits)
        $testOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if ($channel === 'whatsapp') {
            $normalizedPhone = FonnteService::normalizePhone($rawDestination);
            if (!OtpService::isValidPhone($rawDestination)) {
                $this->error("Format nomor telepon tidak valid. Gunakan format 08xxx atau 628xxx.");
                return 1;
            }

            $masked = FonnteService::maskPhone($normalizedPhone);
            $this->info("Menyiapkan pengiriman OTP WhatsApp ke {$masked}...");

            $token = config('services.fonnte.api_token');
            if (empty($token) || $token === 'your_fonnte_token_here') {
                $this->error("Gagal: FONNTE_API_TOKEN belum diatur di .env");
                return 1;
            }

            try {
                $fonnte = new FonnteService();
                $this->line("Menghubungi Fonnte WhatsApp API Gateway...");
                $res = $fonnte->sendOtp($normalizedPhone, $testOtp, 5);

                $this->newLine();
                $this->info("SUKSES: Pesan OTP uji telah berhasil diproses oleh Fonnte Gateway!");
                $this->line("Tujuan: " . $masked);
                $this->line("Kode OTP uji: " . $testOtp);
                if (!empty($res['data']['process'])) {
                    $this->line("Status proses: " . $res['data']['process']);
                }
                return 0;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("GAGAL: " . $e->getMessage());
                return 1;
            }
        } else {
            // Email
            $normalizedEmail = strtolower($rawDestination);
            if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
                $this->error("Format email tidak valid.");
                return 1;
            }

            $masked = substr($normalizedEmail, 0, 2) . '***@' . (explode('@', $normalizedEmail)[1] ?? 'domain');
            $this->info("Menyiapkan pengiriman OTP Email ke {$masked}...");

            if (!OtpService::isSmtpConfigured()) {
                $this->warn("Catatan: Konfigurasi SMTP (MAIL_HOST, MAIL_USERNAME, dll.) terdeteksi belum lengkap atau menggunakan mailer lokal.");
            }

            try {
                $this->line("Mengirim email OTP via Mailer (" . config('mail.default') . ")...");
                Mail::to($normalizedEmail)->send(new OtpMail($testOtp, 5));

                $this->newLine();
                $this->info("SUKSES: Email OTP uji telah berhasil dikirimkan via SMTP!");
                $this->line("Tujuan: " . $masked);
                $this->line("Kode OTP uji: " . $testOtp);
                return 0;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("GAGAL: " . $e->getMessage());
                return 1;
            }
        }
    }
}

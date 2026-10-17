<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Local common weak passwords list (lowercased, without spaces).
     */
    protected const COMMON_PASSWORDS = [
        'password',
        'password123',
        'password1234',
        'rahasia',
        'rahasia123',
        'admin123',
        'admin1234',
        'administrator',
        'qwerty',
        'qwerty123',
        'qwertyuiop',
        'asdfghjk',
        'asdfghjkl',
        'zxcvbnm',
        '12345678',
        '123456789',
        '1234567890',
        '87654321',
        '0987654321',
        'indonesia',
        'indonesia123',
        'indonesiaraya',
        'bismillah',
        'bismillah123',
        'alhamdulillah',
        'merdeka123',
        'semangat123',
        'sayangku',
        'sayangkamu',
        'iloveyou',
        'iloveyou123',
        'testing123',
        'welcome123',
        'laundry123',
        'laundryku',
        'laundryku123',
        'laundryku2026',
        'laundrybersih',
        'pelanggan123',
    ];

    /**
     * Common keyboard / alphabetical sequences.
     */
    protected const SEQUENCES = [
        '01234567890123456789',
        '98765432109876543210',
        'abcdefghijklmnopqrstuvwxyz',
        'zyxwvutsrqponmlkjihgfedcba',
        'qwertyuiopasdfghjklzxcvbnm',
        'mnbvcxzlkjhgfdsapoiuytrewq',
    ];

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('Password harus berupa teks string.');
            return;
        }

        $length = mb_strlen($value, 'UTF-8');

        // 1. Length constraint: min 8, max 255
        if ($length < 8) {
            $fail('Password minimal terdiri dari 8 karakter.');
            return;
        }

        if ($length > 255) {
            $fail('Password maksimal terdiri dari 255 karakter.');
            return;
        }

        $lowerValue = mb_strtolower($value, 'UTF-8');
        $cleanValue = preg_replace('/\s+/u', '', $lowerValue);

        // 2. Reject if entirely the same character (e.g., 'xxxxxxxxx', '11111111')
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (count(array_unique($chars)) <= 1) {
            $fail('Password terlalu mudah ditebak. Hindari karakter yang seluruhnya sama atau berulang.');
            return;
        }

        // 3. Reject short repeating patterns (e.g. 'abababab', '12341234', 'abcabcabc', 'xyzxyzxyz')
        if (preg_match('/^(.{2,4})\1+$/ui', $cleanValue)) {
            $fail('Password terlalu mudah ditebak. Hindari pola karakter berulang.');
            return;
        }

        // 4. Reject simple sequences of length >= 6 (or entire string if <= 8 chars)
        foreach (self::SEQUENCES as $seq) {
            if ($length <= 12 && str_contains($seq, $cleanValue)) {
                $fail('Password terlalu mudah ditebak. Hindari urutan karakter sederhana seperti deret angka atau keyboard.');
                return;
            }
            // Check sliding window of 6+ chars
            for ($i = 0; $i <= strlen($cleanValue) - 6; $i++) {
                $sub = substr($cleanValue, $i, 6);
                if (str_contains($seq, $sub) && $length <= 10) {
                    $fail('Password terlalu mudah ditebak. Hindari urutan karakter sederhana seperti deret angka atau keyboard.');
                    return;
                }
            }
        }

        // 5. Reject if contains "laundryku"
        if (str_contains($cleanValue, 'laundryku')) {
            $fail('Password tidak boleh mengandung kata "laundryku".');
            return;
        }

        // 6. Reject common local weak passwords
        if (in_array($cleanValue, self::COMMON_PASSWORDS, true) || in_array($lowerValue, self::COMMON_PASSWORDS, true)) {
            $fail('Password terlalu umum dan mudah ditebak. Gunakan kombinasi kata atau frasa yang lebih unik.');
            return;
        }

        // 7. Reject if contains user's name, email local part, or phone number
        $this->validateNoUserDataInPassword($cleanValue, $lowerValue, $fail);
    }

    /**
     * Ensure password does not contain user's name, email local-part, or phone number.
     */
    protected function validateNoUserDataInPassword(string $cleanValue, string $lowerValue, Closure $fail): void
    {
        $name = $this->data['name'] ?? $this->data['full_name'] ?? '';
        $destination = $this->data['destination'] ?? $this->data['email'] ?? $this->data['phone'] ?? $this->data['phone_number'] ?? '';

        // Check user's name (parts with min length >= 3)
        if (is_string($name) && !empty(trim($name))) {
            $nameClean = preg_replace('/[^\p{L}\p{N}]/u', ' ', mb_strtolower(trim($name), 'UTF-8'));
            $nameTokens = array_filter(explode(' ', $nameClean), fn ($t) => mb_strlen($t, 'UTF-8') >= 3);
            foreach ($nameTokens as $token) {
                if (str_contains($cleanValue, $token) || str_contains($lowerValue, $token)) {
                    $fail('Password tidak boleh mengandung nama Anda.');
                    return;
                }
            }
        }

        // Check destination (email or phone)
        if (is_string($destination) && !empty(trim($destination))) {
            $destLower = mb_strtolower(trim($destination), 'UTF-8');

            // If email, check local-part and tokens
            if (str_contains($destLower, '@')) {
                $localPart = explode('@', $destLower)[0];
                $localTokens = array_filter(preg_split('/[._\-+]/', $localPart), fn ($t) => strlen($t) >= 3);
                $localTokens[] = preg_replace('/[^a-z0-9]/', '', $localPart);

                foreach (array_unique($localTokens) as $token) {
                    if (strlen($token) >= 3 && (str_contains($cleanValue, $token) || str_contains($lowerValue, $token))) {
                        $fail('Password tidak boleh mengandung bagian dari alamat email Anda.');
                        return;
                    }
                }
            } else {
                // If phone number, check full digits and any 6+ consecutive digit substring
                $digits = preg_replace('/\D/', '', $destLower);
                $digitLen = strlen($digits);
                if ($digitLen >= 6) {
                    if (str_contains($cleanValue, $digits)) {
                        $fail('Password tidak boleh mengandung nomor telepon Anda.');
                        return;
                    }
                    for ($i = 0; $i <= $digitLen - 6; $i++) {
                        $subDigits = substr($digits, $i, 6);
                        if (str_contains($cleanValue, $subDigits)) {
                            $fail('Password tidak boleh mengandung nomor telepon Anda.');
                            return;
                        }
                    }
                }
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FonnteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OtpRegistrationApiController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Register customer initiating an OTP challenge.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $channel = $request->input('channel');
            $emailInput = $request->input('email');
            $phoneInput = $request->input('phone', $request->input('phone_number'));
            $destinationInput = $request->input('destination');
            $name = $request->input('name', $request->input('full_name'));
            $password = $request->input('password');

            if (empty($name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama lengkap wajib diisi.',
                    'errors' => ['name' => ['Nama lengkap wajib diisi.']],
                ], 422);
            }

            if (empty($channel)) {
                if (!empty($emailInput)) {
                    $channel = 'email';
                } elseif (!empty($phoneInput)) {
                    $channel = 'whatsapp';
                } elseif (!empty($destinationInput)) {
                    $channel = filter_var($destinationInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'whatsapp';
                } else {
                    $channel = 'email';
                }
            }

            if ($channel === 'whatsapp') {
                $rawDestination = $destinationInput ?: $phoneInput;
            } else {
                $rawDestination = $destinationInput ?: ($emailInput ?: $phoneInput);
            }

            if (empty($rawDestination)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alamat email atau nomor telepon wajib diisi.',
                    'errors' => ['destination' => ['Tujuan pendaftaran wajib diisi.']],
                ], 422);
            }

            if (empty($password) || strlen($password) < 8) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password minimal terdiri dari 8 karakter.',
                    'errors' => ['password' => ['Password minimal terdiri dari 8 karakter.']],
                ], 422);
            }

            $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

            if ($channel === 'whatsapp' && !OtpService::isValidPhone($rawDestination)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format nomor WhatsApp tidak valid. Gunakan format 08xxx atau 628xxx.',
                    'errors' => ['destination' => ['Format nomor WhatsApp tidak valid.']],
                ], 422);
            }

            if ($channel === 'email' && !filter_var($normalizedDestination, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format alamat email tidak valid.',
                    'errors' => ['destination' => ['Format alamat email tidak valid.']],
                ], 422);
            }

            $maskedDestination = $channel === 'whatsapp'
                ? FonnteService::maskPhone($normalizedDestination)
                : (substr($normalizedDestination, 0, 2) . '***@' . (explode('@', $normalizedDestination)[1] ?? 'domain'));

            // 1. Rate limiting per IP (max 15/hour)
            $ipKey = 'otp-reg-ip:' . $request->ip();
            if (RateLimiter::tooManyAttempts($ipKey, 15)) {
                $seconds = RateLimiter::availableIn($ipKey);
                return response()->json([
                    'success' => false,
                    'message' => "Terlalu banyak permintaan dari alamat IP ini. Silakan coba lagi dalam {$seconds} detik.",
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Terlalu banyak permintaan OTP.',
                    ],
                ], 429);
            }

            // 2. Rate limiting per destination contact (max 5/hour)
            $destKey = 'otp-reg-dest:' . hash('sha256', $normalizedDestination);
            if (RateLimiter::tooManyAttempts($destKey, 5)) {
                $seconds = RateLimiter::availableIn($destKey);
                return response()->json([
                    'success' => false,
                    'message' => "Batas pengiriman OTP untuk kontak ini telah tercapai. Silakan coba lagi dalam {$seconds} detik.",
                    'error' => [
                        'code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Batas pengiriman OTP untuk kontak ini telah tercapai.',
                    ],
                ], 429);
            }

            Log::info('Registration attempt initiated', [
                'channel' => $channel,
                'target' => $maskedDestination,
            ]);

            // Check if existing user with destination exists
            $existingUser = User::where(function ($query) use ($channel, $normalizedDestination, $emailInput, $phoneInput) {
                if ($channel === 'email') {
                    $query->where('email', $normalizedDestination);
                    if (!empty($phoneInput)) {
                        $query->orWhere('phone', $phoneInput);
                    }
                } else {
                    $query->where('phone', $normalizedDestination);
                    if (!empty($emailInput)) {
                        $query->orWhere('email', $emailInput);
                    }
                }
            })->first();

            if ($existingUser) {
                if ($existingUser->is_active) {
                    Log::warning('Registration attempt for existing active user', ['user_id' => $existingUser->id]);
                    return response()->json([
                        'success' => false,
                        'message' => $channel === 'email'
                            ? 'Alamat email ini sudah terdaftar. Silakan masuk menggunakan kata sandi Anda.'
                            : 'Nomor WhatsApp ini sudah terdaftar. Silakan masuk menggunakan kata sandi Anda.',
                        'error' => [
                            'code' => 'ACCOUNT_ALREADY_EXISTS',
                            'message' => $channel === 'email'
                                ? 'Alamat email ini sudah terdaftar. Silakan masuk menggunakan kata sandi Anda.'
                                : 'Nomor WhatsApp ini sudah terdaftar. Silakan masuk menggunakan kata sandi Anda.',
                        ],
                    ], 422);
                }

                // Check resend cooldown for existing pending user
                $cooldownRemaining = $this->otpService->getResendCooldownRemaining($existingUser, 60);
                if ($cooldownRemaining > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Silakan tunggu {$cooldownRemaining} detik sebelum meminta kode OTP kembali.",
                        'error' => [
                            'code' => 'OTP_COOLDOWN',
                            'message' => "Silakan tunggu {$cooldownRemaining} detik sebelum meminta kode OTP kembali.",
                            'cooldown_remaining' => $cooldownRemaining,
                        ],
                    ], 429);
                }
            }

            // ATOMIC TRANSACTION:
            // Create user (if new) and dispatch OTP challenge inside the same transaction.
            // If OTP dispatch fails (gateway error/timeout), user creation is rolled back completely.
            [$user, $otpResult] = DB::transaction(function () use (
                $existingUser, $name, $channel, $normalizedDestination, $password, $emailInput, $phoneInput
            ) {
                if ($existingUser) {
                    $user = $existingUser;
                } else {
                    $email = $channel === 'email' ? $normalizedDestination : ($emailInput ?: "wa_{$normalizedDestination}@laundryku.test");
                    $phone = $channel === 'whatsapp' ? $normalizedDestination : ($phoneInput ?: null);

                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone ? trim($phone) : null,
                        'password' => Hash::make($password),
                        'role' => User::ROLE_CUSTOMER,
                        'is_active' => false,
                        'email_verified_at' => null,
                    ]);

                    $customerRole = Role::where('name', 'customer')->first();
                    if ($customerRole) {
                        $user->roles()->syncWithoutDetaching([$customerRole->id => ['assigned_at' => now()]]);
                    }
                }

                $result = $this->otpService->generateOtp(
                    user: $user,
                    channel: $channel,
                    destination: $normalizedDestination,
                    purpose: 'registration',
                    metadata: [
                        'pending_name' => $name,
                        'pending_password_hash' => Hash::make($password),
                    ]
                );

                return [$user, $result];
            });

            // Increment rate limiter hits only after successful dispatch
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($destKey, 3600);

            $deliveryStatus = $otpResult['delivery_status'];
            $otpSent = $otpResult['otp_sent'];

            $message = ($deliveryStatus === 'sent')
                ? ($channel === 'whatsapp' ? 'Kode OTP telah dikirimkan ke WhatsApp Anda.' : 'Kode OTP telah dikirimkan ke Email Anda.')
                : (app()->environment('local', 'testing') && $otpResult['debug_otp'] !== null
                    ? 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.'
                    : ($channel === 'whatsapp' ? 'Kode OTP sedang dikirimkan ke WhatsApp Anda.' : 'Kode OTP sedang dikirimkan ke Email Anda.'));

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => User::ROLE_CUSTOMER,
                'action' => 'CUSTOMER_OTP_REGISTER_INITIATED',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user_id' => (string) $user->id,
                    'name' => $user->name,
                    'phone_number' => $normalizedDestination,
                    'destination' => $normalizedDestination,
                    'channel' => $channel,
                    'status' => 'pending_verification',
                    'otp_sent' => $otpSent,
                    'delivery_status' => $deliveryStatus,
                    'debug_otp' => $otpResult['debug_otp'],
                ],
            ], 201);
        } catch (\RuntimeException $e) {
            Log::error('Registration OTP delivery failed', [
                'error' => $e->getMessage(),
                'target' => $maskedDestination ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => [
                    'code' => 'OTP_DELIVERY_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 502);
        } catch (\Throwable $e) {
            Log::error('Registration failed with exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registrasi gagal diproses: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REGISTRATION_FAILED',
                    'message' => 'Registrasi gagal diproses. Silakan coba lagi.',
                ],
            ], 500);
        }
    }

    /**
     * Resend an OTP challenge.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Pengguna tidak ditemukan.',
                ],
            ], 404);
        }

        $defaultChannel = $user->has_real_email ? 'email' : 'whatsapp';
        $channel = $request->input('channel', $defaultChannel);

        if ($channel === 'email') {
            $destination = $user->display_email;
            if (!$destination) {
                if ($user->phone) {
                    $channel = 'whatsapp';
                    $destination = $user->phone;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun ini belum memiliki alamat email yang valid. Silakan gunakan WhatsApp.',
                        'error' => [
                            'code' => 'EMAIL_UNAVAILABLE',
                            'message' => 'Akun ini belum memiliki alamat email yang valid.',
                        ],
                    ], 422);
                }
            }
        } else {
            $destination = $user->phone;
        }

        if (!$destination) {
            return response()->json([
                'success' => false,
                'message' => "Kontak {$channel} tidak terdaftar pada akun ini.",
                'error' => [
                    'code' => 'DESTINATION_UNAVAILABLE',
                    'message' => "Kontak {$channel} tidak terdaftar pada akun ini.",
                ],
            ], 422);
        }

        $normalizedDestination = $this->otpService->normalizeDestination($channel, $destination);

        // Cooldown check (60 seconds)
        $cooldownRemaining = $this->otpService->getResendCooldownRemaining($user, 60);
        if ($cooldownRemaining > 0) {
            return response()->json([
                'success' => false,
                'message' => "Silakan tunggu {$cooldownRemaining} detik sebelum meminta kode OTP kembali.",
                'error' => [
                    'code' => 'OTP_COOLDOWN',
                    'message' => "Silakan tunggu {$cooldownRemaining} detik sebelum meminta kode OTP kembali.",
                    'cooldown_remaining' => $cooldownRemaining,
                ],
            ], 429);
        }

        // Rate limiter per IP and per destination
        $ipKey = 'otp-resend-ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 15)) {
            $seconds = RateLimiter::availableIn($ipKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak permintaan dari alamat IP ini. Silakan coba lagi dalam {$seconds} detik.",
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Terlalu banyak permintaan kirim ulang OTP.',
                ],
            ], 429);
        }

        $destKey = 'otp-resend-dest:' . hash('sha256', $normalizedDestination);
        if (RateLimiter::tooManyAttempts($destKey, 5)) {
            $seconds = RateLimiter::availableIn($destKey);
            return response()->json([
                'success' => false,
                'message' => "Batas pengiriman OTP untuk kontak ini telah tercapai. Silakan coba lagi dalam {$seconds} detik.",
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Batas pengiriman OTP untuk kontak ini telah tercapai.',
                ],
            ], 429);
        }

        try {
            $otpResult = $this->otpService->generateOtp($user, $channel, $normalizedDestination);
        } catch (\RuntimeException $e) {
            Log::error('Resend OTP failed: ' . $e->getMessage(), ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => [
                    'code' => 'OTP_DELIVERY_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], 502);
        }

        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($destKey, 3600);

        $deliveryStatus = $otpResult['delivery_status'];
        $otpSent = $otpResult['otp_sent'];
        $message = ($deliveryStatus === 'sent')
            ? 'Kode OTP baru berhasil dikirimkan.'
            : (app()->environment('local', 'testing') && $otpResult['debug_otp'] !== null
                ? 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.'
                : 'Kode OTP baru sedang dikirimkan ke kontak Anda.');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user_id' => (string) $user->id,
                'channel' => $channel,
                'destination' => $normalizedDestination,
                'otp_sent' => $otpSent,
                'delivery_status' => $deliveryStatus,
                'debug_otp' => $otpResult['debug_otp'],
            ],
        ]);
    }

    /**
     * Verify OTP code and activate user.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $user = $this->resolveUser($request);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan.',
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'Pengguna tidak ditemukan.',
                    ],
                ], 404);
            }

            $otp = (string) $request->input('otp', $request->input('otp_code'));
            $result = $this->otpService->verifyOtp($user, $otp, $request->input('channel'));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => [
                        'code' => $result['code'] ?? 'OTP_INVALID',
                        'message' => $result['message'],
                    ],
                ], 400);
            }

            // Generate Sanctum token
            $user->tokens()->where('name', 'customer_token')->delete();
            $token = $user->createToken('customer_token', ['role:' . $user->role])->plainTextToken;

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'CUSTOMER_OTP_VERIFIED_SUCCESS',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $roles = $user->roles->pluck('name')->all();
            if (empty($roles)) {
                $roles = [$user->role];
            }

            return response()->json([
                'success' => true,
                'message' => 'Verifikasi OTP berhasil. Akun Anda telah aktif.',
                'data' => [
                    'user' => [
                        'id' => (string) $user->id,
                        'name' => $user->name,
                        'email' => $user->display_email,
                        'display_email' => $user->display_email,
                        'has_real_email' => $user->has_real_email,
                        'phone' => $user->phone,
                        'display_phone' => $user->display_phone,
                        'status' => 'active',
                        'verified_at' => $user->email_verified_at?->toIso8601String(),
                        'roles' => $roles,
                        'active_mode' => $user->role,
                        'available_modes' => [$user->role],
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('OTP verification failed with exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verifikasi OTP gagal diproses. Silakan coba lagi.',
                'error' => [
                    'code' => 'OTP_VERIFICATION_FAILED',
                    'message' => 'Verifikasi OTP gagal diproses. Silakan coba lagi.',
                ],
            ], 500);
        }
    }

    /**
     * Direct login with password.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $channel = $request->input('channel');
            $emailInput = $request->input('email');
            $phoneInput = $request->input('phone', $request->input('phone_number'));
            $destinationInput = $request->input('destination');
            $password = (string) $request->input('password');

            if (empty($channel)) {
                if (!empty($emailInput)) {
                    $channel = 'email';
                } elseif (!empty($phoneInput)) {
                    $channel = 'whatsapp';
                } elseif (!empty($destinationInput)) {
                    $channel = filter_var($destinationInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'whatsapp';
                } else {
                    $channel = 'email';
                }
            }

            $rawDestination = $destinationInput ?: ($channel === 'whatsapp' ? $phoneInput : ($emailInput ?: $phoneInput));

            if (empty($rawDestination) || empty($password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kontak atau password wajib diisi.',
                    'error' => [
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => 'Kontak atau password salah.',
                    ],
                ], 422);
            }

            $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

            $user = User::where(function ($query) use ($normalizedDestination, $rawDestination) {
                $query->where('email', $normalizedDestination)
                    ->orWhere('phone', $normalizedDestination)
                    ->orWhere('email', $rawDestination)
                    ->orWhere('phone', $rawDestination);
            })->first();

            if (!$user || !Hash::check($password, $user->password)) {
                AuditLog::create([
                    'actor_id' => $user?->id,
                    'actor_role' => $user?->role ?? 'customer',
                    'action' => 'LOGIN_FAILED',
                    'resource_type' => 'auth',
                    'resource_id' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'after_value' => json_encode(['destination' => $normalizedDestination]),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Kontak atau password salah.',
                    'error' => [
                        'code' => 'INVALID_CREDENTIALS',
                        'message' => 'Kontak atau password salah.',
                    ],
                ], 401);
            }

            if (!$user->is_active) {
                if (is_null($user->email_verified_at) && is_null($user->phone_verified_at)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun belum diverifikasi. Silakan lakukan verifikasi OTP registrasi terlebih dahulu.',
                        'error' => [
                            'code' => 'ACCOUNT_PENDING_VERIFICATION',
                            'message' => 'Akun belum diverifikasi.',
                        ],
                        'errors' => [
                            'user_id' => (string) $user->id,
                        ],
                    ], 403);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda dinonaktifkan. Silakan hubungi pusat bantuan.',
                    'error' => [
                        'code' => 'ACCOUNT_INACTIVE',
                        'message' => 'Akun Anda dinonaktifkan.',
                    ],
                ], 403);
            }

            $token = $user->createToken('auth_token', ['role:' . $user->role])->plainTextToken;

            AuditLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'action' => 'LOGIN_SUCCESS',
                'resource_type' => 'user',
                'resource_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $roles = $user->roles->pluck('name')->all();
            if (empty($roles)) {
                $roles = [$user->role];
            }

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => [
                    'user' => [
                        'id' => (string) $user->id,
                        'name' => $user->name,
                        'email' => $user->display_email,
                        'display_email' => $user->display_email,
                        'has_real_email' => $user->has_real_email,
                        'phone' => $user->phone,
                        'display_phone' => $user->display_phone,
                        'status' => 'active',
                        'verified_at' => $user->email_verified_at?->toIso8601String(),
                        'roles' => $roles,
                        'active_mode' => $user->role,
                        'available_modes' => [$user->role],
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Login failed with exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login gagal diproses. Silakan coba lagi.',
                'error' => [
                    'code' => 'LOGIN_FAILED',
                    'message' => 'Login gagal diproses. Silakan coba lagi.',
                ],
            ], 500);
        }
    }

    /**
     * Get current user session.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $roles = $user->roles->pluck('name')->all();
        if (empty($roles)) {
            $roles = [$user->role];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->display_email,
                'display_email' => $user->display_email,
                'has_real_email' => $user->has_real_email,
                'phone' => $user->phone,
                'display_phone' => $user->display_phone,
                'status' => $user->is_active ? 'active' : 'inactive',
                'verified_at' => $user->email_verified_at?->toIso8601String(),
                'roles' => $roles,
                'active_mode' => $user->role,
                'available_modes' => [$user->role],
            ],
        ]);
    }

    /**
     * Logout and revoke access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    protected function resolveUser(Request $request): ?User
    {
        if ($userId = $request->input('user_id')) {
            return User::find($userId);
        }

        $destination = $request->input('destination', $request->input('phone_number', $request->input('phone', $request->input('email'))));
        if ($destination) {
            $channel = $request->input('channel', filter_var($destination, FILTER_VALIDATE_EMAIL) ? 'email' : 'whatsapp');
            $normalized = $this->otpService->normalizeDestination($channel, $destination);

            return User::where('email', $normalized)
                ->orWhere('phone', $normalized)
                ->first();
        }

        return null;
    }
}

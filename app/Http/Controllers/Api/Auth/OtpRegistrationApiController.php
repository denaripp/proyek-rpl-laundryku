<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
            $channel = $request->input('channel', 'email');
            $rawDestination = $request->input('destination', $request->input('phone_number', $request->input('phone', $request->input('email'))));
            $name = $request->input('name', $request->input('full_name'));
            $password = $request->input('password');

            if (empty($name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama lengkap wajib diisi.',
                    'errors' => ['name' => ['Nama lengkap wajib diisi.']],
                ], 422);
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
                ? FontneService::maskPhone($normalizedDestination)
                : (substr($normalizedDestination, 0, 2) . '***@' . (explode('@', $normalizedDestination)[1] ?? 'domain'));

            Log::info('Registration attempt initiated', [
                'channel' => $channel,
                'target' => $maskedDestination,
            ]);

            // Check if existing user with destination exists
            $existingUser = User::where(function ($query) use ($channel, $normalizedDestination) {
                if ($channel === 'email') {
                    $query->where('email', $normalizedDestination);
                } else {
                    $query->where('phone', $normalizedDestination);
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

                $user = $existingUser;
            } else {
                $user = DB::transaction(function () use ($name, $channel, $normalizedDestination, $password) {
                    $createdUser = User::create([
                        'name' => $name,
                        'email' => $channel === 'email' ? $normalizedDestination : null,
                        'phone' => $channel === 'whatsapp' ? $normalizedDestination : null,
                        'password' => Hash::make($password),
                        'role' => User::ROLE_CUSTOMER,
                        'is_active' => false,
                        'email_verified_at' => null,
                    ]);

                    $customerRole = Role::where('name', 'customer')->first();
                    if ($customerRole) {
                        $createdUser->roles()->syncWithoutDetaching([$customerRole->id => ['assigned_at' => now()]]);
                    }

                    return $createdUser;
                });
            }

            // Generate and dispatch OTP challenge
            $otpResult = $this->otpService->generateOtp(
                user: $user,
                channel: $channel,
                destination: $normalizedDestination,
                purpose: 'registration',
                metadata: [
                    'pending_name' => $name,
                    'pending_password_hash' => Hash::make($password),
                ]
            );

            $deliveryStatus = $otpResult['delivery_status'];
            $otpSent = $otpResult['otp_sent'];

            $message = ($deliveryStatus === 'sent')
                ? ($channel === 'whatsapp' ? 'Kode OTP telah dikirimkan ke WhatsApp Anda.' : 'Kode OTP telah dikirimkan ke Email Anda.')
                : 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.';

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
        } catch (\Throwable $e) {
            Log::error('Registration failed with exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registrasi gagal diproses. Silakan coba lagi.',
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

        $channel = $request->input('channel', $user->email ? 'email' : 'whatsapp');
        $destination = $channel === 'email' ? $user->email : $user->phone;

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

        $otpResult = $this->otpService->generateOtp($user, $channel, $destination);

        $deliveryStatus = $otpResult['delivery_status'];
        $otpSent = $otpResult['otp_sent'];
        $message = ($deliveryStatus === 'sent')
            ? 'Kode OTP baru berhasil dikirimkan.'
            : 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user_id' => (string) $user->id,
                'channel' => $channel,
                'destination' => $destination,
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
                    'email' => $user->email,
                    'phone' => $user->phone,
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
    }

    /**
     * Direct login with password.
     */
    public function login(Request $request): JsonResponse
    {
        $channel = $request->input('channel', 'email');
        $rawDestination = $request->input('destination', $request->input('email', $request->input('phone')));
        $password = (string) $request->input('password');

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

        $user = User::where(function ($query) use ($channel, $normalizedDestination) {
            if ($channel === 'email') {
                $query->where('email', $normalizedDestination);
            } else {
                $query->where('phone', $normalizedDestination);
            }
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
        $user->update(['last_login_at' => now()]);

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
                    'email' => $user->email,
                    'phone' => $user->phone,
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
                'email' => $user->email,
                'phone' => $user->phone,
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

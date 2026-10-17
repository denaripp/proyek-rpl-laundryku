<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserRole;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FontneService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * Register a new customer via email or whatsapp (OTP-only, no password).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $channel = $request->input('channel', 'whatsapp');
            $rawDestination = $request->input('destination', $request->input('phone_number', $request->input('phone', $request->input('email'))));
            $name = $request->input('name', $request->input('full_name'));
            $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

            $maskedDestination = $channel === 'whatsapp'
                ? FontneService::maskPhone($normalizedDestination)
                : (substr($normalizedDestination, 0, 2) . '***@' . (explode('@', $normalizedDestination)[1] ?? 'domain'));

            Log::info('Registration attempt initiated', [
                'channel' => $channel,
                'target' => $maskedDestination,
            ]);

            $password = $request->input('password');

            // Check if an account with this destination already exists
            $existingUser = User::where(function ($query) use ($channel, $normalizedDestination) {
                if ($channel === 'email') {
                    $query->where('email', $normalizedDestination);
                } else {
                    $query->where('phone', $normalizedDestination);
                }
            })->first();

            if ($existingUser) {
                if ($existingUser->status === 'active') {
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

                if (in_array($existingUser->status, ['suspended', 'banned'])) {
                    Log::warning('Registration attempt for suspended/banned user', ['user_id' => $existingUser->id, 'status' => $existingUser->status]);
                    return response()->json([
                        'success' => false,
                        'message' => "Akun Anda sedang di-{$existingUser->status}. Silakan hubungi pusat bantuan.",
                        'error' => [
                            'code' => 'ACCOUNT_' . strtoupper($existingUser->status),
                            'message' => "Akun Anda sedang di-{$existingUser->status}. Silakan hubungi pusat bantuan.",
                        ],
                    ], 403);
                }

                // If user is pending_verification, DO NOT mutate user model directly!
                // Instead, the new name and password hash are bound to the new OTP challenge metadata.
                $user = $existingUser;
            } else {
                // Create pending user record without finalized password
                $user = DB::transaction(function () use ($name, $channel, $normalizedDestination) {
                    $createdUser = User::create([
                        'name' => $name,
                        'email' => $channel === 'email' ? $normalizedDestination : null,
                        'phone' => $channel === 'whatsapp' ? $normalizedDestination : null,
                        'password' => null, // Finalized upon OTP verification from challenge metadata
                        'status' => 'pending_verification',
                        'verified_at' => null,
                    ]);

                    // Assign default role: Customer (role id 1)
                    $customerRole = Role::where('code', 'customer')->first();
                    $roleId = $customerRole ? $customerRole->id : 1;

                    UserRole::create([
                        'user_id' => $createdUser->id,
                        'role_id' => $roleId,
                        'store_id' => null,
                        'status' => 'active',
                    ]);

                    // Create initial profile
                    UserProfile::create([
                        'user_id' => $createdUser->id,
                    ]);

                    return $createdUser;
                });
            }

            // Generate and dispatch OTP challenge containing pending credentials metadata
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

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'phone_number' => $normalizedDestination,
                    'destination' => $normalizedDestination,
                    'channel' => $channel,
                    'status' => $user->status,
                    'otp_sent' => $otpSent,
                    'delivery_status' => $deliveryStatus,
                    'debug_otp' => $otpResult['debug_otp'],
                ],
                'meta' => [
                    'message' => $message,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Registration validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . $e->validator->errors()->first(),
                'errors' => $e->errors(),
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->validator->errors()->first(),
                    'details' => $e->errors(),
                ],
            ], 422);

        } catch (\Exception $e) {
            Log::error('Registration failed with exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Registrasi gagal: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REGISTRATION_FAILED',
                    'message' => 'Registrasi gagal: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Alias for register method (Fase 15B / 15C).
     */
    public function storeRegister(RegisterRequest $request): JsonResponse
    {
        return $this->register($request);
    }

    /**
     * Login using email/WhatsApp identifier + password (Direct login, no OTP).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $channel = $request->input('channel');
        $rawDestination = $request->input('destination');
        $password = $request->input('password');
        $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

        $user = User::where(function ($query) use ($channel, $normalizedDestination) {
            if ($channel === 'email') {
                $query->where('email', $normalizedDestination);
            } else {
                $query->where('phone', $normalizedDestination);
            }
        })->first();

        // Reject suspended or banned users
        if ($user && in_array($user->status, ['suspended', 'banned'])) {
            return $this->errorResponse(
                code: 'ACCOUNT_' . strtoupper($user->status),
                message: "Akun Anda sedang di-{$user->status}. Silakan hubungi pusat bantuan.",
                status: 403
            );
        }

        // Reject pending verification accounts
        if ($user && $user->status === 'pending_verification') {
            return $this->errorResponse(
                code: 'ACCOUNT_PENDING_VERIFICATION',
                message: 'Akun belum diverifikasi.',
                status: 403
            );
        }

        // Handle legacy active account without password (no silent OTP generation)
        if ($user && empty($user->password) && $user->status === 'active') {
            return $this->errorResponse(
                code: 'PASSWORD_NOT_SET',
                message: 'Akun Anda belum memiliki kata sandi. Silakan gunakan menu atur kata sandi.',
                status: 422
            );
        }

        // Check password validity
        if (!$user || empty($user->password) || !\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            return $this->errorResponse(
                code: 'INVALID_CREDENTIALS',
                message: 'Kontak atau password salah.',
                status: 401
            );
        }

        // TODO: [2FA] Saat 2FA diimplementasikan, periksa apakah user (terutama role super_admin / store_admin)
        // telah mengaktifkan 2FA. Jika aktif, kirimkan tantangan 2FA (TOTP/WhatsApp/Email) dan kembalikan
        // response 2FA_REQUIRED dengan temporary session token alih-alih langsung menerbitkan full personal access token.

        // Issue Sanctum token for active user with valid password — NO OTP!
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->loadMissing(['roles', 'profile']);

        return $this->successResponse(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'verified_at' => $user->verified_at?->toIso8601String(),
                    'avatar_url' => $user->profile?->avatar_url,
                    'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
                    'profile' => $user->profile,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            meta: [
                'message' => 'Login berhasil. Selamat datang kembali di LaundryKu!',
            ]
        );
    }

    /**
     * Resend an OTP challenge to a user.
     */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return $this->errorResponse(
                code: 'USER_NOT_FOUND',
                message: 'Pengguna dengan tujuan tersebut tidak ditemukan.',
                status: 404
            );
        }

        if (in_array($user->status, ['suspended', 'banned'])) {
            return $this->errorResponse(
                code: 'ACCOUNT_' . strtoupper($user->status),
                message: "Akun Anda sedang di-{$user->status}. Silakan hubungi pusat bantuan.",
                status: 403
            );
        }

        $channel = $request->input('channel', $user->email ? 'email' : 'whatsapp');
        $destination = $channel === 'email' ? $user->email : $user->phone;

        if (!$destination) {
            return $this->errorResponse(
                code: 'DESTINATION_UNAVAILABLE',
                message: "Tujuan kontak $channel tidak terdaftar pada akun ini.",
                status: 422
            );
        }

        $otpResult = $this->otpService->generateOtp($user, $channel, $destination);

        $deliveryStatus = $otpResult['delivery_status'];
        $otpSent = $otpResult['otp_sent'];
        $message = ($deliveryStatus === 'sent')
            ? 'Kode OTP baru berhasil dikirimkan.'
            : 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.';

        return $this->successResponse(
            data: [
                'user_id' => $user->id,
                'channel' => $channel,
                'destination' => $destination,
                'otp_sent' => $otpSent,
                'delivery_status' => $deliveryStatus,
                'debug_otp' => $otpResult['debug_otp'],
            ],
            meta: [
                'message' => $message,
            ]
        );
    }

    /**
     * Verify an OTP challenge and issue Sanctum token (activates user if pending).
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return $this->errorResponse(
                code: 'USER_NOT_FOUND',
                message: 'Pengguna tidak ditemukan.',
                status: 404
            );
        }

        if (in_array($user->status, ['suspended', 'banned'])) {
            return $this->errorResponse(
                code: 'ACCOUNT_' . strtoupper($user->status),
                message: "Akun Anda sedang di-{$user->status}. Silakan hubungi pusat bantuan.",
                status: 403
            );
        }

        $otp = $request->input('otp', $request->input('otp_code'));
        $result = $this->otpService->verifyOtp($user, $otp, $request->input('channel'));

        if (!$result['success']) {
            return $this->errorResponse(
                code: $result['code'] ?? 'OTP_INVALID',
                message: $result['message'],
                status: 400
            );
        }

        // Generate Sanctum Bearer token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Eager load roles & profile if needed
        $user->loadMissing(['roles', 'profile']);

        return $this->successResponse(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'verified_at' => $user->verified_at?->toIso8601String(),
                    'avatar_url' => $user->profile?->avatar_url,
                    'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
                    'profile' => $user->profile,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            meta: [
                'message' => 'Autentikasi berhasil. Anda telah masuk ke dalam sistem.',
            ]
        );
    }

    /**
     * Logout and revoke current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(
            data: [
                'message' => 'Logout berhasil. Sesi telah diakhiri.',
            ],
            meta: [
                'message' => 'Logout berhasil.',
            ]
        );
    }

    /**
     * Get current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $stores = \App\Models\Store::where('owner_user_id', $user->id)
            ->select(['id', 'name', 'address_text', 'approval_status', 'operational_status', 'rejection_reason', 'approved_at', 'created_at'])
            ->orderBy('approved_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($store) => [
                'id' => $store->id,
                'name' => $store->name,
                'address_text' => $store->address_text,
                'approval_status' => $store->approval_status,
                'operational_status' => $store->operational_status,
                'rejection_reason' => $store->rejection_reason,
                'approved_at' => $store->approved_at?->toIso8601String(),
                'created_at' => $store->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $activeRoles = $user->roles()
            ->wherePivot('status', 'active')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'store_id' => $r->pivot->store_id,
            ])
            ->values()
            ->all();

        $roleCodes = collect($activeRoles)->pluck('code')->unique()->values()->all();

        $availableModes = $user->getAvailableModes();
        $currentMode = $user->active_mode;

        // Default mode resolution: courier if active assignment and no approved store; store if approved store; otherwise follow saved or fallback
        if (!$currentMode || !in_array($currentMode, $availableModes)) {
            if ($user->hasActiveCourierAssignment() && !$user->hasApprovedStore()) {
                $currentMode = 'courier';
            } elseif ($user->hasApprovedStore() && !$user->hasActiveCourierAssignment()) {
                $currentMode = 'store';
            } else {
                $currentMode = 'customer';
            }
            $user->active_mode = $currentMode;
            $user->save();
        }

        return $this->successResponse(
            data: [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'active_mode' => $user->active_mode,
                'available_modes' => $availableModes,
                'verified_at' => $user->verified_at?->toIso8601String(),
                'avatar_url' => $user->profile?->avatar_url,
                'roles' => $roleCodes,
                'active_roles' => $activeRoles,
                'stores' => $stores,
                'profile' => $user->profile,
            ],
            meta: []
        );
    }

    /**
     * Switch user operational active mode (customer | store | courier).
     */
    public function switchMode(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:customer,store,courier'],
        ], [
            'mode.in' => 'Mode harus salah satu dari: customer, store, atau courier.',
        ]);

        $targetMode = $validated['mode'];
        $availableModes = $user->getAvailableModes();

        if ($targetMode === 'courier' && !$user->hasActiveCourierAssignment()) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Akun Anda tidak memiliki penugasan kurir aktif.',
                null,
                403
            );
        }

        if ($targetMode === 'store' && !$user->hasApprovedStore() && !$user->hasRole('store_admin')) {
            return $this->errorResponse(
                'FORBIDDEN',
                'Akun Anda belum memiliki toko laundry yang disetujui.',
                null,
                403
            );
        }

        $oldMode = $user->active_mode ?: 'customer';
        $user->active_mode = $targetMode;
        $user->save();

        \App\Models\AuditLog::record(
            actor: $user,
            action: 'USER_MODE_SWITCHED',
            entityType: 'users',
            entityId: $user->id,
            metadata: [
                'from' => $oldMode,
                'to' => $targetMode,
            ],
            ip: $request->ip()
        );

        return $this->successResponse(
            data: [
                'id' => $user->id,
                'name' => $user->name,
                'active_mode' => $user->active_mode,
                'available_modes' => $user->getAvailableModes(),
            ],
            meta: [
                'message' => "Mode berhasil diubah ke {$targetMode}.",
            ]
        );
    }

    /**
     * Request OTP to setup password for legacy active accounts without password.
     * Generic response prevents enumeration / privacy leakage.
     */
    public function requestPasswordSetup(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['required', 'in:email,whatsapp'],
            'destination' => ['required', 'string'],
        ]);

        $channel = $request->input('channel');
        $rawDestination = $request->input('destination');
        $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

        $user = User::where(function ($query) use ($channel, $normalizedDestination) {
            if ($channel === 'email') {
                $query->where('email', $normalizedDestination);
            } else {
                $query->where('phone', $normalizedDestination);
            }
        })->first();

        $debugOtp = null;

        // Only send OTP if user is active and has NO password set
        if ($user && $user->status === 'active' && empty($user->password)) {
            $otpResult = $this->otpService->generateOtp(
                user: $user,
                channel: $channel,
                destination: $normalizedDestination,
                purpose: 'set_password'
            );
            $debugOtp = $otpResult['debug_otp'];
        }

        return $this->successResponse(
            data: [
                'destination' => $normalizedDestination,
                'channel' => $channel,
                'debug_otp' => $debugOtp,
            ],
            meta: [
                'message' => 'Jika kontak terdaftar dan belum memiliki kata sandi, kode OTP verifikasi telah dikirimkan.',
            ]
        );
    }

    /**
     * Complete password setup for legacy active account with valid OTP and strong password.
     */
    public function setupPassword(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['required', 'in:email,whatsapp'],
            'destination' => ['required', 'string'],
            'otp' => ['required', 'string', 'min:6', 'max:6'],
            'password' => ['required', 'string', 'confirmed', new \App\Rules\StrongPassword()],
        ], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.min' => 'Kode OTP harus terdiri dari 6 digit.',
            'otp.max' => 'Kode OTP harus terdiri dari 6 digit.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $channel = $request->input('channel');
        $rawDestination = $request->input('destination');
        $password = $request->input('password');
        $otp = $request->input('otp');
        $normalizedDestination = $this->otpService->normalizeDestination($channel, $rawDestination);

        $user = User::where(function ($query) use ($channel, $normalizedDestination) {
            if ($channel === 'email') {
                $query->where('email', $normalizedDestination);
            } else {
                $query->where('phone', $normalizedDestination);
            }
        })->first();

        // Reject if user not found, not active, or already has password set
        if (!$user || $user->status !== 'active' || !empty($user->password)) {
            return $this->errorResponse(
                code: 'INVALID_REQUEST',
                message: 'Permintaan tidak valid atau akun sudah memiliki kata sandi.',
                status: 422
            );
        }

        $result = $this->otpService->verifyOtp($user, $otp, $channel, 'set_password');

        if (!$result['success']) {
            return $this->errorResponse(
                code: $result['code'] ?? 'OTP_INVALID',
                message: $result['message'],
                status: 400
            );
        }

        // Set password for the user
        $user->password = $password;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;
        $user->loadMissing(['roles', 'profile']);

        return $this->successResponse(
            data: [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'verified_at' => $user->verified_at?->toIso8601String(),
                    'avatar_url' => $user->profile?->avatar_url,
                    'roles' => $user->roles->filter(fn ($r) => ($r->pivot->status ?? null) === 'active')->pluck('code')->values(),
                    'profile' => $user->profile,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            meta: [
                'message' => 'Kata sandi berhasil diatur. Anda telah masuk ke dalam sistem.',
            ]
        );
    }

    /**
     * Helper to resolve user from request parameters.
     */
    protected function resolveUser(Request $request): ?User
    {
        if ($request->filled('user_id')) {
            return User::find($request->input('user_id'));
        }

        if ($request->filled('destination')) {
            $channel = $request->input('channel', 'email');
            $normalized = $this->otpService->normalizeDestination($channel, $request->input('destination'));

            return User::where(function ($query) use ($channel, $normalized) {
                if ($channel === 'email') {
                    $query->where('email', $normalized);
                } else {
                    $query->where('phone', $normalized);
                }
            })->first();
        }

        return null;
    }
}


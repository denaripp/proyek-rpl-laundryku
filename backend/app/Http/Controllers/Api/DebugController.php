<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Services\WhatsAppGateway\FontneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    /**
     * Test Fonnte connection (restricted to local environment).
     */
    public function testFonnte(Request $request): JsonResponse
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $token = config('services.fonnte.api_token');
        $baseUrl = config('services.fonnte.base_url');

        Log::info('DebugController::testFonnte called in local environment');

        if (empty($token) || $token === 'your_fonnte_token_here') {
            return response()->json([
                'success' => false,
                'error' => 'FONNTE_API_TOKEN is not configured in .env',
            ], 400);
        }

        $phone = $request->input('phone', '08315068475');
        $otp = $request->input('code', '123456');

        try {
            $fonnte = new FontneService();
            $result = $fonnte->sendOtp($phone, $otp);

            return response()->json([
                'success' => true,
                'message' => 'Fonnte OTP sent successfully',
                'result' => [
                    'status' => $result['status'] ?? true,
                    'target_masked' => FontneService::maskPhone($phone),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test OTP generation (restricted to local environment).
     */
    public function testOtp(Request $request): JsonResponse
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $phone = $request->input('phone', '08315068475');
        $channel = $request->input('channel', 'whatsapp');

        try {
            $otp = OtpService::generateAndSendOtp($phone, $channel);

            return response()->json([
                'success' => true,
                'message' => 'OTP generated and sent',
                'otp_id' => $otp->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

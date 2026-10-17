<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Maps\GeocodingServiceInterface;
use App\Services\Maps\GoogleMapsService;
use App\Services\Ai\AiClientInterface;
use App\Services\Ai\OpenAiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GeocodingServiceInterface::class, GoogleMapsService::class);
        $this->app->bind(AiClientInterface::class, OpenAiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * SECURITY (Tahap 19) — Rate limiters for sensitive endpoints.
     * - refund/create: anti-spam fraud
     * - dispute/open: anti-spam false disputes
     * - password/change: anti-brute force
     * - ai/chat: covered by AiAssistantService internally
     * - login: covered by AuthController internally
     */
    protected function configureRateLimiters(): void
    {
        // Refund: 5 requests per hour per user
        RateLimiter::for('refund', function (Request $request) {
            return Limit::perHour(5)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Dispute open: 3 per day per user (avoid spam/false disputes)
        RateLimiter::for('dispute', function (Request $request) {
            return Limit::perDay(3)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Password change / sensitive profile update: 3 per hour per user
        RateLimiter::for('password-change', function (Request $request) {
            return Limit::perHour(3)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });

        // Webhook ingestion: 60 per minute per IP (defense against replay)
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Login: 5 attempts per minute per email+IP — defense against
        // credential stuffing / brute force. Matches AuthController's
        // existing manual check but is also usable declaratively via
        // the throttle:login middleware on the login route.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email', ''));
            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });
    }
}
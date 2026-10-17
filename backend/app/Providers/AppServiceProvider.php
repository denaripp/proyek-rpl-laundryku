<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Otp\OtpSender::class,
            \App\Services\Otp\FonnteOtpSender::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            $key = $request->input('destination', $request->input('user_id', $request->ip()));
            return Limit::perMinute(5)->by($key . '|' . $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $key = $request->input('destination', $request->ip());
            return Limit::perMinute(5)->by($key . '|' . $request->ip());
        });

        RateLimiter::for('ai', function (Request $request) {
            $perMinute = (int) config('ai.rate_limit_per_minute', 10);
            $daily = (int) config('ai.daily_limit', 100);
            $key = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute($perMinute)->by($key),
                Limit::perDay($daily)->by($key),
            ];
        });

        if (config('ai.mock_in_local') && ! $this->app->environment('local')) {
            \Illuminate\Support\Facades\Log::warning('AI mock mode is disabled in non-local environment even though AI_MOCK_IN_LOCAL is true.');
        }

        if ($this->app->runningInConsole() && class_exists(\Illuminate\Foundation\Console\ServeCommand::class)) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
                \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables,
                [
                    'SystemDrive',
                    'SYSTEMDRIVE',
                    'SystemRoot',
                    'WINDIR',
                    'windir',
                    'TEMP',
                    'TMP',
                    'USERPROFILE',
                    'LOCALAPPDATA',
                    'APPDATA',
                    'COMSPEC',
                    'PATHEXT',
                    'ProgramData',
                    'ProgramFiles',
                    'CommonProgramFiles',
                ]
            )));
        }
    }
}

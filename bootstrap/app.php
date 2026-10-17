<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('mitra') || $request->is('mitra/*')) {
                return route('mitra.login');
            }
            if ($request->is('kurir') || $request->is('kurir/*')) {
                return route('kurir.login');
            }
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }
            return route('login');
        });

        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            if (auth('admin')->check()) {
                return route('admin.dashboard');
            }
            if (auth('mitra')->check()) {
                return route('mitra.dashboard');
            }
            if (auth('kurir')->check()) {
                return route('kurir.dashboard');
            }
            return route('customer.dashboard');
        });

        $middleware->alias([
            '2fa' => \App\Http\Middleware\VerifyTwoFactor::class,
            'admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);
    })
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

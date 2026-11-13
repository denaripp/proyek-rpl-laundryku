<?php

namespace App\Providers;

use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Courier;
use App\Models\CourierTask;
use App\Models\Dispute;
use App\Models\Review;
use App\Models\SettlementPeriod;
use App\Models\AuditLog;
use App\Policies\StorePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RefundPolicy;
use App\Policies\CourierPolicy;
use App\Policies\CourierTaskPolicy;
use App\Policies\DisputePolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SettlementPolicy;
use App\Policies\AuditLogPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Store::class => StorePolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        Refund::class => RefundPolicy::class,
        Courier::class => CourierPolicy::class,
        CourierTask::class => CourierTaskPolicy::class,
        Dispute::class => DisputePolicy::class,
        Review::class => ReviewPolicy::class,
        SettlementPeriod::class => SettlementPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define gates if needed
        // Gate::define('...', function ($user) { ... });
    }
}
@extends('layouts.dashboard')
@section('title', 'Dashboard — ' . $store->name)
@section('sidebar')
    @php
        $menu = [
            ['key' => 'overview', 'label' => 'Overview', 'route' => 'store.dashboard', 'icon' => 'speedometer2'],
            ['key' => 'orders', 'label' => 'Orders', 'route' => 'store.dashboard', 'icon' => 'box'],
            ['key' => 'services', 'label' => 'Services & Pricing', 'route' => 'store.dashboard', 'icon' => 'tag'],
            ['key' => 'staff', 'label' => 'Staff', 'route' => 'store.dashboard', 'icon' => 'people'],
            ['key' => 'couriers', 'label' => 'Couriers', 'route' => 'store.dashboard', 'icon' => 'truck'],
            ['key' => 'customers', 'label' => 'Customers', 'route' => 'store.dashboard', 'icon' => 'person'],
            ['key' => 'reports', 'label' => 'Reports', 'route' => 'store.dashboard', 'icon' => 'bar-chart'],
        ];
    @endphp
    @include('partials.sidebar', ['activeKey' => 'overview', 'menu' => $menu, 'sidebarLabel' => 'Store'])
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <h1 class="h3 fw-bold text-lk-primary mb-0">{{ $store->name }}</h1>
            <p class="text-muted mb-0 small">Operational overview</p>
        </div>
        <span class="badge lk-badge-{{ $store->is_active ? 'tertiary' : 'secondary' }}">{{ $store->status }}</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Orders Today</div>
                <div class="value">{{ $metrics['orders_today'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card secondary">
                <div class="label">In Progress</div>
                <div class="value">{{ $metrics['orders_in_progress'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card tertiary">
                <div class="label">Revenue Today</div>
                <div class="value">Rp {{ number_format($metrics['revenue_today'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Rating</div>
                <div class="value">{{ number_format($metrics['avg_rating'], 1) }} <small class="text-muted fs-6">({{ $metrics['review_count'] }})</small></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Revenue Month</div>
                <div class="value">Rp {{ number_format($metrics['revenue_month'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Completed (Month)</div>
                <div class="value">{{ $metrics['orders_completed_month'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card danger">
                <div class="label">Pending Refunds</div>
                <div class="value">{{ $metrics['refunds_pending'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Settlement Pending</div>
                <div class="value">Rp {{ number_format($metrics['settlement_pending_amount'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-7">
            <div class="lk-section h-100">
                <h5 class="mb-3">Active Orders</h5>
                @forelse($activeOrders as $o)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div>
                            <strong>#{{ $o->order_number }}</strong>
                            <small class="text-muted ms-2">{{ $o->customer->name ?? '-' }}</small>
                        </div>
                        <span class="lk-status-pill lk-status-{{ $o->status === 'completed' ? 'completed' : ($o->status === 'cancelled' ? 'cancelled' : 'process') }}">{{ $o->status }}</span>
                    </div>
                @empty
                    <p class="lk-empty mb-0">No active orders.</p>
                @endforelse
            </div>
        </div>
        <div class="col-md-5">
            <div class="lk-section h-100">
                <h5 class="mb-3">Payment Method (30d)</h5>
                @forelse($paymentMethodBreakdown as $pm)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span><i class="bi bi-{{ $pm->payment_method === 'cod' ? 'cash-coin' : 'credit-card' }} me-1"></i>{{ strtoupper($pm->payment_method) }}</span>
                        <span>{{ $pm->cnt }} · Rp {{ number_format($pm->total, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="lk-empty mb-0">No payments in the last 30 days.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-7">
            <div class="lk-section">
                <h5 class="mb-3">Recent Orders</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>When</th></tr>
                        </thead>
                        <tbody>
                        @forelse($recentOrders as $o)
                            <tr>
                                <td>#{{ $o->order_number }}</td>
                                <td>{{ $o->customer->name ?? '-' }}</td>
                                <td>Rp {{ number_format($o->total_amount, 0, ',', '.') }}</td>
                                <td><span class="lk-status-pill lk-status-{{ $o->status === 'completed' ? 'completed' : ($o->status === 'cancelled' ? 'cancelled' : 'process') }}">{{ $o->status }}</span></td>
                                <td><small class="text-muted">{{ $o->created_at->diffForHumans() }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No orders yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="lk-section mb-3">
                <h5 class="mb-3">Services & Pricing</h5>
                <p class="text-muted small mb-1">Active services: <strong>{{ $metrics['services_active'] }}</strong></p>
                <p class="text-muted small mb-0">Edit pricing via API: <code>PATCH /api/store-owner/stores/{id}</code></p>
            </div>
            <div class="lk-section mb-3">
                <h5 class="mb-3">Staff</h5>
                <p class="text-muted small mb-0">Active staff: <strong>{{ $metrics['staff_count'] }}</strong></p>
            </div>
            <div class="lk-section">
                <h5 class="mb-3">Top Customers</h5>
                @forelse($topCustomers as $tc)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $tc->customer->name ?? 'User #'.$tc->customer_id }}</span>
                        <span><small class="text-muted">{{ $tc->total_orders }} orders</small> · Rp {{ number_format($tc->total_spent, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <p class="lk-empty mb-0">No customers yet.</p>
                @endforelse
                <p class="small text-muted mt-2 mb-0">Total unique customers: <strong>{{ $metrics['customers_unique'] }}</strong></p>
            </div>
        </div>
    </div>
@endsection

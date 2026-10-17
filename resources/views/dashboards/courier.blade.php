@extends('layouts.dashboard')
@section('title', 'Courier Dashboard')
@section('sidebar')
    @php
        $menu = [
            ['key' => 'overview', 'label' => 'Overview', 'route' => 'courier.dashboard', 'icon' => 'speedometer2'],
            ['key' => 'active', 'label' => 'Active Tasks', 'route' => 'courier.dashboard', 'icon' => 'lightning-charge'],
            ['key' => 'history', 'label' => 'Task History', 'route' => 'courier.dashboard', 'icon' => 'clock-history'],
            ['key' => 'earnings', 'label' => 'Earnings', 'route' => 'courier.dashboard', 'icon' => 'cash-stack'],
        ];
    @endphp
    @include('partials.sidebar', ['activeKey' => 'overview', 'menu' => $menu, 'sidebarLabel' => 'Courier'])
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 fw-bold text-lk-primary mb-0">Courier Dashboard</h1>
            <p class="text-muted mb-0 small">Status & earnings</p>
        </div>
        <form method="POST" action="{{ route('courier.availability') }}" class="d-flex align-items-center gap-2">
            @csrf
            <span class="text-muted small">Available</span>
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" name="is_available" value="1" id="lkAvail" {{ $metrics['is_available'] ? 'checked' : '' }} onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Active Tasks</div>
                <div class="value">{{ $metrics['tasks_active'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card secondary">
                <div class="label">Today Completed</div>
                <div class="value">{{ $metrics['tasks_today_completed'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card tertiary">
                <div class="label">Earnings Today</div>
                <div class="value">Rp {{ number_format($metrics['earnings_today'], 0, ',', '.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="lk-stat-card">
                <div class="label">Earnings Month</div>
                <div class="value">Rp {{ number_format($metrics['earnings_month'], 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-7">
            <div class="lk-section h-100">
                <h5 class="mb-3">Active Tasks</h5>
                @forelse($activeTasks as $t)
                    <div class="border-bottom py-2">
                        <div class="d-flex justify-content-between">
                            <strong>Order #{{ $t->order->id ?? '?' }}</strong>
                            <span class="lk-status-pill lk-status-process">{{ $t->status }}</span>
                        </div>
                        <small class="text-muted">{{ $t->order->store->name ?? '-' }} → {{ $t->order->customer->name ?? '-' }}</small>
                    </div>
                @empty
                    <p class="lk-empty mb-0">No active tasks.</p>
                @endforelse
            </div>
        </div>
        <div class="col-md-5">
            <div class="lk-section h-100">
                <h5 class="mb-3">Earnings Summary</h5>
                <ul class="list-unstyled mb-0">
                    <li class="d-flex justify-content-between border-bottom py-2"><span>Today</span><strong>Rp {{ number_format($metrics['earnings_today'], 0, ',', '.') }}</strong></li>
                    <li class="d-flex justify-content-between border-bottom py-2"><span>This Month</span><strong>Rp {{ number_format($metrics['earnings_month'], 0, ',', '.') }}</strong></li>
                    <li class="d-flex justify-content-between py-2"><span>Lifetime</span><strong>Rp {{ number_format($metrics['earnings_lifetime'], 0, ',', '.') }}</strong></li>
                </ul>
                <p class="text-muted small mb-0 mt-2">Lifetime completed: {{ $metrics['tasks_lifetime'] }}</p>
            </div>
        </div>
    </div>

    <div class="lk-section">
        <h5 class="mb-3">Task History (latest 20)</h5>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Task</th><th>Store</th><th>Status</th><th>Completed</th></tr>
                </thead>
                <tbody>
                @forelse($recentTasks as $t)
                    <tr>
                        <td>#{{ $t->id }}</td>
                        <td>{{ $t->order->store->name ?? '-' }}</td>
                        <td><span class="lk-status-pill lk-status-{{ $t->status === 'delivered' ? 'completed' : ($t->status === 'failed' ? 'cancelled' : 'process') }}">{{ $t->status }}</span></td>
                        <td><small class="text-muted">{{ optional($t->completed_at)->diffForHumans() ?? '-' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No tasks yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

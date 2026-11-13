<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard — LaundryKu')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
    @stack('head')
    <style>
        body { background: var(--lk-neutral); }
        .lk-stat-card { background: #fff; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .lk-stat-card .label { font-size: .75rem; color: var(--lk-muted); text-transform: uppercase; letter-spacing: .05em; }
        .lk-stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--lk-primary); }
        .lk-stat-card.tertiary .value { color: var(--lk-tertiary); }
        .lk-stat-card.secondary .value { color: var(--lk-secondary); }
        .lk-stat-card.danger .value { color: var(--lk-danger); }
        .lk-section { background: #fff; border-radius: 12px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
        .lk-section h5 { color: var(--lk-primary); font-weight: 700; }
        .lk-empty { text-align: center; color: var(--lk-muted); padding: 2rem 0; }
    </style>
</head>
<body>
    @php
        $isMitra = auth('mitra')->check();
        $isKurir = auth('kurir')->check();
        $isAdmin = auth('admin')->check();
        $currentUser = auth('mitra')->user() ?? auth('kurir')->user() ?? auth('admin')->user() ?? auth()->user();
        $brandUrl = $isMitra ? route('mitra.dashboard') : ($isKurir ? route('kurir.dashboard') : ($isAdmin ? route('admin.dashboard') : route('home')));
        $logoutUrl = $isMitra ? route('mitra.logout') : ($isKurir ? route('kurir.logout') : ($isAdmin ? route('admin.logout') : route('logout')));
    @endphp
    <nav class="navbar navbar-expand-lg navbar-dark lk-navbar sticky-top" style="z-index: 1030;">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold" href="{{ $brandUrl }}">
                <i class="bi bi-droplet-fill me-1"></i>LaundryKu
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="text-light small d-none d-md-inline">{{ $currentUser->name ?? '' }}</span>
                @if(!$isMitra && !$isKurir && !$isAdmin)
                    <a href="{{ route('home') }}" class="btn btn-sm btn-outline-light">Lihat Situs</a>
                @endif
                <form action="{{ $logoutUrl }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-light">Logout</button>
                </form>
            </div>
        </div>
    </nav>
    <div class="d-flex">
        @yield('sidebar')
        <main class="flex-grow-1 p-4" style="min-width: 0;">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show small" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    {{-- AI Assistant widget --}}
    @include('partials.ai-widget')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.4/dist/axios.min.js"></script>
    <script>
        window.LK = {
            csrf: document.querySelector('meta[name=csrf-token]').content,
            apiBase: '/api',
        };
        axios.defaults.headers.common['X-CSRF-TOKEN'] = window.LK.csrf;
    </script>
    @stack('scripts')
</body>
</html>

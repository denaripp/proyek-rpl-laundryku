<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard Mitra — LaundryKu')</title>

    {{-- Bootstrap 5 CDN + Icons + Vanilla JS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
    <style>
        :root {
            --brand-primary: #1A3A5C;
            --brand-secondary: #4A90D9;
            --brand-success: #2ECC71;
            --brand-bg: #F5F7FA;
        }
        body {
            background-color: var(--brand-bg);
            color: #1e293b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .navbar-mitra {
            background-color: var(--brand-primary);
        }
        .nav-link.active {
            color: var(--brand-secondary) !important;
            font-weight: 600;
        }
    </style>
    @stack('head')
</head>
<body>
    {{-- Dedicated Mitra Navbar (Strict Isolation: Only Dashboard, Order, Layanan, Profil Toko, Logout) --}}
    <nav class="navbar navbar-expand-lg navbar-dark navbar-mitra sticky-top shadow-sm py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="{{ route('mitra.dashboard') }}">
                <span class="p-1 rounded-2 me-2" style="background: rgba(255,255,255,0.15);">
                    <i class="bi bi-shop-window text-white"></i>
                </span>
                <span>LaundryKu <span class="badge bg-light text-dark ms-1 small fw-semibold" style="font-size: 0.7rem;">MITRA</span></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mitraNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mitraNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('mitra.dashboard') ? 'active' : '' }}" href="{{ route('mitra.dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-box-seam me-1"></i>Order &amp; Tugas
                        </a>
                        <ul class="dropdown-menu shadow-sm border-0">
                            <li><a class="dropdown-item" href="{{ route('mitra.dashboard') }}#orders"><i class="bi bi-list-check me-2"></i>Daftar Order Masuk</a></li>
                            <li><a class="dropdown-item" href="{{ route('mitra.dashboard') }}#active-tasks"><i class="bi bi-truck me-2"></i>Tugas Kurir Toko</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('mitra.dashboard') }}#services">
                            <i class="bi bi-tags me-1"></i>Layanan
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Profil Toko
                        </a>
                        <ul class="dropdown-menu shadow-sm border-0">
                            <li><a class="dropdown-item" href="{{ route('mitra.dashboard') }}#store-profile"><i class="bi bi-info-circle me-2"></i>Informasi &amp; Jam Operasional</a></li>
                            <li><a class="dropdown-item" href="{{ route('mitra.dashboard') }}#reports"><i class="bi bi-bar-chart-line me-2"></i>Laporan Keuangan &amp; COD</a></li>
                        </ul>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3 text-light small d-none d-lg-block">
                        <i class="bi bi-person-circle me-1"></i>{{ Auth::guard('mitra')->user()->name ?? 'Mitra' }}
                    </li>
                    <li class="nav-item">
                        <form action="{{ route('mitra.logout') }}" method="POST" class="m-0">
                            @csrf
                            <button class="btn btn-sm btn-outline-light rounded-pill px-3" type="submit">
                                <i class="bi bi-box-arrow-right me-1"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="py-4">
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>

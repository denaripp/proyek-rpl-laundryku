<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Kurir — LaundryKu')</title>

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
        .navbar-kurir {
            background-color: #0f172a;
        }
    </style>
    @stack('head')
</head>
<body>
    {{-- Dedicated Kurir Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-dark navbar-kurir sticky-top shadow-sm py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="{{ route('kurir.dashboard') }}">
                <span class="p-1 rounded-2 me-2" style="background: rgba(46, 204, 113, 0.2);">
                    <i class="bi bi-bicycle text-success"></i>
                </span>
                <span>LaundryKu <span class="badge bg-success ms-1 small" style="font-size: 0.7rem;">KURIR</span></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kurirNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="kurirNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('kurir.dashboard') }}">
                            <i class="bi bi-geo-alt-fill me-1"></i>Tugas Aktif &amp; Riwayat
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item me-3 text-light small d-none d-lg-block">
                        <i class="bi bi-person-badge me-1"></i>{{ Auth::guard('kurir')->user()->name ?? 'Kurir' }}
                    </li>
                    <li class="nav-item">
                        <form action="{{ route('kurir.logout') }}" method="POST" class="m-0">
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

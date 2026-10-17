<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LaundryKu — Laundry Online Terdekat')</title>
    <meta name="description" content="@yield('description', 'Cari laundry terdekat, bandingkan harga, dan pesan antar-jemput.')">

    {{-- Bootstrap 5 CDN (no build step needed) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
    @stack('head')
</head>
<body>
    @php
        $isCustomer = auth('web')->check();
        $isMitra = auth('mitra')->check();
        $isKurir = auth('kurir')->check();
        $isAdmin = auth('admin')->check();
        $isAnyAuth = $isCustomer || $isMitra || $isKurir || $isAdmin;
    @endphp

    {{-- Navbar --}}
    <nav class="navbar navbar-expand-lg navbar-dark lk-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}">
                <i class="bi bi-droplet-fill me-1"></i>LaundryKu
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#lkNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="lkNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ route('marketplace.index') }}">Cari Laundry</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#cara-pesan">Cara Pesan</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#faq">FAQ</a></li>
                    @if(!$isAnyAuth)
                        <li class="nav-item"><a class="nav-link text-warning fw-semibold" href="{{ route('mitra.register') }}"><i class="bi bi-shop me-1"></i>Gabung Jadi Mitra</a></li>
                    @endif
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    @if($isCustomer)
                        <li class="nav-item"><a class="nav-link" href="{{ route('customer.dashboard') }}"><i class="bi bi-person-circle me-1"></i>{{ auth('web')->user()->name }}</a></li>
                        <li class="nav-item">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-light ms-2 rounded-pill px-3" type="submit">Logout</button>
                            </form>
                        </li>
                    @elseif($isMitra)
                        <li class="nav-item"><a class="nav-link" href="{{ route('mitra.dashboard') }}"><i class="bi bi-shop me-1"></i>Dashboard Mitra</a></li>
                        <li class="nav-item">
                            <form action="{{ route('mitra.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-light ms-2 rounded-pill px-3" type="submit">Logout</button>
                            </form>
                        </li>
                    @elseif($isKurir)
                        <li class="nav-item"><a class="nav-link" href="{{ route('kurir.dashboard') }}"><i class="bi bi-bicycle me-1"></i>Portal Kurir</a></li>
                        <li class="nav-item">
                            <form action="{{ route('kurir.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-light ms-2 rounded-pill px-3" type="submit">Logout</button>
                            </form>
                        </li>
                    @elseif($isAdmin)
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="bi bi-shield-lock me-1"></i>Admin Panel</a></li>
                        <li class="nav-item">
                            <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-light ms-2 rounded-pill px-3" type="submit">Logout</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Masuk</a></li>
                        <li class="nav-item"><a class="btn btn-sm btn-lk-secondary ms-2 rounded-pill px-3" href="{{ route('register') }}">Daftar</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    {{-- Main content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="lk-footer text-light py-4 mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h6 class="fw-bold"><i class="bi bi-droplet-fill me-1"></i>LaundryKu</h6>
                    <p class="small mb-0">Platform marketplace laundry online multi-tenant terpercaya.</p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold">Untuk Mitra</h6>
                    <ul class="list-unstyled small mb-0">
                        <li><a class="text-light" href="{{ route('mitra.register') }}">Daftarkan Laundry Anda</a></li>
                        <li><a class="text-light" href="{{ route('mitra.login') }}">Login Mitra</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold">Kontak</h6>
                    <p class="small mb-0">
                        <i class="bi bi-envelope me-1"></i>halo@laundryku.id<br>
                        <i class="bi bi-telephone me-1"></i>+62 812-0000-0000
                    </p>
                </div>
            </div>
            <hr class="border-light my-3">
            <p class="small text-center mb-0">&copy; {{ date('Y') }} LaundryKu. Semua hak dilindungi.</p>
        </div>
    </footer>

    {{-- AI Assistant widget --}}
    @include('partials.ai-widget')

    {{-- Scripts --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.4/dist/axios.min.js"></script>
    <script>
        window.LK = {
            csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            user: @json(auth()->user()),
            mapsKey: @json(config('services.google_maps.key') ?? ''),
            apiBase: '/api',
        };
        axios.defaults.headers.common['X-CSRF-TOKEN'] = window.LK.csrf;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    </script>
    @stack('scripts')
</body>
</html>

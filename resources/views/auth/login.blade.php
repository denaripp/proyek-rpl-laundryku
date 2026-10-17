@extends('layouts.app')

@section('title', 'Masuk — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4">
                    <h2 class="h4 fw-bold text-lk-primary mb-3 text-center">Masuk ke LaundryKu</h2>

                    @if($errors->any())
                        <div class="alert alert-danger small">{{ $errors->first() }}</div>
                    @endif

                    {{-- PRIMARY: Google OAuth untuk customer publik (PRD §7.6) --}}
                    @php
                        $nextParam = request('next') ?: (session()->has('url.intended') ? parse_url(session('url.intended'), PHP_URL_PATH) : null);
                    @endphp
                    <a href="{{ $nextParam ? route('google.redirect', ['next' => $nextParam]) : route('google.redirect') }}" class="btn btn-outline-secondary w-100 mb-3">
                        <i class="bi bi-google me-1"></i>Masuk dengan Google
                    </a>

                    <div class="d-flex align-items-center mb-3">
                        <hr class="flex-grow-1">
                        <span class="px-2 small text-muted">atau</span>
                        <hr class="flex-grow-1">
                    </div>

                    {{-- SECONDARY: email/password untuk staff internal / admin --}}
                    <p class="small text-muted mb-2">
                        Email &amp; password di bawah hanya untuk akun internal
                        (Store Owner / Courier / Platform Admin). Pelanggan publik mohon
                        masuk melalui Google.
                    </p>

                    <form method="POST" action="{{ $nextParam ? route('login', ['next' => $nextParam]) : route('login') }}">
                        @csrf
                        @if($nextParam)
                            <input type="hidden" name="next" value="{{ $nextParam }}">
                        @endif
                        <div class="mb-3">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button class="btn btn-lk-primary w-100" type="submit">Masuk</button>
                    </form>

                    <p class="text-center small mt-3 mb-0">
                        Belum punya akun? <a href="{{ route('register') }}">Daftar</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
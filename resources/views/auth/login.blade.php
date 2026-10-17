@extends('layouts.app')

@section('title', 'Masuk Customer — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(26, 58, 92, 0.1); color: #1A3A5C; font-weight: 600;">
                            Portal Pelanggan
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #1A3A5C;">Masuk ke LaundryKu</h2>
                        <p class="text-muted small">Kelola cucian Anda dengan mudah, cepat, dan transparan.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    @php
                        $nextParam = request('next') ?: (session()->has('url.intended') ? parse_url(session('url.intended'), PHP_URL_PATH) : null);
                    @endphp

                    <a href="{{ $nextParam ? route('google.redirect', ['next' => $nextParam]) : route('google.redirect') }}" class="btn btn-outline-secondary w-100 mb-3 py-2 fw-medium rounded-3">
                        <i class="bi bi-google me-2 text-danger"></i>Masuk dengan Google
                    </a>

                    <div class="d-flex align-items-center mb-3">
                        <hr class="flex-grow-1 border-secondary-subtle">
                        <span class="px-3 small text-muted">atau via Email</span>
                        <hr class="flex-grow-1 border-secondary-subtle">
                    </div>

                    <form method="POST" action="{{ $nextParam ? route('login', ['next' => $nextParam]) : route('login') }}">
                        @csrf
                        @if($nextParam)
                            <input type="hidden" name="next" value="{{ $nextParam }}">
                        @endif
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="nama@email.com" required value="{{ old('email') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #1A3A5C;">
                            Masuk
                        </button>
                    </form>

                    <p class="text-center small mt-4 mb-0 text-muted">
                        Belum punya akun? <a href="{{ route('register') }}" class="fw-semibold text-decoration-none" style="color: #4A90D9;">Daftar Pelanggan</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
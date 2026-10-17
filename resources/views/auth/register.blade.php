@extends('layouts.app')

@section('title', 'Daftar — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="lk-card p-4">
                    <h2 class="h4 fw-bold text-lk-primary mb-3 text-center">Daftar Akun Baru</h2>

                    @if($errors->any())
                        <div class="alert alert-danger small">{{ $errors->first() }}</div>
                    @endif

                    {{-- PRIMARY: Google OAuth untuk customer publik (PRD §7.6) --}}
                    <a href="{{ route('google.redirect') }}" class="btn btn-outline-secondary w-100 mb-3">
                        <i class="bi bi-google me-1"></i>Daftar dengan Google
                    </a>

                    <div class="d-flex align-items-center mb-3">
                        <hr class="flex-grow-1">
                        <span class="px-2 small text-muted">atau</span>
                        <hr class="flex-grow-1">
                    </div>

                    {{-- SECONDARY: email/password form (untuk staff internal / admin) --}}
                    <p class="small text-muted mb-2">
                        Form email &amp; password di bawah ini hanya untuk
                        <strong>pembuatan akun internal</strong> (Store Owner / Courier / Platform Admin)
                        oleh administrator. Pelanggan publik mohon daftar melalui Google.
                    </p>

                    <form method="POST" action="{{ route('register') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small">Nama</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">No. Telepon (opsional)</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Konfirmasi Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                        <button class="btn btn-lk-primary w-100" type="submit">Daftar</button>
                    </form>

                    <p class="text-center small mt-3 mb-0">
                        Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
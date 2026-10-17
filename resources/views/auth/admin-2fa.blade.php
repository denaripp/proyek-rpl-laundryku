@extends('layouts.app')

@section('title', 'Verifikasi 2FA Super Admin — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(46, 204, 113, 0.15); color: #27ae60; font-weight: 700;">
                            <i class="bi bi-key-fill me-1"></i>Multi-Factor Authentication
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #0F172A;">Verifikasi Dua Langkah</h2>
                        <p class="text-muted small">Buka aplikasi Google Authenticator Anda dan masukkan kode 6-digit.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('admin.2fa.verify') }}">
                        @csrf
                        <div class="mb-4 text-center">
                            <label class="form-label small fw-semibold text-secondary mb-2">Kode OTP 6 Digit</label>
                            <input type="text" name="one_time_password" class="form-control text-center fs-3 fw-bold tracking-widest rounded-3 mx-auto" style="max-width: 220px; letter-spacing: 0.3em;" placeholder="000000" maxlength="6" autofocus required>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #0F172A;">
                            Konfirmasi & Masuk
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('admin.login') }}" class="small text-muted text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Kembali ke login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

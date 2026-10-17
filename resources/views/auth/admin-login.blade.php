@extends('layouts.app')

@section('title', 'Admin Platform Login — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(15, 23, 42, 0.1); color: #0F172A; font-weight: 700;">
                            <i class="bi bi-shield-lock me-1"></i>Administrator Terisolasi
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #0F172A;">Super Admin Access</h2>
                        <p class="text-muted small">Otoritas keamanan & tata kelola ekosistem LaundryKu.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('admin.login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email Administrator</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="admin@laundryku.test" required value="{{ old('email') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #0F172A;">
                            Lanjutkan ke Verifikasi 2FA
                        </button>
                    </form>

                    <div class="alert alert-light border small mt-4 mb-0 text-muted text-center rounded-3">
                        <i class="bi bi-lock-fill me-1 text-warning"></i>Portal ini terproteksi audit log & wajib 2FA Time-Based One-Time Password (TOTP).
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

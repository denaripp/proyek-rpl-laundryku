@extends('layouts.app')

@section('title', 'Login Kurir — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(46, 204, 113, 0.15); color: #27ae60; font-weight: 600;">
                            Portal Kurir Operasional
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #1A3A5C;">Masuk Kurir</h2>
                        <p class="text-muted small">Akses tugas penjemputan, pengantaran, dan pelunasan COD.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('kurir.login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email Kurir</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="kurir@laundry.com" required value="{{ old('email') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #1A3A5C;">
                            Masuk ke Tugas Kurir
                        </button>
                    </form>

                    <div class="alert alert-light border small mt-4 mb-0 text-muted text-center rounded-3">
                        <i class="bi bi-info-circle me-1"></i>Akun kurir didaftarkan langsung oleh Mitra Toko atau Administrator. Tidak ada registrasi kurir secara publik.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Login Mitra Toko — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(74, 144, 217, 0.15); color: #1A3A5C; font-weight: 600;">
                            Portal Mitra Laundry
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #1A3A5C;">Masuk ke Toko Anda</h2>
                        <p class="text-muted small">Kelola pesanan, layanan, timbangan dan kurir toko Anda.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('mitra.login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email Mitra</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="owner@tokolaundry.com" required value="{{ old('email') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Password</label>
                            <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #1A3A5C;">
                            Masuk ke Dashboard Mitra
                        </button>
                    </form>

                    <p class="text-center small mt-4 mb-0 text-muted">
                        Ingin bermitra dengan LaundryKu? <a href="{{ route('mitra.register') }}" class="fw-semibold text-decoration-none" style="color: #4A90D9;">Daftarkan Toko</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

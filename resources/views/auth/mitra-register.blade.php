@extends('layouts.app')

@section('title', 'Daftar Mitra Toko — LaundryKu')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="lk-card p-4 shadow-sm border-0 rounded-4" style="background: #ffffff;">
                    <div class="text-center mb-4">
                        <div class="badge px-3 py-2 rounded-pill mb-2" style="background: rgba(74, 144, 217, 0.15); color: #1A3A5C; font-weight: 600;">
                            Pendaftaran Mitra
                        </div>
                        <h2 class="h4 fw-bold mb-1" style="color: #1A3A5C;">Buka Bisnis Laundry Digital</h2>
                        <p class="text-muted small">Bergabunglah dengan ratusan pengusaha laundry dan jangkau ribuan pelanggan baru.</p>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger small py-2 px-3 rounded-3">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('mitra.register') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Nama Pemilik / Penanggung Jawab</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="Nama Lengkap" required value="{{ old('name') }}" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Email Bisnis / Pemilik</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="bisnis@laundry.com" required value="{{ old('email') }}">
                            <div class="form-text small">Boleh menggunakan email pribadi yang sama jika sebelumnya mendaftar sebagai customer.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Nomor WhatsApp Aktif</label>
                            <input type="text" name="phone" class="form-control rounded-3" placeholder="08xxxxxxxxxx" required value="{{ old('phone') }}">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold text-secondary">Password</label>
                                <input type="password" name="password" class="form-control rounded-3" placeholder="Minimal 8 karakter" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-semibold text-secondary">Konfirmasi Password</label>
                                <input type="password" name="password_confirmation" class="form-control rounded-3" placeholder="Ulangi password" required>
                            </div>
                        </div>
                        <button class="btn w-100 py-2 fw-semibold text-white rounded-3 shadow-sm" type="submit" style="background-color: #1A3A5C;">
                            Lanjutkan Pendaftaran Toko
                        </button>
                    </form>

                    <p class="text-center small mt-4 mb-0 text-muted">
                        Sudah punya akun Mitra? <a href="{{ route('mitra.login') }}" class="fw-semibold text-decoration-none" style="color: #4A90D9;">Masuk di sini</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Daftarkan Laundry Anda — Mitra LaundryKu')
@section('description', 'Daftarkan usaha laundry Anda dan raih ribuan pelanggan baru di sekitar area Anda.')

@section('content')
<div class="container py-5">
    {{-- Status Alerts --}}
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat kesalahan pada input pendaftaran:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @guest
        {{-- ================= GUEST VIEW: Marketing & Direct Google OAuth CTA ================= --}}
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="lk-card p-4 p-md-5 text-center shadow-sm">
                    <div class="mb-3">
                        <span class="p-3 rounded-circle bg-light d-inline-block text-lk-secondary">
                            <i class="bi bi-shop fs-1"></i>
                        </span>
                    </div>
                    <h1 class="h2 fw-bold text-lk-primary mb-3">Daftarkan Laundry Anda ke LaundryKu</h1>
                    <p class="text-muted lead fs-6 mb-4">
                        Bergabunglah dengan ratusan mitra laundry terpercaya. Dapatkan pesanan antar-jemput rutin langsung dari pelanggan di sekitar Anda.
                    </p>

                    <div class="row g-3 my-3 text-start">
                        @foreach([
                            ['bi-graph-up-arrow', 'Tingkatkan Omset', 'Jangkauan lebih luas dan orderan konsisten setiap hari.'],
                            ['bi-truck', 'Dukungan Kurir Terintegrasi', 'Kurir siap antar-jemput pesanan pelanggan Anda.'],
                            ['bi-wallet2', 'Pembayaran Otomatis & Aman', 'Xendit, QRIS, dan COD dengan settlement transparan.'],
                            ['bi-headset', 'Dukungan Prioritas 7x24', 'Tim support siap membantu kendala operasional Anda.'],
                        ] as $benefit)
                            <div class="col-md-6">
                                <div class="d-flex align-items-start p-3 rounded bg-light h-100">
                                    <i class="bi {{ $benefit[0] }} text-lk-secondary fs-4 me-3 mt-1"></i>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $benefit[1] }}</div>
                                        <div class="small text-muted">{{ $benefit[2] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 pt-2">
                        {{-- Direct Google OAuth CTA (skips /login to avoid loops) --}}
                        <a href="{{ route('google.redirect', ['next' => route('store.onboard')]) }}" class="btn btn-lk-primary btn-lg px-4 py-3 shadow">
                            <i class="bi bi-google me-2"></i>Masuk dengan Google untuk Mendaftar
                        </a>
                        <p class="small text-muted mt-3 mb-0">
                            Proses pendaftaran cepat &amp; gratis. Akun aman terhubung langsung via Google.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- ================= AUTHENTICATED USER ================= --}}
        @if($store && in_array($store->status, ['submitted', 'under_review', 'approved', 'rejected', 'suspended']) && request('mode') !== 'edit' && !$isNewMode)
            {{-- ========== SUBMITTED / UNDER REVIEW / APPROVED STATUS TRACKER ========== --}}
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    {{-- Multi-Store Navigation / New Store CTA --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        @if($userStores->count() > 1)
                            <div>
                                <span class="text-muted small">Outlet:</span>
                                <div class="dropdown d-inline-block ms-1">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-shop me-1"></i>{{ $store->name }} ({{ strtoupper(str_replace('_', ' ', $store->status)) }})
                                    </button>
                                    <ul class="dropdown-menu shadow-sm">
                                        @foreach($userStores as $uStore)
                                            <li>
                                                <a class="dropdown-item d-flex justify-content-between align-items-center {{ $uStore->id === $store->id ? 'active' : '' }}" href="{{ route('store.onboard', ['store_id' => $uStore->id]) }}">
                                                    <span>{{ $uStore->name }}</span>
                                                    <span class="badge bg-secondary ms-2" style="font-size: 0.7rem;">{{ $uStore->status }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <a href="{{ route('store.onboard', ['mode' => 'new']) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-circle me-1"></i>Daftarkan Toko Baru
                        </a>
                    </div>

                    <div class="lk-card p-4 p-md-5 shadow-sm">
                        <div class="text-center mb-4">
                            @if($store->status === 'approved')
                                <div class="text-success mb-2"><i class="bi bi-check-circle-fill display-4"></i></div>
                                <h2 class="h3 fw-bold text-success">Laundry Anda Telah Disetujui!</h2>
                                <p class="text-muted">Selamat, <strong>{{ $store->name }}</strong> sudah aktif dan dapat menerima pesanan pelanggan di Marketplace.</p>
                                <a href="{{ route('store.dashboard') }}" class="btn btn-lk-primary btn-lg mt-2">
                                    <i class="bi bi-speedometer2 me-1"></i>Buka Dashboard Mitra
                                </a>
                            @elseif($store->status === 'rejected')
                                <div class="text-danger mb-2"><i class="bi bi-x-circle-fill display-4"></i></div>
                                <h2 class="h3 fw-bold text-danger">Pendaftaran Memerlukan Perhatian</h2>
                                <p class="text-muted">Pengajuan pendaftaran <strong>{{ $store->name }}</strong> saat ini ditolak.</p>
                                @if($store->application?->rejection_reason)
                                    <div class="alert alert-danger text-start">
                                        <strong>Alasan:</strong> {{ $store->application->rejection_reason }}
                                    </div>
                                @endif
                                <a href="{{ route('store.onboard', ['mode' => 'edit', 'store_id' => $store->id]) }}" class="btn btn-outline-danger">
                                    <i class="bi bi-pencil me-1"></i>Perbaiki Data &amp; Ajukan Ulang
                                </a>
                            @else
                                <div class="text-lk-secondary mb-2"><i class="bi bi-hourglass-split display-4"></i></div>
                                <h2 class="h3 fw-bold text-lk-primary">Pengajuan Sedang Ditinjau</h2>
                                <p class="text-muted">Terima kasih telah mendaftar. Tim verifikasi LaundryKu sedang meninjau data outlet <strong>{{ $store->name }}</strong>.</p>
                            @endif
                        </div>

                        {{-- Progress Tracker Steps --}}
                        <div class="card bg-light border-0 p-3 mb-4">
                            <h6 class="fw-bold mb-3 text-secondary">Status Progress Pengajuan:</h6>
                            <div class="d-flex justify-content-between position-relative text-center">
                                <div class="flex-fill">
                                    <div class="btn btn-sm btn-success rounded-circle mb-2" style="width:34px;height:34px;line-height:22px;"><i class="bi bi-check-lg"></i></div>
                                    <div class="small fw-bold">1. Formulir Terkirim</div>
                                    <div class="text-muted" style="font-size:0.75rem;">{{ $store->application?->submitted_at ? $store->application->submitted_at->format('d M Y H:i') : $store->created_at->format('d M Y') }}</div>
                                </div>
                                <div class="flex-fill">
                                    <div class="btn btn-sm {{ in_array($store->status, ['under_review', 'approved']) ? 'btn-success' : 'btn-primary' }} rounded-circle mb-2" style="width:34px;height:34px;line-height:22px;">
                                        @if($store->status === 'approved') <i class="bi bi-check-lg"></i> @else 2 @endif
                                    </div>
                                    <div class="small fw-bold">2. Peninjauan Tim</div>
                                    <div class="text-muted" style="font-size:0.75rem;">{{ in_array($store->status, ['under_review', 'approved']) ? 'Selesai / Berlangsung' : 'Dalam Antrean' }}</div>
                                </div>
                                <div class="flex-fill">
                                    <div class="btn btn-sm {{ $store->status === 'approved' ? 'btn-success' : 'btn-secondary' }} rounded-circle mb-2" style="width:34px;height:34px;line-height:22px;">
                                        @if($store->status === 'approved') <i class="bi bi-check-lg"></i> @else 3 @endif
                                    </div>
                                    <div class="small fw-bold">3. Aktivasi Toko</div>
                                    <div class="text-muted" style="font-size:0.75rem;">{{ $store->status === 'approved' ? 'Aktif' : 'Menunggu Review' }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Summary Detail --}}
                        <div class="border rounded p-3 text-start small">
                            <h6 class="fw-bold text-lk-primary mb-2">Ringkasan Data Pendaftaran</h6>
                            <div class="row g-2">
                                <div class="col-sm-4 text-muted">Nama Outlet:</div>
                                <div class="col-sm-8 fw-semibold">{{ $store->name }}</div>
                                <div class="col-sm-4 text-muted">Alamat:</div>
                                <div class="col-sm-8">{{ $store->address }}, {{ $store->city }}, {{ $store->province }} {{ $store->postal_code }}</div>
                                <div class="col-sm-4 text-muted">Telepon Outlet:</div>
                                <div class="col-sm-8">{{ $store->phone }}</div>
                                <div class="col-sm-4 text-muted">Radius Layanan:</div>
                                <div class="col-sm-8">{{ $store->service_radius_km ?? 5 }} km</div>
                                <div class="col-sm-4 text-muted">Status:</div>
                                <div class="col-sm-8">
                                    <span class="badge bg-{{ $store->status === 'approved' ? 'success' : ($store->status === 'rejected' ? 'danger' : 'warning text-dark') }}">
                                        {{ strtoupper(str_replace('_', ' ', $store->status)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- ================= ACTUAL ONBOARDING FORM ================= --}}
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    @if($isNewMode && $userStores->count() > 0)
                        <div class="alert alert-info mb-4 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-info-circle me-2"></i>Anda sedang mendaftarkan <strong>outlet/toko baru</strong> (Toko ke-{{ $userStores->count() + 1 }}).
                            </div>
                            <a href="{{ route('store.onboard') }}" class="btn btn-sm btn-outline-primary">
                                Kembali ke Status Toko
                            </a>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 fw-bold text-lk-primary mb-1">
                                {{ $isNewMode ? 'Pendaftaran Toko Baru' : 'Formulir Pendaftaran Mitra Laundry' }}
                            </h1>
                            <p class="text-muted mb-0">Lengkapi data outlet Anda untuk mulai menerima pesanan.</p>
                        </div>
                        @if($store && $store->status === 'draft')
                            <span class="badge bg-secondary p-2">Status: DRAFT</span>
                        @endif
                    </div>

                    @if($store && $store->status === 'revision_required' && $store->application?->revision_notes)
                        <div class="alert alert-warning mb-4">
                            <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Catatan Revisi dari Admin:</h6>
                            <p class="mb-0">{{ $store->application->revision_notes }}</p>
                        </div>
                    @endif

                    <form action="{{ route('store.onboard.submit') }}" method="POST" id="onboardForm">
                        @csrf
                        @if($store && !$isNewMode)
                            <input type="hidden" name="store_id" value="{{ $store->id }}">
                        @endif
                        @if($isNewMode)
                            <input type="hidden" name="is_new" value="1">
                        @endif

                        {{-- Section 1: Data Pemilik --}}
                        <div class="lk-card p-4 mb-4 shadow-sm">
                            <h5 class="fw-bold text-lk-primary mb-3">
                                <i class="bi bi-person-badge text-lk-secondary me-2"></i>1. Data Pemilik
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nama Pemilik</label>
                                    <input type="text" class="form-control bg-light" value="{{ auth()->user()->name }}" readonly>
                                    <div class="form-text" style="font-size:0.75rem;">Sesuai akun Google yang sedang login.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email Pemilik</label>
                                    <input type="email" class="form-control bg-light" value="{{ auth()->user()->email }}" readonly>
                                    <div class="form-text" style="font-size:0.75rem;">Email akun Google Anda.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">No. HP Pemilik</label>
                                    <input type="text" name="owner_phone" class="form-control" value="{{ old('owner_phone', auth()->user()->phone ?? $store?->phone) }}" placeholder="Contoh: 08123456789">
                                    <div class="form-text" style="font-size:0.75rem;">Wajib saat submit pendaftaran.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">No. WhatsApp Pemilik</label>
                                    <input type="text" name="owner_whatsapp" class="form-control" value="{{ old('owner_whatsapp', $store?->whatsapp ?? auth()->user()->phone) }}" placeholder="Contoh: 08123456789">
                                </div>
                            </div>
                        </div>

                        {{-- Section 2: Data Laundry --}}
                        <div class="lk-card p-4 mb-4 shadow-sm">
                            <h5 class="fw-bold text-lk-primary mb-3">
                                <i class="bi bi-shop text-lk-secondary me-2"></i>2. Profil Outlet Laundry
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Nama Laundry</label>
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $store?->name) }}" placeholder="Contoh: Berkah Laundry Express">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Telepon Outlet</label>
                                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $store?->phone ?? auth()->user()->phone) }}" placeholder="08123456789">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Deskripsi Laundry</label>
                                    <textarea name="description" class="form-control" rows="2" placeholder="Deskripsikan keunggulan laundry Anda, deterjen yang digunakan, dll.">{{ old('description', $store?->description) }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Section 3: Alamat & Lokasi --}}
                        <div class="lk-card p-4 mb-4 shadow-sm">
                            <h5 class="fw-bold text-lk-primary mb-3">
                                <i class="bi bi-geo-alt-fill text-lk-secondary me-2"></i>3. Alamat Lengkap &amp; Lokasi Peta
                            </h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Alamat Jalan / Patokan</label>
                                    <textarea name="address" class="form-control" rows="2" placeholder="Nama jalan, nomor bangunan, patokan lokasi...">{{ old('address', $store?->address) }}</textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">RT / RW</label>
                                    <input type="text" name="rt_rw" class="form-control" value="{{ old('rt_rw', $store?->rt_rw) }}" placeholder="001/002">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Kelurahan / Desa</label>
                                    <input type="text" name="kelurahan" class="form-control" value="{{ old('kelurahan', $store?->kelurahan) }}" placeholder="Kelurahan">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Kecamatan</label>
                                    <input type="text" name="kecamatan" class="form-control" value="{{ old('kecamatan', $store?->kecamatan) }}" placeholder="Kecamatan">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Kota / Kabupaten</label>
                                    <input type="text" name="city" class="form-control" value="{{ old('city', $store?->city ?? 'Jakarta Selatan') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Provinsi</label>
                                    <input type="text" name="province" class="form-control" value="{{ old('province', $store?->province ?? 'DKI Jakarta') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Kode Pos</label>
                                    <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $store?->postal_code ?? '12345') }}">
                                </div>

                                {{-- Peta Pin Lokasi --}}
                                <div class="col-12 mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label small fw-semibold mb-0">Titik Koordinat Outlet (Peta)</label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnGetLocation">
                                            <i class="bi bi-crosshair me-1"></i>Gunakan Lokasi GPS Saya
                                        </button>
                                    </div>
                                    <div id="onboardMap" style="height: 250px; border-radius: 8px; border: 1px solid #ced4da;"></div>
                                    <div class="row g-2 mt-2">
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Lat</span>
                                                <input type="number" step="any" name="latitude" id="inputLat" class="form-control" value="{{ old('latitude', $store?->latitude ?? -6.2088) }}">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Lng</span>
                                                <input type="number" step="any" name="longitude" id="inputLng" class="form-control" value="{{ old('longitude', $store?->longitude ?? 106.8456) }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 4: Operasional & Pengantaran --}}
                        <div class="lk-card p-4 mb-4 shadow-sm">
                            <h5 class="fw-bold text-lk-primary mb-3">
                                <i class="bi bi-clock-history text-lk-secondary me-2"></i>4. Operasional &amp; Pengantaran
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Jam Buka</label>
                                    <input type="time" name="open_time" class="form-control" value="{{ old('open_time', '08:00') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Jam Tutup</label>
                                    <input type="time" name="close_time" class="form-control" value="{{ old('close_time', '20:00') }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Radius Layanan (km)</label>
                                    <input type="number" step="0.5" name="service_radius_km" class="form-control" value="{{ old('service_radius_km', $store?->service_radius_km ?? 5) }}" min="1" max="50">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Minimal Order (Rp)</label>
                                    <input type="number" name="minimum_order_amount" class="form-control" value="{{ old('minimum_order_amount', $store?->minimum_order_amount ?? 0) }}" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Biaya Penjemputan (Rp)</label>
                                    <input type="number" name="pickup_fee" class="form-control" value="{{ old('pickup_fee', $store?->pickup_fee ?? 5000) }}" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Biaya Pengantaran (Rp)</label>
                                    <input type="number" name="delivery_fee" class="form-control" value="{{ old('delivery_fee', $store?->delivery_fee ?? 5000) }}" min="0">
                                </div>
                                <div class="col-md-4 d-flex align-items-center">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" name="cod_enabled" value="1" id="codSwitch" {{ old('cod_enabled', $store?->cod_enabled) ? 'checked' : '' }}>
                                        <label class="form-check-label small fw-semibold" for="codSwitch">Terima Pembayaran COD (Bayar di Tempat)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 5: Layanan & Harga --}}
                        <div class="lk-card p-4 mb-4 shadow-sm">
                            <h5 class="fw-bold text-lk-primary mb-2">
                                <i class="bi bi-tags-fill text-lk-secondary me-2"></i>5. Layanan &amp; Tarif Harga
                            </h5>
                            <p class="text-muted small mb-3">Pilih jenis layanan yang Anda sediakan dan tentukan harga per unitnya.</p>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">Pilih</th>
                                            <th>Jenis Layanan</th>
                                            <th style="width: 140px;">Satuan</th>
                                            <th style="width: 200px;">Harga Satuan (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $existingPrices = $store ? $store->servicePrices->keyBy('service_id') : collect();
                                        @endphp
                                        @foreach($availableServices as $svc)
                                            @php
                                                $svcPrice = $existingPrices->get($svc->id);
                                                $isChecked = old("services.{$svc->id}.selected", $svcPrice ? 1 : ($loop->first ? 1 : 0));
                                                $priceVal = old("services.{$svc->id}.price", $svcPrice?->price_per_unit ?? 10000);
                                                $unitVal = old("services.{$svc->id}.unit", $svcPrice?->unit ?? (str_contains(strtolower($svc->name), 'satuan') ? 'item' : 'kg'));
                                            @endphp
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="checkbox" name="services[{{ $svc->id }}][selected]" value="1" {{ $isChecked ? 'checked' : '' }}>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">{{ $svc->name }}</div>
                                                    @if($svc->description)
                                                        <div class="text-muted" style="font-size:0.75rem;">{{ $svc->description }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <select name="services[{{ $svc->id }}][unit]" class="form-select form-select-sm">
                                                        <option value="kg" {{ $unitVal === 'kg' ? 'selected' : '' }}>/ Kilogram (kg)</option>
                                                        <option value="item" {{ $unitVal === 'item' ? 'selected' : '' }}>/ Satuan (Pcs)</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">Rp</span>
                                                        <input type="number" name="services[{{ $svc->id }}][price]" class="form-control" value="{{ $priceVal }}" min="0" step="500">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Section 6: Action Buttons --}}
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary btn-lg px-4">
                                <i class="bi bi-save me-1"></i>Simpan Draft
                            </button>
                            <button type="submit" name="action" value="submit" class="btn btn-lk-primary btn-lg px-5 shadow">
                                <i class="bi bi-send-check-fill me-2"></i>Kirim Pendaftaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endguest
</div>
@endsection

@push('scripts')
@auth
    @if(!$store || in_array($store->status, ['draft', 'revision_required', 'rejected']) || request('mode') === 'edit' || $isNewMode)
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var latInput = document.getElementById('inputLat');
            var lngInput = document.getElementById('inputLng');
            var initLat = parseFloat(latInput.value) || -6.2088;
            var initLng = parseFloat(lngInput.value) || 106.8456;

            var map = L.map('onboardMap').setView([initLat, initLng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);

            function updateInputs(lat, lng) {
                latInput.value = lat.toFixed(6);
                lngInput.value = lng.toFixed(6);
            }

            marker.on('dragend', function(e) {
                var position = marker.getLatLng();
                updateInputs(position.lat, position.lng);
            });

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateInputs(e.latlng.lat, e.latlng.lng);
            });

            latInput.addEventListener('change', function() {
                var newLat = parseFloat(latInput.value);
                var newLng = parseFloat(lngInput.value);
                if (!isNaN(newLat) && !isNaN(newLng)) {
                    marker.setLatLng([newLat, newLng]);
                    map.panTo([newLat, newLng]);
                }
            });

            lngInput.addEventListener('change', function() {
                var newLat = parseFloat(latInput.value);
                var newLng = parseFloat(lngInput.value);
                if (!isNaN(newLat) && !isNaN(newLng)) {
                    marker.setLatLng([newLat, newLng]);
                    map.panTo([newLat, newLng]);
                }
            });

            document.getElementById('btnGetLocation')?.addEventListener('click', function() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        var lat = pos.coords.latitude;
                        var lng = pos.coords.longitude;
                        marker.setLatLng([lat, lng]);
                        map.setView([lat, lng], 15);
                        updateInputs(lat, lng);
                    }, function(err) {
                        alert('Tidak dapat mendeteksi lokasi GPS: ' + err.message);
                    });
                } else {
                    alert('Browser Anda tidak mendukung geolokasi GPS.');
                }
            });
        });
    </script>
    @endif
@endauth
@endpush

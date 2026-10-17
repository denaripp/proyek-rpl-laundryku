@extends('layouts.app')

@section('title', $store->name . ' — LaundryKu')
@section('description', $store->description ?? 'Laundry ' . $store->name)

@section('content')
    <div class="container py-4">
        {{-- Header --}}
        <div class="lk-card p-4 mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h1 class="h3 fw-bold mb-0 text-lk-primary">{{ $store->name }}</h1>
                        <span class="badge lk-badge-tertiary"><i class="bi bi-star-fill"></i> {{ number_format((float) $store->average_rating, 1) }}</span>
                        <span class="text-muted small">({{ $store->review_count ?? 0 }} ulasan)</span>
                    </div>
                    <p class="text-muted mb-1"><i class="bi bi-geo-alt me-1"></i>{{ $store->address }}</p>
                    @if($store->phone)
                        <p class="text-muted mb-0"><i class="bi bi-telephone me-1"></i>{{ $store->phone }}</p>
                    @endif
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="{{ route('checkout', $store) }}" class="btn btn-lk-primary btn-lg w-100 w-md-auto">
                        <i class="bi bi-cart-plus me-1"></i>Pesan Sekarang
                    </a>
                </div>
            </div>
            @if($store->description)
                <p class="mt-3 mb-0">{{ $store->description }}</p>
            @endif
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Layanan & harga --}}
                <div class="lk-card p-4 mb-4">
                    <h4 class="fw-bold mb-3 text-lk-primary">Layanan & Harga</h4>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Layanan</th>
                                    <th>Unit</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-end">Min.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($services as $sp)
                                    <tr>
                                        <td>{{ $sp->service->name ?? '-' }}</td>
                                        <td>{{ $sp->unit ?? 'kg' }}</td>
                                        <td class="text-end fw-semibold">Rp {{ number_format((float) $sp->price_per_unit, 0, ',', '.') }}</td>
                                        <td class="text-end text-muted small">{{ $sp->min_qty ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada layanan terdaftar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Reviews --}}
                <div class="lk-card p-4">
                    <h4 class="fw-bold mb-3 text-lk-primary">Ulasan Pelanggan</h4>
                    @forelse($reviews as $r)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $r->customer->name ?? 'Anonim' }}</strong>
                                <span class="text-warning small">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= $r->rating ? '-fill' : '' }}"></i>
                                    @endfor
                                </span>
                            </div>
                            <p class="mb-0 mt-1">{{ $r->comment }}</p>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">Belum ada ulasan.</p>
                    @endforelse
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Jam Operasional --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-lk-primary"><i class="bi bi-clock me-1"></i>Jam Operasional</h5>
                    @php
                        $days = ['monday' => 'Senin', 'tuesday' => 'Selasa', 'wednesday' => 'Rabu', 'thursday' => 'Kamis', 'friday' => 'Jumat', 'saturday' => 'Sabtu', 'sunday' => 'Minggu'];
                    @endphp
                    <ul class="list-unstyled small mb-0">
                        @foreach($days as $key => $label)
                            @php $row = $hours[$key] ?? null; @endphp
                            <li class="d-flex justify-content-between py-1">
                                <span>{{ $label }}</span>
                                <span>
                                    @if($row && (($row['open'] ?? null) || ($row['closed'] ?? null)))
                                        @if(!empty($row['is_closed']))
                                            <span class="text-muted">Tutup</span>
                                        @else
                                            {{ $row['open'] ?? '08:00' }} - {{ $row['closed'] ?? '20:00' }}
                                        @endif
                                    @else
                                        <span class="text-muted">08:00 - 20:00</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Biaya --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold mb-3 text-lk-primary"><i class="bi bi-cash me-1"></i>Biaya</h5>
                    <ul class="list-unstyled small mb-0">
                        <li class="d-flex justify-content-between py-1">
                            <span>Jemput</span>
                            <span>Rp {{ number_format((float) $store->pickup_fee, 0, ',', '.') }}</span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span>Antar</span>
                            <span>Rp {{ number_format((float) $store->delivery_fee, 0, ',', '.') }}</span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span>Min. order</span>
                            <span>Rp {{ number_format((float) $store->minimum_order_amount, 0, ',', '.') }}</span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span>COD</span>
                            <span>{{ $store->cod_enabled ? 'Tersedia' : 'Tidak tersedia' }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

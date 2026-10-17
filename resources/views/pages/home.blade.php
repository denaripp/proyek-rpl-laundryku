@extends('layouts.app')

@section('title', 'LaundryKu — Laundry Online Terdekat, Antar-Jemput')
@section('description', 'Cari laundry terdekat, bandingkan harga, dan pesan antar-jemput dari rumah. Pembayaran Xendit/QRIS/COD.')

@section('content')
    {{-- Hero --}}
    <section class="lk-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 text-center text-lg-start">
                    <h1 class="display-5 mb-3">Laundry Bersih, Tanpa Repot. Tinggal Klik.</h1>
                    <p class="lead mb-4">Cari laundry terdekat, bandingkan harga & rating, pesan antar-jemput, bayar online atau COD.</p>

                    <form action="{{ route('marketplace.index') }}" method="GET" class="lk-search-form">
                        <div class="input-group input-group-lg shadow-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control" placeholder="Cari nama laundry atau area..." value="{{ request('q') }}">
                            <button class="btn btn-lk-tertiary px-4" type="submit">Cari Laundry</button>
                        </div>
                    </form>

                    <div class="d-flex gap-2 mt-3 justify-content-center justify-content-lg-start flex-wrap">
                        <a href="{{ route('marketplace.index') }}" class="btn btn-light btn-lg">
                            <i class="bi bi-shop me-1"></i>Cari Laundry
                        </a>
                        <a href="{{ route('store.onboard') }}" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-plus-circle me-1"></i>Daftarkan Laundry
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 d-none d-lg-block text-center">
                    <i class="bi bi-droplet-fill" style="font-size: 12rem; opacity: .25;"></i>
                </div>
            </div>
        </div>
    </section>

    {{-- Cara pesan --}}
    <section class="container py-5" id="cara-pesan">
        <h2 class="text-center fw-bold mb-4 text-lk-primary">Cara Pesan</h2>
        <div class="row g-4">
            @foreach ([
                ['bi-search', '1. Cari Laundry', 'Temukan laundry terdekat berdasarkan lokasi, rating, dan harga.'],
                ['bi-cart-check', '2. Pilih Layanan', 'Tentukan jenis layanan, berat, dan alamat jemput.'],
                ['bi-truck', '3. Kurir Jemput', 'Kurir kami jemput cucian ke pintu rumah Anda.'],
                ['bi-house-check', '4. Selesai & Bayar', 'Laundry diantar kembali, bayar via Xendit / QRIS / COD.'],
            ] as $item)
                <div class="col-md-6 col-lg-3">
                    <div class="lk-card p-4 text-center h-100">
                        <i class="bi {{ $item[0] }} text-lk-secondary" style="font-size: 2.5rem;"></i>
                        <h5 class="mt-3 mb-2">{{ $item[1] }}</h5>
                        <p class="text-muted small mb-0">{{ $item[2] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Top Stores --}}
    @if($topStores->isNotEmpty())
        <section class="bg-white py-5">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h2 class="fw-bold text-lk-primary mb-1">Laundry Pilihan</h2>
                        <p class="text-muted mb-0">Rating tertinggi di sekitar Anda</p>
                    </div>
                    <a href="{{ route('marketplace.index') }}" class="text-lk-secondary">Lihat semua <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="row g-3">
                    @foreach($topStores as $store)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.store-card', ['store' => $store])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA Owner --}}
    <section class="py-5">
        <div class="container">
            <div class="lk-card p-4 p-md-5 text-center" style="background: linear-gradient(135deg, var(--lk-primary), var(--lk-secondary)); color: #fff;">
                <h2 class="fw-bold">Punya Usaha Laundry?</h2>
                <p class="lead mb-4">Daftarkan laundry Anda, dapatkan lebih banyak pelanggan. Gratis untuk pendaftar awal.</p>
                <a href="{{ route('store.onboard') }}" class="btn btn-light btn-lg">
                    <i class="bi bi-plus-circle me-1"></i>Daftarkan Laundry Anda
                </a>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="bg-white py-5" id="faq">
        <div class="container">
            <h2 class="text-center fw-bold mb-4 text-lk-primary">Pertanyaan Umum</h2>
            <div class="accordion" id="faqAcc">
                @foreach ([
                    'Bagaimana cara pesan laundry?' => 'Cari laundry di halaman Marketplace, pilih layanan, isi alamat jemput & antar, lalu pilih pembayaran.',
                    'Metode pembayaran apa saja?' => 'Xendit (virtual account, e-wallet), QRIS, dan COD (bayar di tempat).',
                    'Berapa biaya antar-jemput?' => 'Tergantung laundry. Biasanya Rp 5.000–15.000 per arah. Ditampilkan di ringkasan checkout.',
                    'Bagaimana jika cucian hilang/rusak?' => 'Dilindungi oleh kebijakan ganti rugi LaundryKu. Ajukan komplain di halaman tracking, tim kami akan mediasi.',
                    'Apakah bisa antar sendiri tanpa jemput?' => 'Bisa. Pilih "Antar sendiri" saat checkout.',
                ] as $q => $a)
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $loop->index }}">
                                {{ $q }}
                            </button>
                        </h2>
                        <div id="faq-{{ $loop->index }}" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                            <div class="accordion-body">{{ $a }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

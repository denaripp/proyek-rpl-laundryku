@extends('layouts.app')

@section('title', 'Pembayaran — ' . $order->order_number)

@section('content')
    <div class="container py-4">
        <h1 class="h4 fw-bold text-lk-primary mb-3">Pembayaran Order #{{ $order->order_number }}</h1>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="lk-card p-4">
                    @if($order->payment)
                        @if($order->payment->status === 'paid')
                            <div class="text-center py-4">
                                <i class="bi bi-check-circle-fill text-lk-tertiary" style="font-size:4rem"></i>
                                <h3 class="mt-3">Pembayaran Berhasil</h3>
                                <p class="text-muted">Terima kasih! Pesanan Anda sedang diproses.</p>
                                <a href="{{ route('order.tracking', $order) }}" class="btn btn-lk-primary mt-2">Lihat Tracking</a>
                            </div>
                        @else
                            {{-- Xendit / QRIS / COD --}}
                            @php $pm = $order->payment->payment_method; @endphp
                            @if($pm === 'xendit' || $pm === 'qris')
                                <h5 class="fw-bold text-lk-primary mb-3">Instruksi Pembayaran</h5>
                                <div class="alert alert-warning d-flex align-items-center">
                                    <i class="bi bi-clock me-2"></i>
                                    <div>
                                        Selesaikan pembayaran sebelum:
                                        <strong id="lkExpText">{{ optional($order->payment->expires_at)->format('d M Y H:i') }}</strong>
                                        <div class="lk-pulse small" id="lkCountdown">--:--:--</div>
                                    </div>
                                </div>

                                @if($pm === 'qris' && $order->payment->qr_url)
                                    <div class="text-center my-3">
                                        <img src="{{ $order->payment->qr_url }}" alt="QRIS" style="max-width: 240px; border: 1px solid #e5e7eb; padding: .5rem; border-radius: 8px;">
                                        <p class="small text-muted mt-2">Scan QR di atas dengan e-wallet Anda.</p>
                                    </div>
                                @elseif($pm === 'xendit' && $order->payment->va_number)
                                    <p>Transfer ke Virtual Account:</p>
                                    <div class="bg-light p-3 rounded mb-3 text-center">
                                        <div class="small text-muted">{{ strtoupper($order->payment->bank_code ?? 'BCA') }}</div>
                                        <div class="fs-4 fw-bold" style="font-family: monospace;">{{ $order->payment->va_number }}</div>
                                        <div class="text-muted small">a/n LaundryKu</div>
                                    </div>
                                @endif

                                <div class="d-grid gap-2">
                                    <button class="btn btn-lk-primary" id="lkCekBtn" onclick="lkCekStatus()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Cek Status Pembayaran
                                    </button>
                                    <a href="{{ route('order.tracking', $order) }}" class="btn btn-outline-secondary">Lihat Tracking</a>
                                </div>
                            @elseif($pm === 'cod')
                                <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-cash-coin me-1"></i>Bayar di Tempat (COD)</h5>
                                <p>Siapkan uang tunai sejumlah:</p>
                                <div class="bg-light p-3 rounded text-center mb-3">
                                    <div class="fs-3 fw-bold text-lk-primary">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</div>
                                </div>
                                <p class="text-muted small">Bayar kepada kurir saat cucian diantar. Mohon siapkan uang pas.</p>
                                <a href="{{ route('order.tracking', $order) }}" class="btn btn-lk-primary w-100">Lihat Tracking</a>
                            @endif
                        @endif
                    @else
                        <p>Tidak ada data pembayaran.</p>
                    @endif
                </div>
            </div>

            <div class="col-lg-5">
                <div class="lk-card p-4" style="position:sticky; top: 90px;">
                    <h5 class="fw-bold text-lk-primary mb-3">Ringkasan</h5>
                    <div class="d-flex justify-content-between py-1 small">
                        <span>Order</span><span class="fw-semibold">#{{ $order->order_number }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 small">
                        <span>Laundry</span><span>{{ $order->store->name ?? '-' }}</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span class="text-lk-primary">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function lkCekStatus() {
    const btn = document.getElementById('lkCekBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengecek...';
    axios.get('/api/orders/{{ $order->id }}')
        .then(r => {
            if (r.data.payment?.status === 'paid') {
                window.location.reload();
            } else {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Cek Status Pembayaran';
            }
        })
        .catch(() => {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Cek Status Pembayaran';
        });
}

// Countdown timer
(function () {
    const exp = new Date('{{ optional($order->payment?->expires_at)->toIso8601String() }}');
    const el = document.getElementById('lkCountdown');
    if (!exp || isNaN(exp)) return;
    function tick() {
        const diff = exp - new Date();
        if (diff <= 0) { el.textContent = 'Kedaluwarsa'; el.classList.remove('lk-pulse'); return; }
        const h = String(Math.floor(diff / 3.6e6)).padStart(2, '0');
        const m = String(Math.floor((diff % 3.6e6) / 6e4)).padStart(2, '0');
        const s = String(Math.floor((diff % 6e4) / 1000)).padStart(2, '0');
        el.textContent = `${h}:${m}:${s}`;
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush

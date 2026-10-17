@extends('layouts.app')

@section('title', 'Tracking Order — ' . $order->order_number)
@section('description', 'Lacak status laundry Anda secara realtime.')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 fw-bold text-lk-primary mb-0">Order #{{ $order->order_number }}</h1>
            <a href="{{ route('order.payment', $order) }}" class="btn btn-lk-tertiary">
                <i class="bi bi-credit-card me-1"></i>Bayar
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                {{-- Timeline --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-list-check me-1"></i>Status</h5>
                    @php
                        $stages = [
                            'pending' => 'Pesanan Dibuat',
                            'confirmed' => 'Dikonfirmasi Laundry',
                            'pickup_assigned' => 'Kurir Ditugaskan',
                            'picked_up' => 'Cucian Dijemput',
                            'in_process' => 'Sedang Diproses',
                            'ready' => 'Siap Diantar',
                            'delivering' => 'Dalam Pengantaran',
                            'completed' => 'Selesai',
                        ];
                        $currentIdx = array_search($order->status, array_keys($stages));
                        $isCancelled = $order->status === 'cancelled';
                    @endphp

                    @if($isCancelled)
                        <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>Pesanan dibatalkan.</div>
                    @else
                        <ul class="lk-timeline">
                            @foreach($stages as $key => $label)
                                @php
                                    $idx = $loop->index;
                                    $cls = $idx < $currentIdx ? 'done' : ($idx === $currentIdx ? 'active' : '');
                                @endphp
                                <li class="{{ $cls }}">
                                    <strong>{{ $label }}</strong>
                                    @php $hist = $order->statusHistories->where('status', $key)->first(); @endphp
                                    @if($hist)
                                        <div class="small text-muted">{{ $hist->created_at->format('d M Y H:i') }} — {{ $hist->notes ?? '' }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Item --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold text-lk-primary mb-3">Item</h5>
                    <ul class="list-unstyled mb-0">
                        @foreach($order->items as $it)
                            <li class="d-flex justify-content-between border-bottom py-2">
                                <span>{{ $it->service->name ?? '-' }} <small class="text-muted ms-2">{{ $it->quantity }} {{ $it->unit }}</small></span>
                                <span>Rp {{ number_format((float) $it->total_price, 0, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="col-lg-5">
                {{-- Map realtime --}}
                <div class="lk-card p-3 mb-4">
                    <h5 class="fw-bold text-lk-primary"><i class="bi bi-geo me-1"></i>Posisi Kurir</h5>
                    <div class="lk-map" id="lkCourierMap" style="height: 350px;">
                        <div class="lk-map-fallback" id="lkCourierFallback">
                            <i class="bi bi-truck" style="font-size:2.5rem"></i>
                            <p class="mt-2 mb-0">Posisi kurir akan tampil di sini saat kurir mulai bergerak.</p>
                        </div>
                    </div>
                </div>

                {{-- Berat & harga --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold text-lk-primary mb-3">Penimbangan</h5>
                    <div class="d-flex justify-content-between py-1">
                        <span>Berat estimasi</span><span>{{ $order->estimated_weight ?? '-' }} kg</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>Berat aktual</span><strong>{{ $order->actual_weight ? $order->actual_weight . ' kg' : 'Belum ditimbang' }}</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Total</span>
                        <span class="text-lk-primary" id="lkOrderTotal">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</span>
                    </div>
                    @if($order->actual_weight)
                        <small class="text-muted">* Total sudah dihitung dari berat aktual</small>
                    @endif
                </div>

                {{-- Pembayaran --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold text-lk-primary mb-3">Pembayaran</h5>
                    @if($order->payment)
                        <p class="mb-1">Metode: <strong>{{ strtoupper($order->payment->payment_method) }}</strong></p>
                        <p class="mb-1">Status:
                            <span class="lk-status-pill lk-status-{{ $order->payment->status === 'paid' ? 'completed' : 'pending' }}">
                                {{ $order->payment->status }}
                            </span>
                        </p>
                    @else
                        <p class="text-muted mb-0">Belum ada pembayaran.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const orderId = {{ $order->id }};
    const mapEl = document.getElementById('lkCourierMap');
    const fallbackEl = document.getElementById('lkCourierFallback');

    // Leaflet (default). Ganti ke Google Maps kalau window.LK.mapsKey ada.
    let map, marker;
    function initLeaflet(lat, lng) {
        if (map) return;
        mapEl.innerHTML = '';
        map = L.map(mapEl).setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM', maxZoom: 19 }).addTo(map);
        marker = L.marker([lat, lng]).addTo(map).bindPopup('Kurir di sini').openPopup();
    }

    // Listen Reverb channel (kalau ada). Fallback: poll tiap 15 detik ke API.
    function startPolling() {
        let lastLat = null, lastLng = null;
        const tick = async () => {
            try {
                const { data } = await axios.get('/api/courier-tasks?order_id=' + orderId);
                const task = (data.data || data || []).find(t => t.order_id === orderId);
                if (task && task.current_lat && task.current_lng) {
                    lastLat = parseFloat(task.current_lat);
                    lastLng = parseFloat(task.current_lng);
                    initLeaflet(lastLat, lastLng);
                    marker.setLatLng([lastLat, lastLng]);
                }
            } catch (e) { /* silent */ }
        };
        tick();
        setInterval(tick, 15000);
    }

    // Try Reverb first
    if (window.Echo) {
        try {
            window.Echo.channel('order.' + orderId)
                .listen('CourierLocationUpdated', (e) => {
                    if (e.lat && e.lng) {
                        initLeaflet(e.lat, e.lng);
                        marker.setLatLng([e.lat, e.lng]);
                    }
                });
        } catch (e) { /* fallthrough to polling */ }
    }
    startPolling();

    // Also poll order status (Xendit may flip payment status async)
    setInterval(async () => {
        try {
            const { data } = await axios.get('/api/orders/' + orderId);
            if (data.actual_weight) {
                document.getElementById('lkOrderTotal').textContent =
                    'Rp ' + parseFloat(data.total_amount).toLocaleString('id-ID');
            }
        } catch (e) {}
    }, 20000);
})();
</script>
@endpush

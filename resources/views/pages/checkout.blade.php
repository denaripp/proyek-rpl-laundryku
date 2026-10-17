@extends('layouts.app')

@section('title', 'Checkout — ' . $store->name)
@section('description', 'Pilih layanan, alamat jemput & antar, dan metode pembayaran.')

@section('content')
    <div class="container py-4">
        <h1 class="h3 fw-bold text-lk-primary mb-3">Checkout — {{ $store->name }}</h1>

        <form id="lkCheckoutForm" class="row g-4">
            @csrf
            <input type="hidden" name="store_id" value="{{ $store->id }}">

            <div class="col-lg-8">
                {{-- Pilih Layanan --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-basket me-1"></i>1. Pilih Layanan</h5>
                    <div id="lkServiceList">
                        @foreach($services as $sp)
                            <div class="form-check border-bottom py-2">
                                <input class="form-check-input lk-svc" type="checkbox" name="items[{{ $loop->index }}][service_id]" value="{{ $sp->service_id }}" id="svc-{{ $sp->id }}" data-price="{{ $sp->price_per_unit }}" data-unit="{{ $sp->unit ?? 'kg' }}">
                                <label class="form-check-label d-flex justify-content-between w-100" for="svc-{{ $sp->id }}">
                                    <span>
                                        <strong>{{ $sp->service->name ?? '-' }}</strong>
                                        <small class="text-muted ms-2">{{ $sp->unit ?? 'kg' }}</small>
                                    </span>
                                    <span class="text-lk-primary fw-semibold">Rp {{ number_format((float) $sp->price_per_unit, 0, ',', '.') }}/{{ $sp->unit ?? 'kg' }}</span>
                                </label>
                                <input type="number" name="items[{{ $loop->index }}][quantity]" min="0.5" step="0.5" value="1" class="form-control form-control-sm mt-1 lk-svc-qty" data-for="svc-{{ $sp->id }}" style="max-width: 120px;" disabled>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Alamat --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-geo-alt me-1"></i>2. Alamat</h5>

                    @auth
                        @if($addresses->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label small">Pilih alamat tersimpan</label>
                                <select class="form-select" id="lkAddressSelect">
                                    <option value="">-- Pilih --</option>
                                    @foreach($addresses as $a)
                                        <option value="{{ $a->id }}" data-addr="{{ $a->address }}" data-lat="{{ $a->latitude }}" data-lng="{{ $a->longitude }}">{{ $a->label }} — {{ \Illuminate\Support\Str::limit($a->address, 40) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @endauth

                    <div class="mb-2">
                        <label class="form-label small">Alamat jemput</label>
                        <textarea class="form-control" name="pickup_address" rows="2" required placeholder="Jl., No., RT/RW, Kelurahan, Kecamatan, Kota"></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="lkSameAddr" checked>
                        <label class="form-check-label" for="lkSameAddr">Alamat antar sama dengan alamat jemput</label>
                    </div>
                    <div class="mb-2" id="lkDeliveryWrap" style="display:none;">
                        <label class="form-label small">Alamat antar</label>
                        <textarea class="form-control" name="delivery_address" rows="2" placeholder="Alamat antar"></textarea>
                    </div>
                </div>

                {{-- Pembayaran --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-credit-card me-1"></i>3. Pembayaran</h5>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input class="btn-check" type="radio" name="payment_method" id="pm-xendit" value="xendit" checked>
                            <label class="btn btn-outline-lk-primary w-100 py-3" for="pm-xendit">
                                <i class="bi bi-bank d-block fs-4"></i> Xendit (VA)
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input class="btn-check" type="radio" name="payment_method" id="pm-qris" value="qris">
                            <label class="btn btn-outline-lk-primary w-100 py-3" for="pm-qris">
                                <i class="bi bi-qr-code d-block fs-4"></i> QRIS
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input class="btn-check" type="radio" name="payment_method" id="pm-cod" value="cod" {{ $store->cod_enabled ? '' : 'disabled' }}>
                            <label class="btn btn-outline-lk-primary w-100 py-3 {{ $store->cod_enabled ? '' : 'disabled' }}" for="pm-cod">
                                <i class="bi bi-cash-coin d-block fs-4"></i> COD
                                @if(!$store->cod_enabled)<small class="d-block text-muted">Tidak tersedia</small>@endif
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ringkasan --}}
            <div class="col-lg-4">
                <div class="lk-card p-4" style="position:sticky; top: 90px;">
                    <h5 class="fw-bold text-lk-primary mb-3">Ringkasan</h5>
                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1"><span>Subtotal</span><span id="lkSubtotal">Rp 0</span></li>
                        <li class="d-flex justify-content-between py-1"><span>Jemput</span><span>Rp {{ number_format((float) $store->pickup_fee, 0, ',', '.') }}</span></li>
                        <li class="d-flex justify-content-between py-1"><span>Antar</span><span>Rp {{ number_format((float) $store->delivery_fee, 0, ',', '.') }}</span></li>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                        <span>Total</span><span id="lkTotal" class="text-lk-primary">Rp 0</span>
                    </div>
                    <button type="submit" class="btn btn-lk-tertiary w-100 btn-lg" id="lkSubmit">
                        <i class="bi bi-check-circle me-1"></i>Buat Pesanan
                    </button>
                    <p class="text-muted small mt-2 mb-0 text-center">* Estimasi. Harga final setelah penimbangan.</p>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('lkCheckoutForm');
    const subtotalEl = document.getElementById('lkSubtotal');
    const totalEl = document.getElementById('lkTotal');
    const pickupFee = {{ (float) $store->pickup_fee }};
    const deliveryFee = {{ (float) $store->delivery_fee }};
    const sameAddr = document.getElementById('lkSameAddr');
    const deliveryWrap = document.getElementById('lkDeliveryWrap');

    sameAddr.addEventListener('change', () => deliveryWrap.style.display = sameAddr.checked ? 'none' : '');

    document.querySelectorAll('.lk-svc').forEach(cb => {
        cb.addEventListener('change', () => {
            const qty = document.querySelector(`.lk-svc-qty[data-for="${cb.id}"]`);
            qty.disabled = !cb.checked;
            if (!cb.checked) qty.value = 1;
            recalc();
        });
    });
    document.querySelectorAll('.lk-svc-qty').forEach(q => q.addEventListener('input', recalc));

    document.getElementById('lkAddressSelect')?.addEventListener('change', e => {
        const opt = e.target.selectedOptions[0];
        if (opt && opt.dataset.addr) {
            form.querySelector('[name=pickup_address]').value = opt.dataset.addr;
        }
    });

    function recalc() {
        let sub = 0;
        document.querySelectorAll('.lk-svc:checked').forEach(cb => {
            const qty = parseFloat(document.querySelector(`.lk-svc-qty[data-for="${cb.id}"]`).value) || 0;
            sub += qty * parseFloat(cb.dataset.price);
        });
        subtotalEl.textContent = 'Rp ' + sub.toLocaleString('id-ID');
        totalEl.textContent = 'Rp ' + (sub + pickupFee + deliveryFee).toLocaleString('id-ID');
    }
    recalc();

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('lkSubmit');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

        // Normalize items: keep only checked
        const fd = new FormData(form);
        const items = [];
        const seenSvc = new Set();
        document.querySelectorAll('.lk-svc').forEach((cb, i) => {
            if (!cb.checked) return;
            if (seenSvc.has(cb.value)) return;
            seenSvc.add(cb.value);
            const qty = parseFloat(document.querySelector(`.lk-svc-qty[data-for="${cb.id}"]`).value) || 0;
            items.push({ service_id: parseInt(cb.value), quantity: qty });
        });

        const payload = {
            store_id: parseInt(fd.get('store_id')),
            pickup_address: fd.get('pickup_address'),
            delivery_address: sameAddr.checked ? fd.get('pickup_address') : fd.get('delivery_address'),
            payment_method: fd.get('payment_method'),
            items,
        };

        try {
            const { data } = await axios.post('/api/orders', payload);
            window.location.href = '/orders/' + data.id + '/payment';
        } catch (err) {
            const msg = err.response?.data?.message || 'Gagal membuat pesanan. Coba lagi.';
            alert(typeof msg === 'string' ? msg : JSON.stringify(msg));
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Buat Pesanan';
        }
    });
})();
</script>
@endpush

@extends('layouts.app')

@section('title', 'Dashboard — ' . auth()->user()->name)

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-lk-primary mb-0">Halo, {{ auth()->user()->name }}</h1>
                <p class="text-muted mb-0">Pantau pesanan dan kelola akun Anda.</p>
            </div>
            <a href="{{ route('marketplace.index') }}" class="btn btn-lk-primary">
                <i class="bi bi-plus-circle me-1"></i>Pesan Lagi
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                {{-- Active Orders --}}
                <div class="lk-card p-4 mb-4">
                    <h5 class="fw-bold text-lk-primary mb-3"><i class="bi bi-arrow-repeat me-1"></i>Pesanan Aktif</h5>
                    @forelse($activeOrders as $o)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <strong>{{ $o->store->name ?? '-' }}</strong>
                                <small class="text-muted ms-2">#{{ $o->order_number }}</small>
                                <div class="small text-muted">{{ $o->created_at->diffForHumans() }}</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="lk-status-pill lk-status-{{ $o->status === 'completed' ? 'completed' : ($o->status === 'cancelled' ? 'cancelled' : 'process') }}">
                                    {{ $o->status }}
                                </span>
                                <a href="{{ route('order.tracking', $o) }}" class="btn btn-sm btn-outline-lk-primary">Lacak</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">Tidak ada pesanan aktif.</p>
                    @endforelse
                </div>

                {{-- Recent Orders --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold text-lk-primary mb-3">Pesanan Terbaru</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order</th>
                                    <th>Laundry</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $o)
                                    <tr>
                                        <td><a href="{{ route('order.tracking', $o) }}" class="text-decoration-none">#{{ $o->order_number }}</a></td>
                                        <td>{{ $o->store->name ?? '-' }}</td>
                                        <td><small class="text-muted">{{ $o->created_at->format('d M Y') }}</small></td>
                                        <td class="text-end">Rp {{ number_format((float) $o->total_amount, 0, ',', '.') }}</td>
                                        <td><span class="lk-status-pill lk-status-{{ $o->status === 'completed' ? 'completed' : ($o->status === 'cancelled' ? 'cancelled' : 'process') }}">{{ $o->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Belum ada pesanan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                {{-- Saved Addresses --}}
                <div class="lk-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-lk-primary mb-0">Alamat Tersimpan</h5>
                        <button class="btn btn-sm btn-outline-lk-primary" data-bs-toggle="modal" data-bs-target="#lkAddAddrModal">+ Tambah</button>
                    </div>
                    @forelse($addresses as $a)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $a->label }}</strong>
                                @if($a->is_default)<span class="badge lk-badge-tertiary">Default</span>@endif
                            </div>
                            <small class="text-muted">{{ $a->address }}</small>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 small">Belum ada alamat tersimpan.</p>
                    @endforelse
                </div>

                {{-- Notifications --}}
                <div class="lk-card p-4">
                    <h5 class="fw-bold text-lk-primary mb-3">Notifikasi</h5>
                    @forelse($notifications as $n)
                        <div class="border-bottom py-2">
                            <div class="small">{{ $n->title ?? $n->data['title'] ?? 'Notifikasi' }}</div>
                            <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0 small">Belum ada notifikasi.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Modal tambah alamat --}}
    <div class="modal fade" id="lkAddAddrModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="lkAddAddrForm">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Alamat</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Label</label>
                        <input class="form-control" name="label" required placeholder="Rumah / Kantor">
                    </div>
                    <div class="mb-2"><label class="form-label small">Alamat</label>
                        <textarea class="form-control" name="address" rows="2" required></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col"><label class="form-label small">Latitude</label>
                            <input class="form-control" name="latitude" type="number" step="0.00000001">
                        </div>
                        <div class="col"><label class="form-label small">Longitude</label>
                            <input class="form-control" name="longitude" type="number" step="0.00000001">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="is_default" id="lkDef">
                        <label class="form-check-label" for="lkDef">Jadikan default</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-lk-primary" type="submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.getElementById('lkAddAddrForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const payload = {
        label: fd.get('label'),
        address: fd.get('address'),
        latitude: parseFloat(fd.get('latitude')) || 0,
        longitude: parseFloat(fd.get('longitude')) || 0,
        is_default: fd.get('is_default') === 'on',
    };
    try {
        await axios.post('/api/customer/addresses', payload);
        bootstrap.Modal.getInstance(document.getElementById('lkAddAddrModal')).hide();
        window.location.reload();
    } catch (err) {
        alert('Gagal menyimpan alamat.');
    }
});
</script>
@endpush

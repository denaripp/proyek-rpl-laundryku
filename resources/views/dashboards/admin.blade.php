@extends('layouts.dashboard')
@section('title', $isOwner ? 'Platform Owner Dashboard' : 'Platform Admin Dashboard')
@section('sidebar')
    @include('partials.sidebar', [
        'activeKey' => $tab ?? 'overview',
        'menu' => $menu,
        'sidebarLabel' => $isOwner ? 'Platform Owner' : 'Platform Admin',
    ])
@endsection

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 fw-bold text-lk-primary mb-1">
                @if(($tab ?? 'overview') === 'overview')
                    {{ $isOwner ? 'Platform Owner Overview' : 'Platform Admin Overview' }}
                @elseif($tab === 'stores')
                    Manajemen Toko &amp; Approval
                @elseif($tab === 'orders')
                    Daftar Semua Pesanan
                @elseif($tab === 'customers')
                    Daftar Pengguna / Pelanggan
                @elseif($tab === 'couriers')
                    Daftar Mitra Kurir
                @elseif($tab === 'payments')
                    Riwayat Transaksi Pembayaran
                @elseif($tab === 'refunds')
                    Manajemen Pengembalian Dana (Refund)
                @elseif($tab === 'disputes')
                    Penyelesaian Sengketa (Disputes)
                @elseif($tab === 'audit')
                    Audit Log Aktivitas Sistem
                @elseif($tab === 'commission')
                    Pengaturan Komisi Platform
                @elseif($tab === 'ai')
                    Monitoring Penggunaan AI Assistant
                @else
                    {{ ucfirst($tab) }}
                @endif
            </h1>
            <p class="text-muted mb-0 small">Dashboard manajemen terpadu platform LaundryKu</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if($isOwner)
                <span class="badge bg-dark px-3 py-2 fs-7"><i class="bi bi-shield-lock-fill me-1 text-warning"></i>OWNER</span>
            @else
                <span class="badge bg-primary px-3 py-2 fs-7"><i class="bi bi-person-badge-fill me-1"></i>ADMIN</span>
            @endif
        </div>
    </div>

    {{-- ================= TAB: OVERVIEW ================= --}}
    @if(($tab ?? 'overview') === 'overview')
        <h6 class="text-muted text-uppercase small fw-bold mt-2 mb-2"><i class="bi bi-cash-stack me-1"></i>Finansial</h6>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="lk-stat-card tertiary">
                    <div class="label">GMV Hari Ini</div>
                    <div class="value">Rp {{ number_format($metrics['gmv_today'], 0, ',', '.') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">GMV Bulan Ini</div>
                    <div class="value">Rp {{ number_format($metrics['gmv_month'], 0, ',', '.') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card secondary">
                    <div class="label">COD Hari Ini</div>
                    <div class="value">Rp {{ number_format($metrics['cod_today'], 0, ',', '.') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Online Hari Ini</div>
                    <div class="value">Rp {{ number_format($metrics['online_today'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-bold mt-3 mb-2"><i class="bi bi-gear-fill me-1"></i>Operasional</h6>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Pesanan Hari Ini</div>
                    <div class="value">{{ $metrics['orders_today'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Dalam Proses</div>
                    <div class="value">{{ $metrics['orders_in_progress'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card secondary">
                    <div class="label">Tingkat Penyelesaian</div>
                    <div class="value">{{ number_format($metrics['completion_rate'] * 100, 1) }}%</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card danger">
                    <div class="label">Dispute Terbuka</div>
                    <div class="value">{{ $metrics['disputes_open'] }}</div>
                </div>
            </div>
        </div>

        <h6 class="text-muted text-uppercase small fw-bold mt-3 mb-2"><i class="bi bi-diagram-3-fill me-1"></i>Jaringan Platform</h6>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Total Toko</div>
                    <div class="value">{{ $metrics['stores_total'] }} <small class="text-muted fs-6">({{ $metrics['stores_active'] }} aktif)</small></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <a href="{{ route('admin.dashboard', ['tab' => 'stores']) }}" class="text-decoration-none">
                    <div class="lk-stat-card {{ $metrics['stores_pending'] > 0 ? 'secondary' : '' }}">
                        <div class="label">Toko Menunggu Review</div>
                        <div class="value">{{ $metrics['stores_pending'] }} <i class="bi bi-arrow-right-short fs-5 text-muted"></i></div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Total Pelanggan</div>
                    <div class="value">{{ $metrics['customers_total'] }} <small class="text-muted fs-6">({{ $metrics['customers_active_today'] }} hari ini)</small></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="lk-stat-card">
                    <div class="label">Total Kurir</div>
                    <div class="value">{{ $metrics['couriers_total'] }} <small class="text-muted fs-6">({{ $metrics['couriers_available'] }} siap)</small></div>
                </div>
            </div>
        </div>

        @if($isOwner)
            <h6 class="text-muted text-uppercase small fw-bold mt-3 mb-2"><i class="bi bi-shield-shaded me-1"></i>Owner-Only Metrics</h6>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="lk-stat-card tertiary">
                        <div class="label">Estimasi Komisi Platform (30 Hari)</div>
                        <div class="value">Rp {{ number_format($platformCommission ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="lk-stat-card">
                        <div class="label">Panggilan AI Hari Ini</div>
                        <div class="value">{{ $metrics['ai_calls_today'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="lk-stat-card">
                        <div class="label">Biaya AI Hari Ini</div>
                        <div class="value">$ {{ number_format($metrics['ai_cost_today'], 4) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="lk-stat-card">
                        <div class="label">Token AI Hari Ini</div>
                        <div class="value">{{ number_format($metrics['ai_tokens_today']) }}</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-7">
                <div class="lk-section h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Aktivitas Audit Terbaru</h5>
                        <a href="{{ route('admin.dashboard', ['tab' => 'audit']) }}" class="small text-decoration-none">Lihat Semua &rarr;</a>
                    </div>
                    @forelse($recentAudit as $a)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $a->action }}</strong>
                                <small class="text-muted">{{ $a->created_at->diffForHumans() }}</small>
                            </div>
                            <small class="text-muted">oleh {{ $a->actor->name ?? 'System' }} &middot; {{ $a->resource_type }} #{{ $a->resource_id }}</small>
                        </div>
                    @empty
                        <p class="lk-empty mb-0">Belum ada catatan audit.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-md-5">
                <div class="lk-section h-100">
                    <h5 class="mb-3">Percobaan Login Gagal (24 Jam)</h5>
                    @forelse($failedLogins as $f)
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between">
                                <span>{{ $f->email }}</span>
                                <small class="text-muted">{{ $f->attempted_at->diffForHumans() }}</small>
                            </div>
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $f->ip_address }}</small>
                        </div>
                    @empty
                        <p class="lk-empty mb-0 text-success"><i class="bi bi-check-circle-fill me-1"></i>Tidak ada percobaan login gagal dalam 24 jam.</p>
                    @endforelse
                </div>
            </div>
        </div>

    {{-- ================= TAB: STORES ================= --}}
    @elseif($tab === 'stores')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Toko / Mitra Laundry</h5>
                <span class="badge bg-light text-dark border">Total: {{ $stores->total() }} Toko</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama Toko</th>
                            <th>Pemilik</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                            <th class="text-end">Aksi Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stores as $s)
                            <tr>
                                <td class="fw-bold text-muted">#{{ $s->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $s->name }}</div>
                                    <small class="text-muted">{{ $s->phone ?? '-' }}</small>
                                </td>
                                <td>
                                    <div>{{ $s->owner->name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $s->owner->email ?? '-' }}</small>
                                </td>
                                <td>
                                    <div>{{ $s->city ?? '-' }}</div>
                                    <small class="text-muted">{{ $s->province ?? '-' }}</small>
                                </td>
                                <td>
                                    @if($s->status === 'approved')
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Approved</span>
                                        @if($s->is_active)
                                            <span class="badge bg-primary">Aktif</span>
                                        @else
                                            <span class="badge bg-secondary">Tutup</span>
                                        @endif
                                    @elseif(in_array($s->status, ['submitted', 'resubmitted', 'under_review']))
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Menunggu Review</span>
                                    @elseif($s->status === 'suspended')
                                        <span class="badge bg-dark"><i class="bi bi-pause-circle me-1"></i>Suspended</span>
                                    @elseif($s->status === 'rejected')
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Rejected</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($s->status) }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $s->created_at->format('d M Y H:i') }}</small></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        @if(in_array($s->status, ['submitted', 'resubmitted', 'under_review', 'draft']))
                                            <form action="{{ route('admin.stores.approve', $s->id) }}" method="POST" class="d-inline store-action-form" data-action="Approve" data-store-name="{{ $s->name }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg me-1"></i>Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $s->id }}">
                                                <i class="bi bi-x-lg me-1"></i>Tolak
                                            </button>

                                            {{-- Modal Tolak --}}
                                            <div class="modal fade text-start" id="rejectModal{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="{{ route('admin.stores.reject', $s->id) }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Tolak Pendaftaran Toko</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Tolak pengajuan toko <strong>{{ $s->name }}</strong>?</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">Alasan Penolakan <span class="text-danger">*</span></label>
                                                                    <textarea name="reason" class="form-control" rows="3" required placeholder="Contoh: Dokumen tidak lengkap, nomor telepon tidak aktif..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger btn-sm">Tolak Toko</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif($s->status === 'approved')
                                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#suspendModal{{ $s->id }}">
                                                <i class="bi bi-pause-circle me-1"></i>Suspend
                                            </button>

                                            {{-- Modal Suspend --}}
                                            <div class="modal fade text-start" id="suspendModal{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="{{ route('admin.stores.suspend', $s->id) }}" method="POST">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Suspend Toko</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Nonaktifkan operasional toko <strong>{{ $s->name }}</strong> dari marketplace?</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-semibold">Alasan Suspend <span class="text-danger">*</span></label>
                                                                    <textarea name="reason" class="form-control" rows="3" required placeholder="Contoh: Pelanggaran ketentuan layanan..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-dark btn-sm">Suspend Toko</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <form action="{{ route('admin.stores.reactivate', $s->id) }}" method="POST" class="d-inline store-action-form" data-action="Aktifkan kembali" data-store-name="{{ $s->name }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Aktifkan
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data toko.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($stores->hasPages())
                <div class="mt-3">
                    {{ $stores->appends(['tab' => 'stores'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: ORDERS ================= --}}
    @elseif($tab === 'orders')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Seluruh Pesanan</h5>
                <span class="badge bg-light text-dark border">Total: {{ $orders->total() }} Pesanan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Pelanggan</th>
                            <th>Toko / Outlet</th>
                            <th>Total Tagihan</th>
                            <th>Status</th>
                            <th>Waktu Pesan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $o)
                            <tr>
                                <td class="fw-bold">#{{ $o->order_number ?? $o->id }}</td>
                                <td>{{ $o->customer->name ?? 'N/A' }}</td>
                                <td>{{ $o->store->name ?? 'N/A' }}</td>
                                <td class="fw-semibold">Rp {{ number_format($o->total_amount, 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge bg-info text-dark">{{ ucfirst($o->status) }}</span>
                                </td>
                                <td><small class="text-muted">{{ $o->created_at->format('d M Y H:i') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data pesanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="mt-3">
                    {{ $orders->appends(['tab' => 'orders'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: CUSTOMERS / USERS ================= --}}
    @elseif($tab === 'customers')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Pengguna Platform</h5>
                <span class="badge bg-light text-dark border">Total: {{ $customers->total() }} Pengguna</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. Telepon</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $u)
                            <tr>
                                <td>#{{ $u->id }}</td>
                                <td class="fw-semibold">{{ $u->name }}</td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->phone ?? '-' }}</td>
                                <td>
                                    @foreach($u->roles as $role)
                                        <span class="badge bg-light text-dark border me-1">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($u->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $u->created_at->format('d M Y') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada pengguna.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($customers->hasPages())
                <div class="mt-3">
                    {{ $customers->appends(['tab' => 'customers'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: COURIERS ================= --}}
    @elseif($tab === 'couriers')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Mitra Kurir</h5>
                <span class="badge bg-light text-dark border">Total: {{ $couriers->total() }} Kurir</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nama Kurir</th>
                            <th>Kendaraan</th>
                            <th>Plat Nomor</th>
                            <th>Status Ketersediaan</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($couriers as $c)
                            <tr>
                                <td>#{{ $c->id }}</td>
                                <td class="fw-semibold">{{ $c->user->name ?? 'N/A' }}</td>
                                <td>{{ $c->vehicle_type ?? 'Motor' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $c->vehicle_plate ?? '-' }}</span></td>
                                <td>
                                    @if($c->is_available)
                                        <span class="badge bg-success">Siap Menerima Order</span>
                                    @else
                                        <span class="badge bg-secondary">Offline / Istirahat</span>
                                    @endif
                                </td>
                                <td><i class="bi bi-star-fill text-warning me-1"></i>{{ number_format($c->rating_average ?? 5.0, 1) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data kurir.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($couriers->hasPages())
                <div class="mt-3">
                    {{ $couriers->appends(['tab' => 'couriers'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: PAYMENTS ================= --}}
    @elseif($tab === 'payments')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Riwayat Pembayaran</h5>
                <span class="badge bg-light text-dark border">Total: {{ $payments->total() }} Transaksi</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Order #</th>
                            <th>Metode</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Waktu Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $p)
                            <tr>
                                <td>#{{ $p->id }}</td>
                                <td>#{{ $p->order->order_number ?? $p->order_id }}</td>
                                <td><span class="badge bg-light text-dark border text-uppercase">{{ $p->payment_method }}</span></td>
                                <td class="fw-semibold">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                <td>
                                    @if($p->status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($p->status === 'pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($p->status) }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $p->paid_at ? $p->paid_at->format('d M Y H:i') : '-' }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data pembayaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($payments->hasPages())
                <div class="mt-3">
                    {{ $payments->appends(['tab' => 'payments'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: REFUNDS ================= --}}
    @elseif($tab === 'refunds')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Daftar Pengembalian Dana (Refunds)</h5>
                <span class="badge bg-light text-dark border">Total: {{ $refunds->total() }} Pengajuan</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Order #</th>
                            <th>Diajukan Oleh</th>
                            <th>Jumlah Refund</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refunds as $rf)
                            <tr>
                                <td>#{{ $rf->id }}</td>
                                <td>#{{ $rf->order->order_number ?? $rf->order_id }}</td>
                                <td>{{ $rf->requestedBy->name ?? 'N/A' }}</td>
                                <td class="fw-semibold text-danger">Rp {{ number_format($rf->amount, 0, ',', '.') }}</td>
                                <td><small class="text-muted">{{ $rf->reason }}</small></td>
                                <td>
                                    @if($rf->status === 'completed')
                                        <span class="badge bg-success">Selesai</span>
                                    @elseif($rf->status === 'requested')
                                        <span class="badge bg-warning text-dark">Menunggu</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($rf->status) }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $rf->created_at->format('d M Y H:i') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data refund.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($refunds->hasPages())
                <div class="mt-3">
                    {{ $refunds->appends(['tab' => 'refunds'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: DISPUTES ================= --}}
    @elseif($tab === 'disputes')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Penyelesaian Sengketa Pesanan (Disputes)</h5>
                <span class="badge bg-light text-dark border">Total: {{ $disputes->total() }} Dispute</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Order #</th>
                            <th>Pelapor</th>
                            <th>Kategori / Alasan</th>
                            <th>Status</th>
                            <th>Tanggal Laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disputes as $d)
                            <tr>
                                <td>#{{ $d->id }}</td>
                                <td>#{{ $d->order->order_number ?? $d->order_id }}</td>
                                <td>{{ $d->openedBy->name ?? 'N/A' }}</td>
                                <td>
                                    <div>{{ $d->reason }}</div>
                                    <small class="text-muted">{{ $d->description ?? '-' }}</small>
                                </td>
                                <td>
                                    @if($d->status === 'resolved')
                                        <span class="badge bg-success">Selesai</span>
                                    @elseif($d->status === 'opened')
                                        <span class="badge bg-danger">Terbuka</span>
                                    @else
                                        <span class="badge bg-secondary">{{ ucfirst($d->status) }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $d->created_at->format('d M Y H:i') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada sengketa pesanan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($disputes->hasPages())
                <div class="mt-3">
                    {{ $disputes->appends(['tab' => 'disputes'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: AUDIT LOG ================= --}}
    @elseif($tab === 'audit')
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Audit Log Aktivitas Sistem</h5>
                <span class="badge bg-light text-dark border">Total: {{ $auditLogs->total() }} Log</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Aksi (Action)</th>
                            <th>Aktor (User)</th>
                            <th>Resource</th>
                            <th>Metadata / Keterangan</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($auditLogs as $al)
                            <tr>
                                <td>#{{ $al->id }}</td>
                                <td class="fw-semibold"><span class="badge bg-light text-dark border">{{ $al->action }}</span></td>
                                <td>{{ $al->actor->name ?? 'System' }}</td>
                                <td>{{ $al->resource_type }} #{{ $al->resource_id }}</td>
                                <td>
                                    <small class="text-muted text-break">{{ json_encode($al->metadata ?? $al->after_value) }}</small>
                                </td>
                                <td><small class="text-muted">{{ $al->created_at->format('d M Y H:i:s') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada catatan audit log.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($auditLogs->hasPages())
                <div class="mt-3">
                    {{ $auditLogs->appends(['tab' => 'audit'])->links() }}
                </div>
            @endif
        </div>

    {{-- ================= TAB: COMMISSION (OWNER) ================= --}}
    @elseif($tab === 'commission' && $isOwner)
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Pengaturan Komisi Platform</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tipe / Kategori</th>
                            <th>Rate Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($commissionSettings as $cs)
                            <tr>
                                <td>#{{ $cs->id }}</td>
                                <td class="fw-semibold">{{ $cs->name ?? 'Default Platform Cut' }}</td>
                                <td class="fw-bold text-success">{{ $cs->commission_rate * 100 }}%</td>
                                <td>
                                    @if($cs->is_active)
                                        <span class="badge bg-success">Aktif</span>
                                    @else
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Default komisi platform aktif: 10%</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ================= TAB: AI USAGE (OWNER) ================= --}}
    @elseif($tab === 'ai' && $isOwner)
        <div class="lk-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Monitoring Penggunaan OpenAI Assistant</h5>
                <span class="badge bg-light text-dark border">Total: {{ $aiLogs->total() }} Log</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Pengguna</th>
                            <th>Model</th>
                            <th>Prompt Tokens</th>
                            <th>Completion Tokens</th>
                            <th>Biaya ($ USD)</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($aiLogs as $ail)
                            <tr>
                                <td>#{{ $ail->id }}</td>
                                <td>{{ $ail->user->name ?? 'Guest / Anon' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $ail->model ?? 'gpt-4o-mini' }}</span></td>
                                <td>{{ number_format($ail->prompt_tokens ?? 0) }}</td>
                                <td>{{ number_format($ail->completion_tokens ?? 0) }}</td>
                                <td class="fw-semibold text-primary">$ {{ number_format($ail->total_cost ?? 0, 5) }}</td>
                                <td><small class="text-muted">{{ $ail->created_at->format('d M Y H:i:s') }}</small></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Belum ada data penggunaan AI.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($aiLogs->hasPages())
                <div class="mt-3">
                    {{ $aiLogs->appends(['tab' => 'ai'])->links() }}
                </div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.store-action-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const action = form.getAttribute('data-action') || 'Proses';
                const storeName = form.getAttribute('data-store-name') || 'toko';
                if (!confirm(action + ' toko ' + storeName + '?')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>
@endpush

@extends('layouts.app')

@section('title', 'Cari Laundry Terdekat — LaundryKu')
@section('description', 'Marketplace laundry: cari berdasarkan lokasi, harga, rating, dan jenis layanan.')

@push('head')
<style>
    .filter-panel { background: #fff; border-radius: 12px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .view-toggle .btn { border-color: var(--lk-secondary); color: var(--lk-secondary); }
    .view-toggle .btn.active { background: var(--lk-secondary); color: #fff; }
</style>
@endpush

@section('content')
    <div class="container py-4">
        <h1 class="h3 fw-bold text-lk-primary mb-3">Cari Laundry</h1>

        <form action="{{ route('marketplace.index') }}" method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label small">Kata kunci</label>
                <input type="text" name="q" class="form-control" placeholder="Nama laundry / area" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Radius (km)</label>
                <select name="radius_km" class="form-select">
                    <option value="">Semua</option>
                    @foreach([2, 5, 10, 20] as $r)
                        <option value="{{ $r }}" @selected(($filters['radius_km'] ?? '') == $r)>{{ $r }} km</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Min Rating</label>
                <select name="min_rating" class="form-select">
                    <option value="">Semua</option>
                    @foreach([3, 3.5, 4, 4.5] as $r)
                        <option value="{{ $r }}" @selected(($filters['min_rating'] ?? '') == $r)>≥ {{ $r }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-lk-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
            </div>
            <input type="hidden" name="view" id="lkView" value="{{ request('view', 'list') }}">
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <small class="text-muted">{{ $results->total() ?? 0 }} laundry ditemukan</small>
            <div class="btn-group view-toggle" role="group">
                <button type="button" class="btn btn-sm {{ request('view', 'list') === 'list' ? 'active' : '' }}" onclick="lkSetView('list')">
                    <i class="bi bi-list-ul"></i> List
                </button>
                <button type="button" class="btn btn-sm {{ request('view') === 'map' ? 'active' : '' }}" onclick="lkSetView('map')">
                    <i class="bi bi-map"></i> Map
                </button>
            </div>
        </div>

        <div id="lkListView" style="{{ request('view') === 'map' ? 'display:none' : '' }}">
            @if($results->isEmpty())
                <div class="lk-card p-4 text-center text-muted">Tidak ada laundry yang cocok dengan filter Anda.</div>
            @else
                <div class="row g-3">
                    @foreach($results as $store)
                        <div class="col-md-6 col-lg-4">
                            @include('partials.store-card', ['store' => $store])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $results->withQueryString()->links() }}</div>
            @endif
        </div>

        <div id="lkMapView" style="{{ request('view') !== 'map' ? 'display:none' : '' }}">
            <div class="lk-map" id="lkMap"></div>
            <div class="mt-3">
                <ul class="list-group">
                    @foreach($results as $store)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>{{ $store->name }}</strong> <small class="text-muted ms-2">{{ \Illuminate\Support\Str::limit($store->address ?? '', 50) }}</small></span>
                            <a href="{{ route('store.detail', $store->slug) }}" class="btn btn-sm btn-outline-lk-primary">Detail</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function lkSetView(view) {
        document.getElementById('lkView').value = view;
        const url = new URL(window.location.href);
        url.searchParams.set('view', view);
        window.location.href = url.toString();
    }

    // Init Leaflet map (fallback ke OpenStreetMap jika Google Maps key kosong / gagal)
    (function () {
        if (document.getElementById('lkMapView').style.display === 'none') return;
        const mapEl = document.getElementById('lkMap');
        const useGoogle = window.LK.mapsKey;
        const stores = @json($mapStores);

        if (!useGoogle) {
            // Pakai Leaflet + OSM sebagai fallback (selalu tersedia)
            try {
                const map = L.map(mapEl).setView([stores[0]?.lat ?? -6.2, stores[0]?.lng ?? 106.8], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 19,
                }).addTo(map);
                stores.forEach(s => {
                    L.marker([s.lat, s.lng]).addTo(map)
                        .bindPopup(`<strong>${s.name}</strong><br>${s.address ?? ''}<br><a href="/stores/${s.slug}">Detail</a>`);
                });
                return;
            } catch (e) {
                // fallthrough ke text fallback
            }
        } else {
            // Google Maps loader (placeholder — kalau gagal, fallback ke Leaflet)
            if (typeof google === 'undefined' || !google.maps) {
                // inject script
                const s = document.createElement('script');
                s.src = `https://maps.googleapis.com/maps/api/js?key=${useGoogle}&libraries=places`;
                s.onerror = () => renderTextFallback();
                s.onload = () => {
                    try {
                        const center = stores[0] ?? { lat: -6.2, lng: 106.8 };
                        const map = new google.maps.Map(mapEl, { center, zoom: 12 });
                        stores.forEach(s => new google.maps.Marker({ position: { lat: s.lat, lng: s.lng }, map, title: s.name }));
                    } catch (e) { renderTextFallback(); }
                };
                document.head.appendChild(s);
                return;
            }
        }
        renderTextFallback();

        function renderTextFallback() {
            mapEl.innerHTML = '<div class="lk-map-fallback"><i class="bi bi-map" style="font-size:3rem"></i><p>Peta tidak dapat dimuat. Alamat laundry:</p><ul class="list-unstyled small">' +
                stores.map(s => `<li><strong>${s.name}</strong> — ${s.address ?? '-'}</li>`).join('') + '</ul></div>';
        }
    })();
</script>
@endpush

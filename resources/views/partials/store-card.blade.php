@php
    $rating = (float) ($store->average_rating ?? 0);
    $fullStars = (int) floor($rating);
    $hasHalf = $rating - $fullStars >= 0.5;
@endphp
<a href="{{ route('store.detail', $store->slug) }}" class="text-decoration-none text-reset">
    <div class="lk-card lk-card-store p-3 h-100">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="mb-0 text-lk-primary">{{ $store->name }}</h5>
            <span class="badge lk-badge-tertiary">
                <i class="bi bi-star-fill"></i> {{ number_format($rating, 1) }}
            </span>
        </div>
        <p class="text-muted small mb-2">
            <i class="bi bi-geo-alt me-1"></i>{{ \Illuminate\Support\Str::limit($store->address ?? '-', 60) }}
        </p>
        <div class="d-flex justify-content-between align-items-center small text-muted">
            <span><i class="bi bi-chat-quote me-1"></i>{{ $store->review_count ?? 0 }} ulasan</span>
            @if(isset($store->distance))
                <span><i class="bi bi-signpost me-1"></i>{{ number_format($store->distance, 1) }} km</span>
            @endif
        </div>
    </div>
</a>

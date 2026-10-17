@php
    /**
     * Sidebar partial — Tahap 18.
     * Variabel yang diharapkan:
     *  - $activeKey: string (key menu yang sedang aktif, untuk highlight)
     *  - $menu: array of ['key','label','route','icon','perm']  (untuk admin)
     *  - $user: User instance
     */
    $user = $user ?? auth()->user();
@endphp
<aside class="lk-sidebar bg-white border-end p-3" style="width: 240px; min-height: calc(100vh - 56px); position: sticky; top: 56px;">
    <div class="small text-muted text-uppercase fw-bold mb-2">{{ $sidebarLabel ?? 'Menu' }}</div>
    <ul class="nav flex-column gap-1">
        @foreach(($menu ?? []) as $item)
            @php
                $itemUrl = !empty($item['url']) ? $item['url'] : (\Illuminate\Support\Facades\Route::has($item['route'] ?? '') ? route($item['route']) : '#');
                $isActive = ($activeKey ?? '') === $item['key'];
            @endphp
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center gap-2 rounded px-3 py-2 {{ $isActive ? 'active fw-semibold bg-light text-lk-primary' : 'text-dark' }}"
                   href="{{ $itemUrl }}">
                    <i class="bi bi-{{ $item['icon'] ?? 'circle' }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</aside>

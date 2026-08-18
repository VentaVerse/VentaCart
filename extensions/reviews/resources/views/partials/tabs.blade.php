{{-- One home, one tab bar (owner ruling 2026-08-19). --}}
<div class="v3-tabs" role="tablist" aria-label="Reviews workspace">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
        <a class="v3-tab" role="tab" aria-selected="{{ ($tab ?? null) === $key ? 'true' : 'false' }}" href="{{ route('admin.reviews.index', ['tab' => $key]) }}">
            {{ $label }}
            @isset($counts)<span class="v3-tabs__count">{{ $counts[$key] }}</span>@endisset
        </a>
    @endforeach
    <a class="v3-tab" role="tab" aria-selected="{{ request()->routeIs('admin.reviews.settings') ? 'true' : 'false' }}" href="{{ route('admin.reviews.settings') }}">Settings</a>
</div>

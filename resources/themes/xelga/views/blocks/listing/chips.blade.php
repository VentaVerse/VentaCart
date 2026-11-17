{{-- The filters a shopper has on, each a link that takes it off. --}}
@if ($data['filters']['chips'] !== [])
    <ul class="active-filters" aria-label="Active filters">
        @foreach ($data['filters']['chips'] as $chip)
            <li><a class="chip-x" href="{{ $chip['url'] }}" aria-label="Remove filter {{ $chip['label'] }}">{{ $chip['label'] }}<svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></a></li>
        @endforeach
        <li><a class="chip-x chip-x--clear" href="{{ $data['filters']['clear_url'] }}">Clear all</a></li>
    </ul>
@endif

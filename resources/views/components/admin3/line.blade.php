{{-- A count and the screen that resolves it.

     The whole line is the target and the hover runs edge to edge,
     which is what makes a ledger row feel like a row rather than like
     a link in a list. Zero recedes rather than shouting: a count of
     nothing waiting is good news.

     `detail` names the newest thing behind the count, on one line,
     when a single cheap read can name it. --}}
@props(['href', 'label', 'count' => null, 'detail' => null])
<li>
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'v3-line']) }}>
        <span class="v3-line__label">
            {{ $label }}
            @if ($detail)<span class="v3-line__detail">{{ $detail }}</span>@endif
        </span>
        <span class="v3-num v3-line__count {{ (int) $count === 0 ? 'v3-line__count--zero' : '' }}">{{ number_format((int) $count) }}</span>
        <x-admin3.icon name="chevron" class="v3-line__go" />
    </a>
</li>

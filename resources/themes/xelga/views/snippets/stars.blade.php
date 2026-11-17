{{-- A star rating out of five, read aloud as one phrase. --}}
@php
    $value = max(0, min(5, (float) $rating));
    $full = (int) floor($value);
    $half = ($value - $full) >= 0.5 ? 1 : 0;
@endphp
<span class="stars" role="img" aria-label="Rated {{ rtrim(rtrim(number_format($value, 1), '0'), '.') }} out of 5{{ isset($count) && $count ? ', ' . $count . ' ' . ($count === 1 ? 'review' : 'reviews') : '' }}">
    @for ($i = 0; $i < 5; $i++)
        <svg class="icon" aria-hidden="true"><use href="#i-{{ $i < $full ? 'star-fill' : ($i === $full && $half ? 'star-half' : 'star') }}"></use></svg>
    @endfor
    @if (isset($count) && $count)
        <span class="stars__count" aria-hidden="true">({{ $count }})</span>
    @endif
</span>

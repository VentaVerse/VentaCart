{{-- An order status as core names it, colored by the tone core gives the status. --}}
@php
    $tone = match ($tone ?? 'neutral') {
        'info' => 'status--info',
        'success' => 'status--ok',
        'warning' => 'status--warn',
        'danger' => 'status--danger',
        default => 'status--neutral',
    };
@endphp
<span class="status {{ $tone }}{{ ! empty($large) ? ' status--lg' : '' }}">{{ $name }}</span>

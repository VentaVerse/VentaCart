@php
    $config = $placement->config ?? [];
    $style = ($config['style'] ?? 'space') === 'line' ? 'line' : 'space';
    $size = in_array($config['size'] ?? 'md', ['sm', 'md', 'lg'], true) ? $config['size'] : 'md';
@endphp
<div class="container-store">
    <hr class="wsep wsep--{{ $style }} wsep--{{ $size }}" aria-hidden="true">
</div>

@php
    $config = $placement->config ?? [];
    $direction = ($config['direction'] ?? 'horizontal') === 'vertical' ? 'vertical' : 'horizontal';
    $tree = $tree ?? [];
@endphp
@if (count($tree))
<nav class="container-store wmenu wmenu--{{ $direction }}" aria-label="{{ $placement->title ?: 'Menu' }}">
    @if ($placement->title)
        <h2 class="section-title wmenu__title">{{ $placement->title }}</h2>
    @endif
    <ul class="wmenu__list">
        @foreach ($tree as $node)
            @if ($node['type'] === 'separator')
                <li class="wmenu__sep" aria-hidden="true"></li>
            @elseif ($node['type'] === 'title')
                <li class="wmenu__heading">{{ $node['label'] }}</li>
            @else
                <li class="wmenu__item">
                    <a href="{{ $node['url'] ?? '#' }}" @if ($node['new_tab']) target="_blank" rel="noopener" @endif
                       class="{{ $node['accent'] ? 'nav-accent' : '' }}" @if ($node['accent']) style="--nav-accent: {{ $node['accent'] }}" @endif>{{ $node['label'] }}</a>
                    @if (count($node['children']))
                        <ul class="wmenu__sub">
                            @foreach ($node['children'] as $kid)
                                @if (! in_array($kid['type'], ['separator', 'title'], true))
                                    <li><a href="{{ $kid['url'] ?? '#' }}">{{ $kid['label'] }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
</nav>
@endif

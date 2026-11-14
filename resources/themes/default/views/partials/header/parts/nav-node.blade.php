@php
    $vDrop = $node['dropdown'] ?? null;
    $vKids = $node['children'] ?? [];
    $vHasMenu = $vDrop || count($vKids) > 0;
    $vAccent = $node['accent'] ?? null;
@endphp
@if ($node['type'] === 'separator')
    <span class="nav-sep" aria-hidden="true"></span>
@elseif ($node['type'] === 'title')
    <span class="nav-title">{{ $node['label'] }}</span>
@elseif (! $vHasMenu)
    <a href="{{ $node['url'] ?? '#' }}" class="{{ $vAccent ? 'nav-accent' : '' }}" @if ($vAccent) style="--nav-accent: {{ $vAccent }}" @endif
       @if ($node['new_tab']) target="_blank" rel="noopener" @endif>{{ $node['label'] }}</a>
@else
    <div class="nav-dropdown-wrap {{ ($vDrop['kind'] ?? '') === 'mega' ? 'nav-dropdown-wrap--mega' : '' }}">
        <a href="{{ $node['url'] ?? '#' }}" class="nav-dropdown-trigger {{ $vAccent ? 'nav-accent' : '' }}" @if ($vAccent) style="--nav-accent: {{ $vAccent }}" @endif
           @if ($node['new_tab']) target="_blank" rel="noopener" @endif>
            {{ $node['label'] }}
            <svg class="nav-dropdown-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </a>

        @if (($vDrop['kind'] ?? '') === 'mega')
            <div class="nav-dropdown nav-mega">
                @foreach ($vDrop['columns'] as $col)
                    <div class="nav-mega__col">
                        <a href="{{ $col['url'] }}" class="nav-mega__head">{{ $col['name'] }}</a>
                        @foreach ($col['children'] as $child)
                            <a href="{{ $child['url'] }}" class="nav-dropdown-item">{{ $child['name'] }}</a>
                        @endforeach
                    </div>
                @endforeach
                @if (!empty($vDrop['brands']))
                    <div class="nav-mega__col nav-mega__col--brands">
                        <span class="nav-mega__head">Brands</span>
                        @foreach (array_slice($vDrop['brands'], 0, 8) as $brand)
                            <a href="{{ $brand['url'] }}" class="nav-dropdown-item">{{ $brand['name'] }}</a>
                        @endforeach
                    </div>
                @endif
                @if (!empty($vDrop['banner']))
                    <div class="nav-mega__banner">
                        @if ($vDrop['banner']['url'])<a href="{{ $vDrop['banner']['url'] }}">@endif
                        <img src="{{ storage_url($vDrop['banner']['image']) }}" alt="" loading="lazy">
                        @if ($vDrop['banner']['url'])</a>@endif
                    </div>
                @endif
                @foreach ($vKids as $kid)
                    <a href="{{ $kid['url'] ?? '#' }}" class="nav-dropdown-item nav-mega__extra" @if ($kid['new_tab']) target="_blank" rel="noopener" @endif>{{ $kid['label'] }}</a>
                @endforeach
            </div>
        @elseif (($vDrop['kind'] ?? '') === 'html')
            <div class="nav-dropdown nav-dropdown--html">{!! $vDrop['html'] !!}</div>
        @elseif (($vDrop['kind'] ?? '') === 'brands')
            <div class="nav-dropdown nav-dropdown--brands">
                @if ($vDrop['all_label'])<a href="{{ $node['url'] ?? '#' }}" class="nav-dropdown-item nav-dropdown-all">{{ $vDrop['all_label'] }}</a>@endif
                @foreach ($vDrop['brands'] as $brand)
                    <a href="{{ $brand['url'] }}" class="nav-dropdown-item">{{ $brand['name'] }}</a>
                @endforeach
            </div>
        @else
            <div class="nav-dropdown">
                @if (!empty($vDrop['all_label']) && ($node['url'] ?? null))
                    <a href="{{ $node['url'] }}" class="nav-dropdown-item nav-dropdown-all">{{ $vDrop['all_label'] }}</a>
                @endif
                @foreach ($vDrop['columns'] ?? [] as $sub)
                    @if (!empty($sub['children']))
                        <div class="nav-dropdown-parent">
                            <a href="{{ $sub['url'] }}" class="nav-dropdown-item nav-dropdown-has-children">
                                {{ $sub['name'] }}
                                <svg class="nav-submenu-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <div class="nav-submenu">
                                <a href="{{ $sub['url'] }}" class="nav-dropdown-item nav-dropdown-all">All {{ $sub['name'] }}</a>
                                @foreach ($sub['children'] as $grandchild)
                                    <a href="{{ $grandchild['url'] }}" class="nav-dropdown-item">{{ $grandchild['name'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $sub['url'] }}" class="nav-dropdown-item">{{ $sub['name'] }}</a>
                    @endif
                @endforeach
                @foreach ($vKids as $kid)
                    @if ($kid['type'] === 'separator')
                        <span class="nav-dropdown-sep" aria-hidden="true"></span>
                    @elseif ($kid['type'] === 'title')
                        <span class="nav-dropdown-title">{{ $kid['label'] }}</span>
                    @else
                        <a href="{{ $kid['url'] ?? '#' }}" class="nav-dropdown-item" @if ($kid['new_tab']) target="_blank" rel="noopener" @endif>{{ $kid['label'] }}</a>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endif

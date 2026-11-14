@php
    $vDrop = $node['dropdown'] ?? null;
    $vKids = collect($node['children'] ?? [])->filter(fn ($k) => ! in_array($k['type'], ['separator', 'title'], true));
    $vSubs = collect($vDrop['columns'] ?? []);
    $vBrands = collect($vDrop['brands'] ?? []);
    $vOpens = $vSubs->isNotEmpty() || $vKids->isNotEmpty() || $vBrands->isNotEmpty();
@endphp
@if ($node['type'] === 'separator')
    <hr class="mobile-nav-sep">
@elseif ($node['type'] === 'title')
    <span class="mobile-nav-title">{{ $node['label'] }}</span>
@elseif (($vDrop['kind'] ?? '') === 'html')
    <a href="{{ $node['url'] ?? '#' }}">{{ $node['label'] }}</a>
@elseif (! $vOpens)
    <a href="{{ $node['url'] ?? '#' }}" @if ($node['new_tab']) target="_blank" rel="noopener" @endif>{{ $node['label'] }}</a>
@else
    <div x-data="{ expanded: false }">
        <div class="mobile-nav-parent">
            <a href="{{ $node['url'] ?? '#' }}" @if ($node['new_tab']) target="_blank" rel="noopener" @endif>{{ $node['label'] }}</a>
            <button type="button" @click="expanded = !expanded" class="mobile-nav-toggle" :aria-expanded="expanded ? 'true' : 'false'" aria-label="Open {{ $node['label'] }}">
                <svg :class="expanded && 'rotate-180'" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
        <div x-show="expanded" x-cloak x-transition>
            <div class="mobile-nav-subs">
                @foreach ($vSubs as $sub)
                    @if (!empty($sub['children']))
                        <div x-data="{ subExpanded: false }">
                            <div class="mobile-nav-parent">
                                <a href="{{ $sub['url'] }}">{{ $sub['name'] }}</a>
                                <button type="button" @click="subExpanded = !subExpanded" class="mobile-nav-toggle" :aria-expanded="subExpanded ? 'true' : 'false'" aria-label="Open {{ $sub['name'] }}">
                                    <svg :class="subExpanded && 'rotate-180'" class="w-3.5 h-3.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                            <div x-show="subExpanded" x-cloak x-transition>
                                <div class="mobile-nav-subs">
                                    @foreach ($sub['children'] as $grandchild)
                                        <a href="{{ $grandchild['url'] }}">{{ $grandchild['name'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $sub['url'] }}">{{ $sub['name'] }}</a>
                    @endif
                @endforeach
                @foreach ($vBrands as $brand)
                    <a href="{{ $brand['url'] }}">{{ $brand['name'] }}</a>
                @endforeach
                @foreach ($vKids as $kid)
                    @include('partials.header.parts.mobile-node', ['node' => $kid])
                @endforeach
            </div>
        </div>
    </div>
@endif

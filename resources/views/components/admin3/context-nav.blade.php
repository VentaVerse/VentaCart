@props(['groups' => [], 'active' => null, 'label' => 'Secondary'])
{{-- Any second menu column beside the rail: settings today, a module's
     own sections tomorrow. Knows nothing about what it lists; the caller
     passes resolved groups: [['label' => ..., 'items' => [['key', 'label',
     'icon', 'url'], ...]], ...]. --}}
<nav class="v3-snav" aria-label="{{ $label }}">
    @foreach ($groups as $group)
        <div class="v3-snav__group">{{ $group['label'] }}</div>
        @foreach ($group['items'] as $item)
            <a href="{{ $item['url'] }}" class="v3-snav__item"
               @if ($active === $item['key']) aria-current="page" @endif>
                <x-admin3.icon :name="$item['icon']" />
                {{ $item['label'] }}
                @if (isset($item['count']) && $item['count'] > 0)
                    <span class="v3-snav__count">{{ number_format($item['count']) }}</span>
                @endif
            </a>
        @endforeach
    @endforeach
</nav>

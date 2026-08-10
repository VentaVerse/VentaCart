{{-- A page whose siblings stay on screen.

     Settings and the SEO workspace are each thirty screens a person
     moves between all day, so the list of where else they can go
     stays beside the work instead of being something they go back
     for. Below the rail's breakpoint the same list is one picker,
     because a phone is for choosing a destination, not for scanning
     a map.

     `groups` is already normalised by the caller, which is what lets
     one component serve two registries that disagree about how a
     destination names itself:

         [['label' => 'Store', 'items' => [
             ['label' => 'General', 'url' => '/…', 'active' => true],
         ]]]

     `narrow` caps the work column at a measure, which is what a
     column of fields wants and what a table does not. --}}
@props([
    'groups' => [],
    'label' => 'Sections',
    'back' => null,
    'backLabel' => null,
    'narrow' => false,
])
<div {{ $attributes->merge(['class' => 'v-workspace']) }}>
    @if ($groups)
        <div class="v-workspace__rail">
            <nav class="v-sidenav" aria-label="{{ $label }}">
                @if ($back)
                    <a href="{{ $back }}" class="v-sidenav__back">
                        <x-admin.icon name="chevron-right" class="w-3 h-3 rotate-180" /> {{ $backLabel }}
                    </a>
                @endif
                @foreach ($groups as $group)
                    <div class="v-sidenav__group">
                        <p class="v-label v-sidenav__label">{{ $group['label'] }}</p>
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="v-sidenav__link"
                               @if ($item['active'] ?? false) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="v-workspace__main {{ $narrow ? 'v-workspace__main--narrow' : '' }}">
        @if ($groups)
            <select x-data @change="window.location.assign($event.target.value)"
                    class="v-control v-sidenav__picker h-[var(--control-h)] rounded-v-md border bg-v-surface px-2.5 text-[13px] text-v-text"
                    aria-label="{{ $label }}">
                @foreach ($groups as $group)
                    <optgroup label="{{ $group['label'] }}">
                        @foreach ($group['items'] as $item)
                            <option value="{{ $item['url'] }}" @selected($item['active'] ?? false)>{{ $item['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        @endif
        {{ $slot }}
    </div>
</div>

@props(['name', 'label', 'hint' => null, 'items' => [], 'selected' => []])
{{-- A searchable multi-pick over a long list: search box, scrollable
     checklist, live selected count. Selected ids post as name[]. --}}
@php
    $plSelected = collect($selected)->map(fn ($v) => is_numeric($v) ? (int) $v : $v)->values()->all();
@endphp
<div x-data="{ q: '', count: @js(count($plSelected)) }">
    <x-admin3.field :name="$name" :label="$label" :hint="$hint">
        <div class="v3-picklist">
            <div class="v3-picklist__top">
                <input type="search" class="v3-control" x-model="q" placeholder="Search…" aria-label="Search {{ $label }}">
                <span class="v3-picklist__count" x-text="count + ' selected'">{{ count($plSelected) }} selected</span>
            </div>
            <div class="v3-picklist__scroll">
                @foreach ($items as $item)
                    <label class="v3-check v3-picklist__item"
                           x-show="!q || @js(strtolower($item['name'])).includes(q.toLowerCase())">
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $item['id'] }}"
                               @checked(in_array(is_numeric($item['id']) ? (int) $item['id'] : $item['id'], $plSelected, true))
                               @change="count += $event.target.checked ? 1 : -1">
                        {{ $item['name'] }}
                    </label>
                @endforeach
                @if (count($items) === 0)
                    <p class="v3-cell-quiet v3-picklist__item">Nothing to pick from.</p>
                @endif
            </div>
        </div>
    </x-admin3.field>
</div>

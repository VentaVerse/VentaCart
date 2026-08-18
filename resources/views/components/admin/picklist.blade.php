@props(['name', 'label', 'hint' => null, 'items' => [], 'selected' => []])
{{-- A searchable multi-pick over a long list: search box, scrollable
     checklist, live selected count. Selected ids post as name[]. --}}
@php
    $plSelected = collect($selected)->map(fn ($v) => is_numeric($v) ? (int) $v : $v)->values()->all();
@endphp
<div x-data="{ q: '', count: @js(count($plSelected)) }">
    <x-admin.field :name="$name" :label="$label" :hint="$hint">
        <div class="v-picklist">
            <div class="v-picklist__top">
                <input type="search" class="v-control" x-model="q" placeholder="Search…" aria-label="Search {{ $label }}">
                <span class="v-picklist__count" x-text="count + ' selected'">{{ count($plSelected) }} selected</span>
            </div>
            <div class="v-picklist__scroll">
                @foreach ($items as $item)
                    <label class="v-check v-picklist__item"
                           x-show="!q || @js(strtolower($item['name'])).includes(q.toLowerCase())">
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $item['id'] }}"
                               @checked(in_array(is_numeric($item['id']) ? (int) $item['id'] : $item['id'], $plSelected, true))
                               @change="count += $event.target.checked ? 1 : -1">
                        {{ $item['name'] }}
                    </label>
                @endforeach
                @if (count($items) === 0)
                    <p class="v-cell-quiet v-picklist__item">Nothing to pick from.</p>
                @endif
            </div>
        </div>
    </x-admin.field>
</div>

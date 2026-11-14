@props(['name', 'label', 'options' => [], 'value' => null, 'emptyLabel' => null, 'placeholder' => 'Search…', 'hint' => null])
{{-- One value picked from a long list by typing (vCombobox). Options are
     [{id, name}]; the picked id posts as $name. With an emptyLabel, picking
     it posts an empty value. --}}
@php
    $vId = 'cb-' . \Illuminate\Support\Str::random(6);
@endphp
<div class="v-combo" x-data="vCombobox(@js(['options' => array_values($options), 'value' => $value, 'emptyLabel' => $emptyLabel]))" @click.outside="close()">
    <x-admin.field :name="$name" :label="$label" :hint="$hint">
        <input type="hidden" name="{{ $name }}" :value="value" x-ref="value" value="{{ $value }}">
        <div class="v-combo__box" :class="{ 'is-open': open }">
            <input type="text" id="{{ $vId }}" class="v-control v-combo__input" autocomplete="off"
                   role="combobox" aria-autocomplete="list" aria-controls="{{ $vId }}-list"
                   :aria-expanded="open ? 'true' : 'false'"
                   :aria-activedescendant="open && matches[active] ? '{{ $vId }}-' + active : ''"
                   :placeholder="open ? (label || @js($placeholder)) : @js($placeholder)"
                   :value="open ? q : label"
                   @focus="show()" @click="open || show()"
                   @input="q = $event.target.value; open = true; active = 0"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                   @keydown.enter.prevent="enter()" @keydown.escape.prevent="close()" @keydown.tab="close()">
            <ul class="v-combo__list" role="listbox" id="{{ $vId }}-list" x-ref="list" x-show="open" x-cloak>
                <template x-for="(o, n) in matches" :key="String(o.id)">
                    <li role="option" class="v-combo__opt" :id="'{{ $vId }}-' + n"
                        :class="{ 'is-empty': o.id === '' }"
                        :aria-selected="String(o.id) === value ? 'true' : 'false'"
                        :data-active="n === active ? 'true' : 'false'"
                        @mousedown.prevent="pick(o)" @mousemove="active = n"
                        x-text="o.name"></li>
                </template>
                <li class="v-combo__none" x-show="matches.length === 0">Nothing matches.</li>
            </ul>
        </div>
    </x-admin.field>
</div>

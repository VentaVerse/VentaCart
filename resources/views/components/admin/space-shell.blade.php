@props([
    'title',
    'space',          // the space's name in the title tail and the section picker
    'groups',         // resolved nav groups: [{label, items: [{key, label, icon, line, url}]}]
    'active' => null, // context-nav active item key
    'railActive' => null, // accepted for back-compat; the classic nav derives its own state
    'roots' => [],        // accepted for back-compat; the crossing died with the separate chrome
])
{{-- One admin, one chrome (owner ruling 2026-08-17, completed here).
     A space keeps what makes it a space, its own grouped context nav,
     as a second column beside the classic sidebar, inside the same
     shell as every other page: same slab, same topbar with the palette
     search, theme switch and account, same toasts. The icon strip and
     the separate top band are gone; they existed only because this
     used to be its own document. --}}
<x-admin.chrome :title="$title === $space ? $space : $title . ': ' . $space">
    @push('head')
        @vite('resources/css/admin/admin.css')
    @endpush
    <div class="v v-embed v-settings-root">
        <div class="v-settings__body">
            <x-admin.context-nav :groups="$groups" :active="$active" :label="$space" />

            <div class="v-settings__main">
                {{-- The context nav, for the widths that cannot show it.
                     Below 1023px the nav beside this column is hidden, and
                     this select stands in for it; above that width it is
                     redundant with the nav two inches to its left and must
                     not render at all. It leaked onto desktop for months
                     because it carried no base rule and only ever declared
                     itself inside the narrow media query, so the stylesheet
                     now states both halves of that intent. --}}
                <label class="v-snav__picker">
                    <span class="v-sr">{{ $space }} section</span>
                    <select class="v-control" onchange="if (this.value) window.location.href = this.value">
                        <option value="">Go to&hellip;</option>
                        @foreach ($groups as $group)
                            <optgroup label="{{ $group['label'] }}">
                                @foreach ($group['items'] as $item)
                                    <option value="{{ $item['url'] }}" @selected($active === $item['key'])>{{ $item['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>

                <div class="v-settings__measure">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</x-admin.chrome>

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
                {{-- Retired 2026-08-19 (owner ruling): the section jump was
                     redundant beside the context nav that lists the same
                     sections, and it only ever appeared on desktop through a
                     missing base rule. Where the nav is hidden (narrow
                     widths) the space's own hub carries the way between
                     sections. --}}
                <div class="v-settings__measure">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</x-admin.chrome>

{{--
    'active' is optional: when omitted it's derived from the request path via
    SettingsNav::activeKeyForPath() (longest-URL-prefix match, Task 7) —
    exactly what every settings-nav item's own URL already encodes. Pass it
    explicitly only where the URL genuinely can't say which sub-sidebar entry
    a page belongs to (e.g. a page mounted at a different literal path than
    its conceptual settings-nav home).
--}}
@props(['active' => null, 'title', 'wide' => false])
@php
    $groups = \App\Support\SettingsNav::groups(Auth::user());
    $activeKey = $active ?? \App\Support\SettingsNav::activeKeyForPath(request()->path());
@endphp
<x-admin.shell :title="$title . ': Settings'">
    <x-admin.page :title="$title">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
        <div class="flex gap-8">
            {{-- Sub-sidebar (desktop) --}}
            <nav class="hidden lg:block w-[13.5rem] shrink-0">
                <div class="sticky top-11 space-y-4">
                    <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-1.5 text-xs font-medium text-v-secondary hover:text-v-text">
                        <x-admin.icon name="chevron-right" class="w-3 h-3 rotate-180" /> All Settings
                    </a>
                    @foreach ($groups as $group)
                        <div>
                            <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-widest text-v-muted">{{ $group['label'] }}</p>
                            <div class="space-y-0.5">
                                @foreach ($group['items'] as $item)
                                    <a href="{{ \App\Support\SettingsNav::url($item) }}"
                                       class="block rounded-v-md px-2 py-1 text-[13px] transition-colors
                                           {{ $item['key'] === $activeKey ? 'bg-v-accent-weak font-semibold text-v-accent' : 'text-v-secondary hover:bg-v-hover hover:text-v-text' }}">
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </nav>

            {{-- Content (mobile select + slot) --}}
            <div class="min-w-0 flex-1 {{ $wide ? 'max-w-none' : 'max-w-2xl' }}">
                <select x-data @change="window.location.assign($event.target.value)"
                        class="v-control lg:hidden mb-5 w-full h-[var(--control-h)] rounded-v-md border border-v-border bg-v-surface px-2.5 text-[13px] text-v-text" aria-label="Settings section">
                    @foreach ($groups as $group)
                        <optgroup label="{{ $group['label'] }}">
                            @foreach ($group['items'] as $item)
                                <option value="{{ \App\Support\SettingsNav::url($item) }}" @selected($item['key'] === $activeKey)>{{ $item['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                {{ $slot }}
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>

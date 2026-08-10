{{--
    Settings is the ordinary page skeleton wearing the workspace rail.

    It used to run its own shell: its own two-column flex, its own
    sticky sidebar in utility strings, its own mobile <select>. All
    three are now x-admin.workspace, which the SEO workspace shares,
    so the only thing left here is the one job this component has --
    turning App\Support\SettingsNav into the shape the workspace
    speaks.

    'active' is optional: when omitted it's derived from the request path via
    SettingsNav::activeKeyForPath() (longest-URL-prefix match, Task 7),
    exactly what every settings-nav item's own URL already encodes. Pass it
    explicitly only where the URL genuinely can't say which sub-sidebar entry
    a page belongs to (e.g. a page mounted at a different literal path than
    its conceptual settings-nav home).

    There is no `wide` prop any more. It was opt-in, twenty-seven of the
    thirty-four settings screens opted in, and the seven that did not sat
    229px narrower than the page the reader had just come from. Settings is
    one workspace with one rail and one work column: a person moving between
    thirty screens all day should see the column stay where it was. The
    measure cap still exists on x-admin.workspace for a caller whose column
    really is a page of prose; settings has none.
--}}
@props(['active' => null, 'title'])
@php
    $activeKey = $active ?? \App\Support\SettingsNav::activeKeyForPath(request()->path());
    $groups = collect(\App\Support\SettingsNav::groups(Auth::user()))
        ->map(fn ($group) => [
            'label' => $group['label'],
            'items' => collect($group['items'])->map(fn ($item) => [
                'label' => $item['label'],
                'url' => \App\Support\SettingsNav::url($item),
                'active' => $item['key'] === $activeKey,
            ])->all(),
        ])->all();
@endphp
<x-admin.shell :title="$title . ': Settings'">
    <x-admin.page :title="$title">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
        <x-admin.workspace :groups="$groups" label="Settings sections"
            :back="route('admin.settings.index')" back-label="All Settings">
            {{ $slot }}
        </x-admin.workspace>
    </x-admin.page>
</x-admin.shell>

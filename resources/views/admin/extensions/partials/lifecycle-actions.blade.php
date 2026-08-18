{{-- The standard button set for one extension row (owner ruling
     2026-08-18): not installed shows Install and nothing else; installed
     shows Uninstall, Enable/Disable, Configure, in that order. Every
     listing includes this partial rather than composing its own buttons,
     so the grammar cannot drift. Expects $module (an ExtensionCard row). --}}
<span class="v3-actrow">
    @if (in_array('uninstall', $module['actions'], true))
        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'uninstall-{{ $module['name'] }}')">Uninstall</button>
    @endif
    @if (in_array('enable', $module['actions'], true))
        <form method="POST" action="{{ route('admin.extensions.enable', $module['name']) }}">
            @csrf
            <button type="submit" class="v3-btn v3-btn--good v3-btn--sm">Enable</button>
        </form>
    @endif
    @if (in_array('disable', $module['actions'], true))
        <form method="POST" action="{{ route('admin.extensions.disable', $module['name']) }}">
            @csrf
            <button type="submit" class="v3-btn v3-btn--secondary v3-btn--sm">Disable</button>
        </form>
    @endif
    @if (in_array('install', $module['actions'], true))
        <form method="POST" action="{{ route('admin.extensions.install', $module['name']) }}">
            @csrf
            <button type="submit" class="v3-btn v3-btn--primary v3-btn--sm">{{ $module['state'] === 'needs_migration' ? 'Update' : 'Install' }}</button>
        </form>
    @endif
    @if ($module['configure'])
        <a href="{{ $module['configure'] }}" class="v3-btn v3-btn--ghost v3-btn--sm">Configure</a>
    @elseif ($module['state'] === 'disabled')
        <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" disabled>Configure</button>
    @endif
</span>
@if (in_array('uninstall', $module['actions'], true))
    <x-admin3.confirm name="uninstall-{{ $module['name'] }}" title="Uninstall {{ $module['title'] }}?"
        message="Uninstalling removes its tables and everything in them. Its files stay, so it can be installed again, but the data does not come back."
        confirm="Uninstall"
        :action="route('admin.extensions.uninstall', $module['name'])" method="POST" />
@endif

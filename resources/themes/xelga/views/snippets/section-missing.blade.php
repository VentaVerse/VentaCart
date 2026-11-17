{{-- Shown in the editor preview only, for a section whose type the theme no longer has, or whose extension is switched off or removed. --}}
<div class="section-note" data-section-id="{{ $id }}" data-section-type="{{ $type }}" @if (! empty($extension)) data-section-source="{{ $extension }}" @endif role="note">
    @if (! empty($extension))
        {{ $extension }} is {{ ($absence ?? 'missing') === 'off' ? 'switched off' : 'not installed' }}.
    @else
        This section is no longer available. Remove it in the editor.
    @endif
</div>

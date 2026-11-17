{{-- In the editor only: the listing settings that live in VentaCart, read here, changed there. --}}
@if ($context->preview)
    <p class="editor-note listing__core-note" role="note"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"></use></svg>
        {{ $data['per_page'] ? $data['per_page'] . ' products per page.' : '' }} Products per page and which filters show are set in VentaCart, Settings, Catalog.
        @if ($data['settings_url'])<a href="{{ $data['settings_url'] }}" target="_blank" rel="noopener">Open Catalog settings</a>@endif
    </p>
@endif

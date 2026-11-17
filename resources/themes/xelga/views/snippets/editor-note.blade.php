{{-- A note only the editor's preview shows, for a section that has nothing to show shoppers yet. --}}
@if ($context->preview)
    <p class="editor-note" role="note"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"></use></svg> {{ $note }}</p>
@endif

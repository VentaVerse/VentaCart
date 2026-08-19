@props(['formId', 'onlyWhenDirty' => false])
{{-- The page's save controls. Rendered either in the page header (the
     default, teleported there by x-admin.form) or beside the fields when
     a page carries more than one save scope. Both placements are the same
     markup, so a secondary form's Save is recognisably the same control
     as a primary one. --}}
<div class="v-formacts">
    {{-- The awareness the old save bar existed to provide, kept without
         the bar: a quiet word, announced, next to the button that acts
         on it. --}}
    <span class="v-formacts__note" x-show="dirty" x-cloak role="status">Unsaved changes</span>
    <button type="button" class="v-btn v-btn--ghost" x-show="dirty" x-cloak
            @click="discard()" :disabled="saving">Discard</button>
    {{-- A control whose only field is a switch has nothing to save until
         the switch moves, so its Save waits to be needed rather than
         standing next to the page's own Save looking like a rival. --}}
    <button type="submit" form="{{ $formId }}" class="v-btn v-btn--primary" :disabled="saving"
            @if ($onlyWhenDirty) x-show="dirty || saving" x-cloak @endif>
        <span x-text="saving ? 'Saving…' : 'Save'">Save</span>
    </button>
</div>

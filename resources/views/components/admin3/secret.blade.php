@props(['name', 'masked' => null, 'value' => null, 'placeholder' => ''])
{{-- A stored secret: masked and read-only at rest, revealed by View,
     replaced by Edit. Leaving Edit's input blank keeps the stored value
     (the controller skips empty encrypted fields). --}}
<div class="v3-secret" x-data="{ mode: @js($masked ? 'rest' : 'edit') }">
    <template x-if="mode === 'rest'">
        <div class="v3-secret__row">
            <span class="v3-secret__value v3-mono">{{ $masked }}</span>
            @if ($value !== null)
                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="mode = 'view'">View</button>
            @endif
            <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="mode = 'edit'; $nextTick(() => $refs.input?.focus())">Edit</button>
        </div>
    </template>
    <template x-if="mode === 'view'">
        <div class="v3-secret__row">
            <span class="v3-secret__value v3-mono">{{ $value }}</span>
            <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="mode = 'rest'">Hide</button>
            <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="mode = 'edit'; $nextTick(() => $refs.input?.focus())">Edit</button>
        </div>
    </template>
    <template x-if="mode === 'edit'">
        <div class="v3-secret__row">
            <input type="password" name="{{ $name }}" x-ref="input" class="v3-control v3-mono"
                   placeholder="{{ $masked ? 'Leave blank to keep the current key' : $placeholder }}"
                   autocomplete="new-password">
            @if ($masked)
                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="mode = 'rest'; $refs.input.value = ''; $refs.input.dispatchEvent(new Event('input', { bubbles: true }))">Cancel</button>
            @endif
        </div>
    </template>
</div>

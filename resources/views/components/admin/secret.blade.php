@props(['name', 'masked' => null, 'revealUrl' => null, 'placeholder' => ''])
@php
    // One mask convention: fixed-width bullets, plus the identifying last
    // four when the masked form carries a tail (never for passwords, which
    // pass bullets only). The mask leaks neither length nor content.
    $tail = $masked !== null ? substr(ltrim($masked, "\u{2022}"), -4) : '';
    $display = $masked !== null ? ('••••••••••••' . ($tail !== '' ? ' ' . $tail : '')) : null;
@endphp
{{-- A stored secret: masked and read-only at rest. View fetches the
     plaintext on demand (never server-rendered into the page); Edit
     replaces it; blank on submit keeps the stored value. Logic lives in
     admin.js (vSecret): x-if clones die on mode switches, so the
     component root is held in a closure there. --}}
<div class="v-secret" x-data="vSecret(@js(['display' => $display, 'revealUrl' => $revealUrl]))">
    <template x-if="mode === 'rest'">
        <div class="v-secret__row">
            <span class="v-secret__value v-mono" x-text="shown ?? @js($display)"></span>
            @if ($revealUrl)
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" :disabled="busy"
                        @click="reveal()" x-text="shown !== null ? 'Hide' : 'View'">View</button>
            @endif
            <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="edit()">Edit</button>
        </div>
    </template>
    <template x-if="mode === 'edit'">
        <div class="v-secret__row">
            <input type="password" name="{{ $name }}" class="v-control v-mono"
                   placeholder="{{ $display ? 'Leave blank to keep the current key' : $placeholder }}"
                   autocomplete="new-password">
            @if ($display)
                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="cancel()">Cancel</button>
            @endif
        </div>
    </template>
</div>

@props([
    'name',
    'masked' => null,
    'revealUrl' => null,
    'placeholder' => '',
    'label' => null,
    'type' => 'password',
    'maxlength' => null,
    'unreadable' => false,
    'editing' => false,
    'removeName' => null,
    'removeValue' => null,
])
@php
    // One mask convention: sixteen bullets, plus the identifying last four
    // when the masked form carries a tail (never for passwords, which pass
    // bullets only). The mask leaks neither length nor content.
    $tail = $masked !== null ? mb_substr(ltrim($masked, "\u{2022}"), -4) : '';
    $display = $masked !== null ? (str_repeat("\u{2022}", 16) . $tail) : null;
    $stored = $display !== null || $unreadable;
    $what = $label ?? 'key';
    $faceLabel = 'Change ' . $what . ($tail !== '' ? ' (saved, ends in ' . $tail . ')' : ' (saved)');
    $badLabel = 'Change ' . $what . " (the saved value can\u{2019}t be read)";
@endphp
{{-- A stored secret, drawn as one input-shaped box.

     Saved: the mask sits where the value would be, with the tools inside
     the box's right edge: show/hide and copy (both fetch the value from
     revealUrl on demand, so the page never carries it), Change, and a
     "more" menu holding Remove. Clicking the mask is Change too.
     Changing: the box becomes an empty input; Cancel restores the mask.
     Never saved: a plain input.

     While saved and unchanged the control posts nothing, which the server
     reads as "keep what is stored". Remove (only when removeName is given)
     posts removeName=removeValue, so clearing is always deliberate, never
     a side effect of a blank field. Logic: resources/js/admin/secret.js. --}}
<div class="v-secret" x-data="vSecret(@js([
        'stored' => $stored,
        'unreadable' => (bool) $unreadable,
        'editing' => (bool) $editing,
        'revealUrl' => $revealUrl,
        'label' => $what,
    ]))">
    <template x-if="mode === 'rest'">
        <div class="v-secret__box" :class="unreadable && 'v-secret__box--bad'">
            <template x-if="shown === null">
                <button type="button" class="v-secret__face" data-secret-face @click="edit()"
                        :aria-label="unreadable ? @js($badLabel) : @js($faceLabel)">
                    <span class="v-secret__mask" x-show="!unreadable" aria-hidden="true"><span class="v-secret__dots"><span class="v-secret__wide">{{ str_repeat("\u{2022}", 8) }}</span>{{ str_repeat("\u{2022}", 8) }}</span>@if ($tail !== '')<span class="v-secret__tail v-mono">{{ $tail }}</span>@endif</span>
                    <span class="v-secret__bad" x-show="unreadable" x-cloak>Saved value can&rsquo;t be read</span>
                </button>
            </template>
            <template x-if="shown !== null">
                <input type="text" readonly class="v-secret__input v-mono" :value="shown"
                       aria-label="{{ $what }}" data-secret-shown spellcheck="false" @focus="$event.target.select()">
            </template>
            <span class="v-secret__tools">
                @if ($revealUrl)
                    <button type="button" class="v-secret__tool" x-show="!unreadable" data-secret-show
                            :disabled="busy !== null" :aria-busy="busy === 'show'" :aria-pressed="shown !== null"
                            :aria-label="(shown !== null ? 'Hide ' : 'Show ') + @js($what)" @click="toggle()">
                        <x-admin.icon name="spinner" class="v-spin" x-show="busy === 'show'" x-cloak />
                        <x-admin.icon name="eye" x-show="busy !== 'show' && shown === null" />
                        <x-admin.icon name="eye-off" x-show="busy !== 'show' && shown !== null" x-cloak />
                    </button>
                    <button type="button" class="v-secret__tool" x-show="!unreadable"
                            :disabled="busy !== null" :aria-busy="busy === 'copy'"
                            aria-label="Copy {{ $what }}" @click="copy()">
                        <x-admin.icon name="spinner" class="v-spin" x-show="busy === 'copy'" x-cloak />
                        <x-admin.icon name="copy" x-show="busy !== 'copy' && !copied" />
                        <x-admin.icon name="check" class="v-secret__ok" x-show="busy !== 'copy' && copied" x-cloak />
                    </button>
                @endif
                <button type="button" class="v-secret__word" data-secret-change
                        aria-label="Change {{ $what }}" @click="edit()">Change</button>
                @if ($removeName)
                    <span class="v-secret__more" @click.outside="menu = false" @keydown.escape.stop="closeMenu()">
                        <button type="button" class="v-secret__tool" data-secret-more
                                aria-haspopup="true" :aria-expanded="menu"
                                aria-label="More actions for {{ $what }}" @click="menu = !menu">
                            <x-admin.icon name="more" />
                        </button>
                        <span class="v-secret__menu" x-show="menu" x-cloak x-transition.opacity.duration.130ms>
                            <button type="button" class="v-secret__item v-secret__item--bad" data-secret-remove
                                    aria-label="Remove {{ $what }}" @click="askRemove()">
                                <x-admin.icon name="trash" />Remove
                            </button>
                        </span>
                    </span>
                @endif
            </span>
            <span class="v-sr" role="status" x-text="announce"></span>
        </div>
    </template>
    <template x-if="mode === 'edit'">
        <div class="v-secret__box">
            <input type="{{ $type === 'text' ? 'text' : 'password' }}" name="{{ $name }}"
                   class="v-secret__input v-mono" placeholder="{{ $placeholder }}" aria-label="{{ $what }}"
                   @if ($maxlength) maxlength="{{ (int) $maxlength }}" @endif
                   autocomplete="{{ $type === 'text' ? 'off' : 'new-password' }}"
                   autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true">
            @if ($stored)
                <span class="v-secret__tools">
                    <button type="button" class="v-secret__word" data-secret-cancel
                            aria-label="Cancel changing {{ $what }}" @click="cancel()">Cancel</button>
                </span>
            @endif
        </div>
    </template>
    @if ($removeName)
        <template x-if="mode === 'removed'">
            <div class="v-secret__box v-secret__box--gone">
                <input type="hidden" name="{{ $removeName }}" value="{{ $removeValue }}">
                <span class="v-secret__note">Removed on save</span>
                <span class="v-secret__tools">
                    <button type="button" class="v-secret__word" data-secret-undo
                            aria-label="Undo removing {{ $what }}" @click="undo()">Undo</button>
                </span>
            </div>
        </template>
        <template x-if="confirming">
            <div class="v-overlay" x-id="['secret-remove']" @click.self="keep()" @keydown.escape.window="keep()">
                <div class="v-modal" role="alertdialog" aria-modal="true" :aria-labelledby="$id('secret-remove')">
                    <h2 class="v-modal__title" :id="$id('secret-remove')">Remove {{ $what }}?</h2>
                    <p class="v-modal__body">The saved value is deleted when you save this page.</p>
                    <div class="v-modal__acts">
                        <button type="button" class="v-btn v-btn--ghost" data-secret-keep @click="keep()">Cancel</button>
                        <button type="button" class="v-btn v-btn--danger-fill" @click="remove()">Remove</button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

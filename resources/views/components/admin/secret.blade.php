{{-- A stored secret, in one field.

     The Turnstile secret key used to be two things: an always-empty
     password box you typed a replacement into, and a line of prose
     underneath printing the current key as 31 bullets and its last
     four characters. The prose is where the key actually lived, so it
     was the thing you read, and it wrapped over three lines because a
     run of bullets has no break opportunity in it. Two controls, one
     value, and the value outside the box that owns it.

     One field instead, in three states:

       resting   the mask, read only, nothing submitted;
       revealed  the stored value in the same box, still read only;
       editing   the stored value, editable, and submitted.

     The value lives in Alpine state and the input is bound to it with
     x-model, so nothing re-renders the box out from under a person
     typing in it. `name` is only present while editing: a resting
     field posts nothing, which is exactly what SettingController's
     "an empty encrypted key keeps the stored one" branch already
     expects. Save behaviour, validation and route are untouched.

     A key that is not set yet has nothing to protect, so it renders as
     an ordinary editable field with no View and no Edit. --}}
@props([
    'name',
    'masked' => null,
    'value' => null,
    'placeholder' => null,
])
@php
    $vHasValue = filled($masked);
    $vState = [
        'mask' => (string) $masked,
        'plain' => (string) $value,
        'field' => $name,
    ];
@endphp
<div {{ $attributes->merge(['class' => 'v-secret']) }}
     x-data="{
        has: {{ $vHasValue ? 'true' : 'false' }},
        mask: {{ \Illuminate\Support\Js::from($vState['mask']) }},
        plain: {{ \Illuminate\Support\Js::from($vState['plain']) }},
        field: {{ \Illuminate\Support\Js::from($vState['field']) }},
        shown: false,
        editing: false,
        text: '',
        init() {
            this.editing = ! this.has;
            this.shown = ! this.has;
            this.text = this.has ? this.mask : '';
        },
        toggle() {
            if (this.editing) return;
            this.shown = ! this.shown;
            this.text = this.shown ? this.plain : this.mask;
        },
        edit() {
            this.editing = true;
            this.shown = true;
            this.text = this.plain;
            this.$nextTick(() => { this.$refs.box.focus(); this.$refs.box.select(); });
        },
        cancel() {
            this.editing = false;
            this.shown = false;
            this.text = this.mask;
        },
     }">
    <div class="v-secret__row">
        <input type="text" id="{{ $name }}" x-ref="box" x-model="text"
            :name="editing ? field : false"
            :readonly="! editing"
            :aria-readonly="editing ? 'false' : 'true'"
            @if ($vHasValue) readonly aria-readonly="true" @else name="{{ $name }}" @endif
            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            class="v-control v-secret__box v-mono">

        @if ($vHasValue)
            <div class="v-secret__acts">
                <x-admin.button type="button" variant="secondary" size="sm"
                    x-bind:aria-pressed="shown.toString()"
                    x-bind:disabled="editing"
                    @click="toggle()">
                    <x-admin.icon name="eye" class="w-4 h-4" x-show="! shown" />
                    <x-admin.icon name="eye-off" class="w-4 h-4" x-show="shown" x-cloak />
                    <span x-text="shown ? 'Hide' : 'View'">View</span>
                </x-admin.button>
                <x-admin.button type="button" variant="secondary" size="sm"
                    @click="editing ? cancel() : edit()">
                    <span x-text="editing ? 'Cancel' : 'Edit'">Edit</span>
                </x-admin.button>
            </div>
        @endif
    </div>
</div>

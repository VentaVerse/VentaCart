{{-- The one place a destructive button is filled: this button IS the
     destructive act, so components/buttons.css fills it through the
     .v-confirm scope rather than through a second variant name.

     Three axes, one component:

     - `tone` is what the button means. `danger` is the default and the
       only one that fills red and announces as an alertdialog, because
       an alertdialog interrupts and not every confirmation earns that.
       `primary` is for a step that is merely worth pausing on
       (signing in as a customer), where a red button would be a lie.
     - `action` submits a form, which covers every server-side delete.
       `onConfirm` runs an Alpine expression in the caller's own scope,
       which covers the fetch-driven actions that used to reach for
       window.confirm because this component only spoke forms.
     - `message` is the question, or the default slot when the question
       has to be bound to Alpine state (the name of the file about to
       be deleted). The `fields` slot carries any hidden input the
       guarded action needs.

     The copy rule: name what is destroyed and what happens next. --}}
@props([
    'name',
    'title',
    'message' => null,
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
    'tone' => 'danger',
    'action' => null,
    'method' => 'POST',
    'onConfirm' => null,
])
@php
    $isGet = strtoupper($method) === 'GET';
    $isDanger = $tone === 'danger';
    $messageId = $name . '-message';
@endphp
<x-admin.modal :name="$name" :title="$title" maxWidth="sm"
               class="{{ $isDanger ? 'v-confirm' : '' }}"
               :role="$isDanger ? 'alertdialog' : 'dialog'"
               :describedby="$messageId">
    <div id="{{ $messageId }}" class="text-[13px] text-v-secondary">{{ $message ?? $slot }}</div>

    @if ($action)
        <form method="{{ $isGet ? 'GET' : 'POST' }}" action="{{ $action }}" data-v-form class="mt-4 flex justify-end gap-2">
            @unless ($isGet)
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif
            @endunless
            {{ $fields ?? '' }}
            <x-admin.button variant="secondary" type="button" @click="open = false">{{ $cancel }}</x-admin.button>
            <x-admin.button :variant="$isDanger ? 'danger' : 'primary'" type="submit">{{ $confirm }}</x-admin.button>
        </form>
    @else
        <div class="mt-4 flex justify-end gap-2">
            <x-admin.button variant="secondary" type="button" @click="open = false">{{ $cancel }}</x-admin.button>
            <x-admin.button :variant="$isDanger ? 'danger' : 'primary'" type="button"
                            @click="open = false; {{ $onConfirm }}">{{ $confirm }}</x-admin.button>
        </div>
    @endif
</x-admin.modal>

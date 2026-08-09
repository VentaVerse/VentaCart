@props(['name', 'title', 'message', 'confirm' => 'Confirm', 'action', 'method' => 'POST'])
{{-- The one place a destructive button is filled: this button IS the
     destructive act, so components/buttons.css fills it through the
     .v-confirm scope rather than through a second variant name. --}}
<x-admin.modal :name="$name" :title="$title" maxWidth="sm" class="v-confirm">
    <p class="text-[13px] text-v-secondary">{{ $message }}</p>
    <form method="POST" action="{{ $action }}" data-v-form class="mt-4 flex justify-end gap-2">
        @csrf
        @if (! in_array(strtoupper($method), ['GET', 'POST']))
            @method($method)
        @endif
        <x-admin.button variant="secondary" type="button" @click="open = false">Cancel</x-admin.button>
        <x-admin.button variant="danger" type="submit">{{ $confirm }}</x-admin.button>
    </form>
</x-admin.modal>

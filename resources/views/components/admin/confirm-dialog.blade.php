@props(['name', 'title', 'message', 'confirm' => 'Confirm', 'action', 'method' => 'POST'])
<x-admin.modal :name="$name" :title="$title" maxWidth="sm">
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

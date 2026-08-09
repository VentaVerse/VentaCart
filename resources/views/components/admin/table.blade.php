{{-- A raised sheet holding a table that scrolls sideways and never
     clips. The scroll box carries the sheet's own radius so a wide
     table's first and last columns still round with the panel
     instead of being sliced by it. --}}
<div {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg']) }}>
    <div class="v-table-wrap overflow-x-auto">
        <table class="v-table w-full text-[13px]">
            @isset($head)
                <thead><tr>{{ $head }}</tr></thead>
            @endisset
            <tbody class="divide-y divide-v-border">{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        <div class="px-4 py-2.5 border-t border-v-border">{{ $footer }}</div>
    @endisset
</div>

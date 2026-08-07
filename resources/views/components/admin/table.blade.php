<div {{ $attributes->merge(['class' => 'bg-v-surface border border-v-border rounded-v-lg']) }}>
    <div class="overflow-x-auto">
        <table class="v-table w-full text-[13px]">
            @isset($head)
                <thead><tr class="bg-v-sunken">{{ $head }}</tr></thead>
            @endisset
            <tbody class="divide-y divide-v-border">{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        <div class="px-4 py-2.5 border-t border-v-border">{{ $footer }}</div>
    @endisset
</div>

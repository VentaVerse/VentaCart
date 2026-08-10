{{-- A raised sheet holding a table that scrolls sideways and never
     clips. The scroll box carries the sheet's own radius so a wide
     table's first and last columns still round with the panel
     instead of being sliced by it.

     Three densities, one table. The default divides rows with
     divide-y on the body. `ruled` divides with a border on the cell
     instead, which is the only way a table carrying rowspans can rule
     correctly. `roomy` is verification density: rows carrying an
     image someone actually looks at. `tiered` turns on the two-tier
     grammar, a band of record-level facts above one row per line
     item.

     `bare` drops the sheet, for a table that already sits inside a
     panel. --}}
@props(['ruled' => false, 'roomy' => false, 'tiered' => false, 'bare' => false])
@php
$table = 'v-table'
    . ($ruled || $tiered ? ' v-table--ruled' : '')
    . ($roomy ? ' v-table--roomy' : '')
    . ($tiered ? ' v-table--tiered' : '');
$divide = ($ruled || $tiered) ? null : 'divide-y divide-v-border';
@endphp
<div {{ $bare ? $attributes : $attributes->merge(['class' => 'v-sheet bg-v-surface border border-v-border rounded-v-lg']) }}>
    <div class="v-table-wrap overflow-x-auto">
        <table class="{{ $table }}">
            @isset($head)
                <thead><tr>{{ $head }}</tr></thead>
            @endisset
            <tbody @if ($divide) class="{{ $divide }}" @endif>{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        <div class="px-4 py-2.5 border-t border-v-border">{{ $footer }}</div>
    @endisset
</div>

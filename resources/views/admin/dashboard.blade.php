@php
    $statusTones = [
        'Pending' => 'warning', 'Unpaid' => 'warning', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'success', 'Complete' => 'success',
        'Canceled' => 'danger', 'Failed' => 'danger',
        'Refunded' => 'neutral', 'Expired' => 'neutral',
    ];

    $money = function (float $amount) use ($currency) {
        return ['mark' => $currency, 'amount' => number_format($amount, 2), 'zero' => $amount == 0.0];
    };
@endphp
<x-admin.shell title="Today">
    <article class="v-sheet" aria-labelledby="v-sheet-date">
        <!--
IMPECCABLE DIRECTION CONTRACT
THESIS: The admin's home is today's page in the shop's daybook. It refuses the widget-scatter dashboard of stat tiles and unrelated panels that every admin ships.
OWN-WORLD: The established Venta Blotter: Archivo on tinted paper, raised white sheets, hairline rules, tabular mono figures, azure slab, warm brass action. No new visual language.
STORY: The merchant opens the admin in the morning, reads down one sheet, and knows what today is and what it needs from them.
FIRST VIEWPORT: The date at headline scale with one plain sentence of standing beneath it, then the day's figures as a ruled band with yesterday alongside, then the day's work as actionable lines.
FORM: The day sheet, candidate 5 of 7 ordered by resonance, seed key 24dc4e5d.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
-->

        {{-- 1. The head ------------------------------------------------ --}}
        <header class="v-sheet__head">
            <div class="v-sheet__headtext">
                <h1 class="v-sheet__date" id="v-sheet-date">{{ $sheetDate->format('l, j F Y') }}</h1>
                <p class="v-sheet__standing">{{ $standing }}</p>
            </div>
            <x-admin.button variant="primary" :href="route('admin.orders.create')">
                <x-admin.icon name="plus" class="w-4 h-4" /> New order
            </x-admin.button>
        </header>

        {{-- 2. The day's figures --------------------------------------- --}}
        <section class="v-sheet__mv" aria-label="The day's figures">
            <div class="v-band">
                <div class="v-fig v-fig--head" aria-hidden="true">
                    <span class="v-fig__name"></span>
                    <span class="v-fig__cell v-label">Today</span>
                    @foreach ($comparisons as $comparison)
                        <span class="v-fig__cell v-label">{{ $comparison['label'] }}</span>
                    @endforeach
                </div>

                @foreach ($figures as $figure)
                    @php $cells = [['key' => 'today', 'label' => 'Today', 'now' => true]]; @endphp
                    @foreach ($comparisons as $comparison)
                        @php $cells[] = ['key' => $comparison['key'], 'label' => $comparison['label'], 'now' => false]; @endphp
                    @endforeach
                    <div class="v-fig">
                        <span class="v-fig__name">
                            {{ $figure['label'] }}
                            @if ($figure['hint'])<x-admin.hint :label="'What ' . lcfirst($figure['label']) . ' counts'">{{ $figure['hint'] }}</x-admin.hint>@endif
                        </span>
                        @foreach ($cells as $cell)
                            @php
                                $raw = $figure['values'][$cell['key']] ?? 0;
                                $m = $figure['money'] ? $money((float) $raw) : null;
                                $isZero = $figure['money'] ? $m['zero'] : ((int) $raw === 0);
                            @endphp
                            <span class="v-fig__cell {{ $cell['now'] ? 'v-fig__cell--now' : '' }}">
                                <span class="v-fig__when">{{ $cell['label'] }}</span>
                                @if ($figure['money'])
                                    <span class="v-money {{ $isZero ? 'v-money--zero' : '' }}">
                                        <span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span>
                                    </span>
                                @else
                                    <span class="v-num v-fig__plain {{ $isZero ? 'v-fig__plain--zero' : '' }}">{{ number_format((int) $raw) }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 3. The day's work ------------------------------------------- --}}
        <section class="v-sheet__mv" aria-labelledby="v-sheet-work">
            <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-work">Needs doing</h2>
            <ul class="v-lines">
                @foreach ($workLines as $line)
                    <li>
                        <a href="{{ $line['url'] }}" class="v-line">
                            <span class="v-line__label">{{ $line['label'] }}</span>
                            <span class="v-num v-line__count {{ $line['count'] === 0 ? 'v-line__count--zero' : '' }}">{{ number_format($line['count']) }}</span>
                            <x-admin.icon name="chevron-right" class="v-line__go" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- 4. The day's entries ---------------------------------------- --}}
        <section class="v-sheet__mv" aria-labelledby="v-sheet-entries">
            <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-entries">
                Today's entries
                @if ($entriesTruncated)
                    <a href="{{ route('admin.orders.index') }}" class="v-sheet__mvlink">All orders</a>
                @endif
            </h2>

            @if ($entries->isEmpty())
                <p class="v-sheet__blank">
                    Nothing entered yet today.@if ($lastEntryLabel) The last order came in {{ $lastEntryLabel }}.@endif
                </p>
            @else
                <div class="v-sheet__scroll">
                    <table class="v-table w-full text-[13px]">
                        <thead>
                            <tr>
                                <x-admin.table.th>Time</x-admin.table.th>
                                <x-admin.table.th>Customer</x-admin.table.th>
                                <x-admin.table.th class="v-col-bought">Bought</x-admin.table.th>
                                <x-admin.table.th align="right">Amount</x-admin.table.th>
                                <x-admin.table.th>Status</x-admin.table.th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-v-border">
                            @foreach ($entries as $entry)
                                @php
                                    $items = $entry->products;
                                    $lead = $items->first();
                                    $extra = max($items->count() - 1, 0);
                                    $m = $money((float) $entry->total);
                                @endphp
                                <x-admin.table.row href="{{ route('admin.orders.show', $entry) }}">
                                    <x-admin.table.cell mono class="whitespace-nowrap text-v-secondary">{{ $entry->created_at->format('g:i a') }}</x-admin.table.cell>
                                    <x-admin.table.cell class="text-v-text">{{ $entry->full_name }}</x-admin.table.cell>
                                    <x-admin.table.cell class="v-col-bought text-v-secondary">
                                        @if ($lead)
                                            <span class="v-entry__item">{{ $lead->name }}@if ($lead->variant_label), {{ $lead->variant_label }}@endif</span>
                                            @if ($extra > 0)<span class="v-entry__more">and {{ $extra }} more</span>@endif
                                        @else
                                            <span class="v-entry__more">No line items</span>
                                        @endif
                                    </x-admin.table.cell>
                                    <x-admin.table.cell>
                                        <span class="v-money {{ $m['zero'] ? 'v-money--zero' : '' }}">
                                            <span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span>
                                        </span>
                                    </x-admin.table.cell>
                                    <x-admin.table.cell>
                                        <x-admin.badge :tone="$statusTones[$entry->status?->name] ?? 'neutral'">{{ $entry->status?->name }}</x-admin.badge>
                                    </x-admin.table.cell>
                                </x-admin.table.row>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 5. Standing matters ----------------------------------------- --}}
        <section class="v-sheet__mv v-sheet__mv--foot" aria-labelledby="v-sheet-standing">
            <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-standing">Outstanding</h2>
            <ul class="v-lines">
                <li>
                    <a href="{{ route('admin.automations.index') }}" class="v-line">
                        <span class="v-line__label">
                            Automations overdue
                            @if ($unhealthyAutomations->isNotEmpty())
                                @php
                                    $named = $unhealthyAutomations->pluck('name');
                                    $rest = $named->count() - 3;
                                    $detail = $named->take(3)->join(', ') . ($rest > 0 ? ', and ' . $rest . ' more' : '');
                                @endphp
                                <span class="v-line__detail">{{ $detail }}</span>
                            @endif
                        </span>
                        <span class="v-num v-line__count {{ $unhealthyAutomations->isEmpty() ? 'v-line__count--zero' : '' }}">{{ $unhealthyAutomations->count() }}</span>
                        <x-admin.icon name="chevron-right" class="v-line__go" />
                    </a>
                </li>
                @foreach ($standingLines as $line)
                    <li>
                        <a href="{{ $line['url'] }}" class="v-line">
                            <span class="v-line__label">{{ $line['label'] }}</span>
                            <span class="v-num v-line__count {{ $line['count'] === 0 ? 'v-line__count--zero' : '' }}">{{ number_format($line['count']) }}</span>
                            <x-admin.icon name="chevron-right" class="v-line__go" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </article>
</x-admin.shell>

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
THESIS: The admin's home is today's page in the shop's daybook, and it opens with where the business stands. It refuses the widget-scatter dashboard of stat tiles and unrelated panels that every admin ships.
OWN-WORLD: The established Venta Blotter: Archivo on tinted paper, raised white sheets, hairline rules, tabular mono figures, azure slab, warm brass action. No new visual language.
STORY: The merchant opens the admin in the morning, reads down one sheet, knows whether the business is alright, and then knows what today needs from them.
FIRST VIEWPORT: The date at headline scale with one plain sentence of standing beneath it, then two years of revenue as one plot, then this month and today each set against the reference that makes them mean something.
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

        @if ($hasTraded)
            {{-- 2. The pulse -------------------------------------------- --}}
            <section class="v-sheet__mv" aria-labelledby="v-sheet-pulse">
                <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-pulse">Standing</h2>

                @include('admin.partials.pulse-plot', ['plot' => $plot, 'currency' => $currency])

                @foreach ($bands as $band)
                    <div class="v-bandblock">
                        <h3 class="v-label v-bandblock__title">
                            {{ $band['title'] }}
                            @if ($band['hint'])<x-admin.hint :label="$band['title']">{{ $band['hint'] }}</x-admin.hint>@endif
                        </h3>

                        <div class="v-band v-band--pulse">
                            <div class="v-fig v-fig--head" aria-hidden="true">
                                <span class="v-fig__name"></span>
                                @foreach ($band['columns'] as $column)
                                    <span class="v-fig__cell v-label">{{ $column['label'] }}</span>
                                @endforeach
                                <span class="v-fig__cell v-label">Change</span>
                            </div>

                            @foreach ($band['rows'] as $row)
                                <div class="v-fig">
                                    <span class="v-fig__name">
                                        {{ $row['label'] }}
                                        @if ($row['hint'])<x-admin.hint :label="'What ' . lcfirst($row['label']) . ' counts'">{{ $row['hint'] }}</x-admin.hint>@endif
                                    </span>

                                    @foreach ($band['columns'] as $column)
                                        @php
                                            $blank = $column['key'] === 'ref' && $band['referenceMissing'];
                                            $raw = $row['values'][$column['key']] ?? 0;
                                            $m = $row['money'] ? $money((float) $raw) : null;
                                            $isZero = $row['money'] ? $m['zero'] : ((int) $raw === 0);
                                        @endphp
                                        <span class="v-fig__cell {{ $column['lead'] ? 'v-fig__cell--now' : '' }}">
                                            <span class="v-fig__when">{{ $column['label'] }}</span>
                                            @if ($blank)
                                                <span class="v-fig__plain v-fig__plain--zero">No record</span>
                                            @elseif ($row['money'])
                                                <span class="v-money {{ $isZero ? 'v-money--zero' : '' }}">
                                                    <span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span>
                                                </span>
                                            @else
                                                <span class="v-num v-fig__plain {{ $isZero ? 'v-fig__plain--zero' : '' }}">{{ number_format((int) $raw) }}</span>
                                            @endif
                                        </span>
                                    @endforeach

                                    <span class="v-fig__cell v-fig__cell--change">
                                        <span class="v-fig__when">Change</span>
                                        @if ($band['referenceMissing'])
                                            <span class="v-change v-change--level">Nothing to compare</span>
                                        @else
                                            <span class="v-change v-change--{{ $row['change']['direction'] }}">
                                                @if ($row['change']['direction'] !== 'level')
                                                    <x-admin.icon :name="$row['change']['direction'] === 'ahead' ? 'arrow-up-right' : 'arrow-down-right'" class="v-change__go" />
                                                @endif
                                                {{ $row['change']['words'] }}
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @else
            {{-- 2. Before the first order ------------------------------- --}}
            <section class="v-sheet__mv" aria-labelledby="v-sheet-open">
                <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-open">
                    To open
                    <a href="{{ route('admin.products.create') }}" class="v-sheet__mvlink">Add a product</a>
                </h2>
                <ul class="v-lines">
                    @foreach ($openingLines as $line)
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
        @endif

        {{-- 3. The day's work ------------------------------------------- --}}
        @if ($hasTraded || ($workNeeded ?? false))
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
        @endif

        @if ($hasTraded)
            {{-- 4. The running accounts --------------------------------- --}}
            <section class="v-sheet__mv" aria-labelledby="v-sheet-accounts">
                <h2 class="v-subhead v-sheet__mvhead" id="v-sheet-accounts">
                    Running accounts
                    <span class="v-sheet__mvnote">{{ $accountsLabel }}</span>
                </h2>

                @if ($accounts['orders'] === 0)
                    <p class="v-sheet__blank">Nothing sold in the {{ lcfirst($accountsLabel) }}.</p>
                @else
                    <div class="v-accounts">
                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">Carrying the money</h3>
                            <ul class="v-rank">
                                @foreach ($accounts['byRevenue'] as $item)
                                    @php $m = $money((float) $item['revenue']); @endphp
                                    <li class="v-rank__row">
                                        <span class="v-rank__name">{{ $item['name'] }}</span>
                                        <span class="v-money"><span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span></span>
                                        <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                            <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                            <rect class="v-rank__fill" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                        </svg>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">Moving off the shelf</h3>
                            <ul class="v-rank">
                                @foreach ($accounts['byUnits'] as $item)
                                    <li class="v-rank__row">
                                        <span class="v-rank__name">{{ $item['name'] }}</span>
                                        <span class="v-num v-rank__units">{{ number_format($item['units']) }}</span>
                                        <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                            <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                            <rect class="v-rank__fill v-rank__fill--units" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                        </svg>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">How it was paid</h3>
                            <svg class="v-mixbar" viewBox="0 0 100 6" preserveAspectRatio="none" role="img"
                                 aria-label="Share of revenue by payment method, listed below">
                                @php $offset = 0; @endphp
                                @foreach ($accounts['payments'] as $i => $method)
                                    @php
                                        $w = round($method['share'] * 100, 3);
                                        $x = $offset;
                                        $offset += $w;
                                    @endphp
                                    <rect class="v-mixbar__seg v-acc-{{ $i % 6 }}" x="{{ $x }}" y="0" width="{{ $w }}" height="6"></rect>
                                @endforeach
                            </svg>
                            <ul class="v-mix">
                                @foreach ($accounts['payments'] as $i => $method)
                                    @php $m = $money((float) $method['revenue']); @endphp
                                    <li class="v-mix__row">
                                        <svg class="v-mix__sw" width="9" height="9" viewBox="0 0 9 9" aria-hidden="true" focusable="false">
                                            <rect class="v-acc-{{ $i % 6 }}" width="9" height="9" rx="2"></rect>
                                        </svg>
                                        <span class="v-mix__name">{{ $method['label'] }}</span>
                                        <span class="v-num v-mix__share">{{ number_format($method['share'] * 100, 1) }}%</span>
                                        <span class="v-money"><span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">
                                Buyers
                                <x-admin.hint label="What counts as a returning buyer">A buyer is one email address. Returning means that address had ordered before this period.</x-admin.hint>
                            </h3>
                            <ul class="v-mix">
                                <li class="v-mix__row v-mix__row--plain">
                                    <span class="v-mix__name">New</span>
                                    <span class="v-num v-mix__share">{{ $accounts['buyers']['total'] > 0 ? number_format($accounts['buyers']['new'] / $accounts['buyers']['total'] * 100, 1) . '%' : '' }}</span>
                                    <span class="v-num v-mix__count">{{ number_format($accounts['buyers']['new']) }}</span>
                                </li>
                                <li class="v-mix__row v-mix__row--plain">
                                    <span class="v-mix__name">Returning</span>
                                    <span class="v-num v-mix__share">{{ $accounts['buyers']['total'] > 0 ? number_format($accounts['buyers']['returning'] / $accounts['buyers']['total'] * 100, 1) . '%' : '' }}</span>
                                    <span class="v-num v-mix__count">{{ number_format($accounts['buyers']['returning']) }}</span>
                                </li>
                            </ul>

                            @if ($accounts['groups'] !== [])
                                <h3 class="v-label v-accounts__title v-accounts__title--second">Revenue by customer group</h3>
                                <ul class="v-mix">
                                    @foreach ($accounts['groups'] as $group)
                                        @php $m = $money((float) $group['revenue']); @endphp
                                        <li class="v-mix__row v-mix__row--plain">
                                            <span class="v-mix__name">{{ $group['label'] }}</span>
                                            <span class="v-num v-mix__share">{{ number_format($group['share'] * 100, 1) }}%</span>
                                            <span class="v-money"><span class="v-money__mark">{{ $m['mark'] }}</span><span class="v-money__amt">{{ $m['amount'] }}</span></span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
            </section>

            {{-- 5. The day's entries ------------------------------------ --}}
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
        @endif

        {{-- 6. Standing matters ----------------------------------------- --}}
        @if ($hasTraded || ($standingNeeded ?? false))
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
        @endif
    </article>
</x-admin.shell>

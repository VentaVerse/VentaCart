@php
    use Illuminate\Support\Str;

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

    // The band takes the two work figures a merchant acts on first, so the
    // list underneath carries only what the band did not.
    $work = $hasTraded ? ($workRemaining ?? []) : $workLines;
@endphp
<x-admin3.shell title="Today">
    <x-admin.page class="v-day v-settle" :title="$sheetDate->format('l, j F Y')" :standing="$standing">
        <!--
IMPECCABLE DIRECTION CONTRACT
THESIS: The admin's home is today's page in the shop's daybook, and it now leads with five figures a merchant can read from across the room. It refuses the widget-scatter dashboard, but it also refuses the flat sheet that gave every figure the same voice.
OWN-WORLD: The established Venta Blotter: Archivo on tinted paper, raised white sheets, hairline rules, tabular mono figures, azure slab, warm brass action. The six identity accents now also fill, on their own darker stop, because white text has to sit on them.
STORY: The merchant opens the admin in the morning, takes the day's shape from the band in one glance, and then reads down for the detail behind it.
FIRST VIEWPORT: The date, one plain sentence of standing, and the five filled blocks: what came in today, how much, the month so far, what is waiting to be packed and what is about to run out.
FORM: The ERP's dashboard layout, mirrored. Same regions in the same order; Venta's own data and Venta's own identity in them.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and dashboard-v5.md
-->

        {{-- 1. The head ------------------------------------------------
             On the paper rather than inside a sheet, so the page reads as
             a titled thing containing panels instead of one slab with a
             heading printed on it. This band is now x-admin.page's own
             render, so the other 96 views wear it too. --}}
        <x-slot:actions>
            <x-admin.button variant="primary" :href="route('admin.orders.create')">
                <x-admin.icon name="plus" class="w-4 h-4" /> New order
            </x-admin.button>
        </x-slot:actions>

        @if ($hasTraded)
            {{-- 2. The band ---------------------------------------------
                 Colour here says WHICH measure, never how it is doing, and
                 the written label is beside it in every case. Every block
                 keeps its fill whatever the figure: a zero block dropped to
                 white was tried in the ERP and removed, because "nothing
                 yet today" is the normal state of a morning and a row where
                 two of five have gone pale reads as unfinished rather than
                 as calm. --}}
            <div class="v-stats">
                @foreach ($stats as $stat)
                    @php $m = $stat['money'] ? $money((float) $stat['value']) : null; @endphp
                    <x-admin.figure
                        :label="$stat['label']"
                        :value="$m ? $m['amount'] : number_format((int) $stat['value'])"
                        :mark="$m ? $m['mark'] : null"
                        :note="$stat['note']"
                        :accent="$stat['accent']"
                        :icon="$stat['icon']"
                        :href="$stat['url']" />
                @endforeach
            </div>

            {{-- 3. How the money arrives ---------------------------------
                 The ERP's channel strip. A Venta store is one shop and has
                 no marketplace channels, so the honest part-to-whole
                 equivalent is the payment method: every order records one,
                 they genuinely differ in what they cost to operate, and a
                 store taking money one way collapses to a single chip
                 rather than to an empty row. --}}
            @if ($mix !== [])
                <div class="v-chans">
                    @foreach ($mix as $i => $method)
                        @php $m = $money((float) $method['revenue']); @endphp
                        <div class="v-chan">
                            {{-- Drawn as SVG rather than a span carrying an
                                 inline custom property: no CSS in Blade, and
                                 fill is a presentation attribute. --}}
                            <svg class="v-chan__ic" width="38" height="38" viewBox="0 0 38 38" aria-hidden="true" focusable="false">
                                <rect class="v-slice-{{ $i % 6 }}" width="38" height="38" rx="12"></rect>
                                <text class="v-chan__in" x="19" y="19" text-anchor="middle" dominant-baseline="central">{{ Str::upper(Str::substr($method['label'], 0, 1)) }}</text>
                            </svg>
                            <span class="v-chan__body">
                                <span class="v-chan__n">{{ $method['label'] }}</span>
                                <span class="v-chan__s">{{ number_format($method['orders']) }} {{ Str::plural('order', $method['orders']) }}</span>
                            </span>
                            <span class="v-chan__q">
                                <x-admin.money :mark="$m['mark']" :amount="$m['amount']" class="v-chan__qv" />
                                <span class="v-num v-chan__ql">{{ number_format($method['share'] * 100, 1) }}%</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 4. The split ---------------------------------------------
                 Two thirds to the work and its history, one third to the
                 standing counts beside it. --}}
            <div class="v-split">
                <div class="v-split__main v-settle v-settle--late">

                    {{-- 4a. Latest orders --------------------------------
                         Today's first, newest at the top; when today runs
                         short, the most recent earlier orders fill the
                         ledger to seven, so a quiet morning still reads as
                         a shop with trade in its book rather than as a
                         blank line. Today's rows print a time and earlier
                         rows print their date, which is what keeps the
                         panel honest about what it holds. --}}
                    <x-admin.panel
                        title="Latest orders"
                        id="v-sheet-entries"
                        :note="$entriesTodayCount > 0 ? $entriesTodayCount . ' today' : null"
                        :link="route('admin.orders.index')"
                        link-label="All orders"
                        flush>
                        @if ($entries->isEmpty())
                            <p class="v-panel__blank">Nothing on the books yet.</p>
                        @else
                            <x-admin.table bare class="v-table-wrap--gutter">
                                <x-slot:head>
                                    <x-admin.table.th>Placed</x-admin.table.th>
                                    <x-admin.table.th>Customer</x-admin.table.th>
                                    <x-admin.table.th class="v-col-bought">Bought</x-admin.table.th>
                                    <x-admin.table.th align="right">Amount</x-admin.table.th>
                                    <x-admin.table.th>Status</x-admin.table.th>
                                </x-slot:head>
                                @foreach ($entries as $entry)
                                    @php
                                        $items = $entry->products;
                                        $lead = $items->first();
                                        $extra = max($items->count() - 1, 0);
                                        $m = $money((float) $entry->total);
                                        $entryToday = $entry->created_at->gte($sheetDate);
                                        $when = $entryToday
                                            ? $entry->created_at->format('g:i a')
                                            : $entry->created_at->format($entry->created_at->year === $sheetDate->year ? 'j M' : 'j M Y');
                                    @endphp
                                    <x-admin.table.row href="{{ route('admin.orders.show', $entry) }}">
                                        <x-admin.table.cell mono class="whitespace-nowrap {{ $entryToday ? 'text-v-text' : 'text-v-muted' }}">{{ $when }}</x-admin.table.cell>
                                        <x-admin.table.cell class="text-v-text">
                                            {{-- The row opens on a click, but a pointer convenience is
                                                 not a control: this is the named link the keyboard and
                                                 the screen reader use to reach the order. --}}
                                            <a href="{{ route('admin.orders.show', $entry) }}" class="v-quietlink">{{ $entry->full_name }}</a>
                                        </x-admin.table.cell>
                                        <x-admin.table.cell class="v-col-bought text-v-secondary">
                                            @if ($lead)
                                                <span class="v-entry__item">{{ $lead->name }}@if ($lead->variant_label), {{ $lead->variant_label }}@endif</span>
                                                @if ($extra > 0)<span class="v-entry__more">and {{ $extra }} more</span>@endif
                                            @else
                                                <span class="v-entry__more">No line items</span>
                                            @endif
                                        </x-admin.table.cell>
                                        <x-admin.table.cell>
                                            <x-admin.money :mark="$m['mark']" :amount="$m['amount']" :zero="$m['zero']" />
                                        </x-admin.table.cell>
                                        <x-admin.table.cell>
                                            <x-admin.badge :tone="$statusTones[$entry->order_status_name] ?? 'neutral'">{{ $entry->order_status_name }}</x-admin.badge>
                                        </x-admin.table.cell>
                                    </x-admin.table.row>
                                @endforeach
                            </x-admin.table>
                            @if ($entriesTruncated)
                                <p class="v-panel__blank">The rest of today is on the orders screen.</p>
                            @endif
                        @endif
                    </x-admin.panel>

                    {{-- 4b. Sales ----------------------------------------
                         The plot answers for whichever period the picker
                         holds. The seven controls are real links carrying
                         ?range=, so the section works without JavaScript;
                         with it, day-sheet-plot.js intercepts, fetches the
                         series from the sales-series endpoint and rebuilds
                         the plot in place. The comparison table beneath is
                         the MONTH'S table and deliberately does not follow
                         the picker. --}}
                    <x-admin.panel title="Sales" id="v-sheet-pulse">
                        <x-slot:head>
                            <span class="v-panel__note" data-sales-period>{{ $plot['title'] }}</span>
                        </x-slot:head>

                        <x-admin.segment inline label="Sales period"
                                         data-range-picker
                                         data-range-endpoint="{{ route('admin.dashboard.sales-series') }}">
                            @foreach ($salesRanges as $rangeKey => $rangeLabel)
                                <x-admin.segment.item
                                    :href="route('admin.dashboard', ['range' => $rangeKey])"
                                    :active="$salesRange === $rangeKey"
                                    data-range="{{ $rangeKey }}">{{ $rangeLabel }}</x-admin.segment.item>
                            @endforeach
                        </x-admin.segment>

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
                                                        <x-admin.money :mark="$m['mark']" :amount="$m['amount']" :zero="$isZero" />
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

                        {{-- Today against a usual weekday, as one line.
                             A second table repeated the month table's
                             three row labels directly beneath them, and
                             two of its figures were already the band's
                             own notes; the average order is the figure
                             it alone carried, so the sentence keeps it
                             and the twin table is gone. --}}
                        <p class="v-usual">
                            {{ $usualLine }}
                            <x-admin.hint :label="'What a usual ' . $pulse['usual']['weekday'] . ' counts'">The middle figure of the last {{ $pulse['usual']['samples'] }} {{ $pulse['usual']['weekday'] }}s.</x-admin.hint>
                        </p>
                    </x-admin.panel>

                    {{-- 4c. The ring ------------------------------------
                         The ERP's "orders by channel", divided the way a
                         Venta store actually divides. Orders, not revenue:
                         the chips above already divide the money, and one
                         large order can hold a small share of the ring and
                         the largest figure in the column beside it. --}}
                    @if ($ring)
                        @php
                            $ringR = 70;
                            $ringC = 2 * M_PI * $ringR;
                            $ringAt = 0.0;
                        @endphp
                        <x-admin.panel
                            :title="'Orders by ' . $ring['by']"
                            id="v-sheet-ring"
                            :note="$accountsLabel . ($ring['scope'] ? ' · ' . $ring['scope'] : '')"
                            flush>
                            <div class="v-breakdown">
                                <div class="v-breakdown__ring">
                                    <svg class="v-ring" width="100%" height="180" viewBox="0 0 200 200" data-ring
                                         role="img" aria-label="Share of orders by {{ $ring['by'] }}. Every figure is in the table beside this ring.">
                                        @foreach ($ring['segments'] as $i => $seg)
                                            @php
                                                $dash = round($seg['share'] * $ringC, 2);
                                                $offset = round(-$ringAt * $ringC, 2);
                                                $ringAt += $seg['share'];
                                            @endphp
                                            <circle class="v-ring__seg v-stroke-{{ $i % 6 }}"
                                                    data-ring-seg
                                                    data-ring-label="{{ $seg['label'] }}"
                                                    data-ring-count="{{ number_format($seg['orders']) }}"
                                                    data-ring-share="{{ number_format($seg['share'] * 100, 1) }}% of orders"
                                                    cx="100" cy="100" r="{{ $ringR }}"
                                                    stroke-dasharray="{{ $dash }} {{ round($ringC, 2) }}"
                                                    stroke-dashoffset="{{ $offset }}"
                                                    transform="rotate(-90 100 100)">
                                                <title>{{ $seg['label'] }}: {{ number_format($seg['orders']) }} {{ Str::plural('order', $seg['orders']) }}</title>
                                            </circle>
                                        @endforeach
                                        {{-- The middle answers the pointer in space that was
                                             already there, so there is no panel to position and
                                             no native title to wait a second for. --}}
                                        <text class="v-ring__v v-num" x="100" y="94" text-anchor="middle" data-ring-v>{{ number_format($ring['total']) }}</text>
                                        <text class="v-ring__k" x="100" y="112" text-anchor="middle" data-ring-k>{{ Str::plural('order', $ring['total']) }}</text>
                                        <text class="v-ring__s" x="100" y="128" text-anchor="middle" data-ring-s></text>
                                    </svg>
                                </div>

                                <x-admin.table bare class="v-table-wrap--gutter v-breakdown__records">
                                    <x-slot:head>
                                        <x-admin.table.th>{{ Str::ucfirst($ring['by']) }}</x-admin.table.th>
                                        <x-admin.table.th align="right">Orders</x-admin.table.th>
                                        <x-admin.table.th align="right">Revenue</x-admin.table.th>
                                        <x-admin.table.th align="right">Share</x-admin.table.th>
                                    </x-slot:head>
                                    @foreach ($ring['segments'] as $i => $seg)
                                        @php $m = $money((float) $seg['revenue']); @endphp
                                        <tr>
                                            <x-admin.table.cell class="text-v-text">
                                                {{-- The swatch rides on the row it describes rather
                                                     than in a legend, so matching an arc to a revenue
                                                     figure is one glance and not two lists. --}}
                                                <svg class="v-tsw" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false">
                                                    <rect class="v-slice-{{ $i % 6 }}" width="10" height="10" rx="3"></rect>
                                                </svg>{{ $seg['label'] }}
                                            </x-admin.table.cell>
                                            <x-admin.table.cell><span class="v-num">{{ number_format($seg['orders']) }}</span></x-admin.table.cell>
                                            <x-admin.table.cell><x-admin.money :mark="$m['mark']" :amount="$m['amount']" /></x-admin.table.cell>
                                            <x-admin.table.cell><span class="v-num">{{ number_format($seg['share'] * 100, 1) }}%</span></x-admin.table.cell>
                                        </tr>
                                    @endforeach
                                </x-admin.table>
                            </div>
                        </x-admin.panel>
                    @endif

                    {{-- 4d. The running accounts ------------------------ --}}
                    <x-admin.panel title="Running accounts" id="v-sheet-accounts" :note="$accountsLabel" :flush="$accounts['orders'] === 0">
                        @if ($accounts['orders'] === 0)
                            <p class="v-panel__blank">Nothing sold in the {{ lcfirst($accountsLabel) }}.</p>
                        @else
                            <div class="v-accounts">
                                {{-- The bars draw each row against the list's own leader, so
                                     they only exist when there is a comparison to draw: a
                                     ranking of one would put a lone full-width strip under a
                                     lone row, which reads as a rule rather than as a share,
                                     so a single item prints its figure alone. --}}
                                <div class="v-accounts__col">
                                    <h3 class="v-label v-accounts__title">Carrying the money</h3>
                                    @php $revenueBars = count($accounts['byRevenue']) > 1; @endphp
                                    <ul class="v-rank">
                                        @foreach ($accounts['byRevenue'] as $item)
                                            @php $m = $money((float) $item['revenue']); @endphp
                                            <li class="v-rank__row {{ $revenueBars ? '' : 'v-rank__row--flat' }}">
                                                <span class="v-rank__name">{{ $item['name'] }}</span>
                                                <x-admin.money :mark="$m['mark']" :amount="$m['amount']" />
                                                @if ($revenueBars)
                                                    <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                                        <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                                        <rect class="v-rank__fill" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                                    </svg>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div class="v-accounts__col">
                                    <h3 class="v-label v-accounts__title">Moving off the shelf</h3>
                                    @php $unitBars = count($accounts['byUnits']) > 1; @endphp
                                    <ul class="v-rank">
                                        @foreach ($accounts['byUnits'] as $item)
                                            <li class="v-rank__row {{ $unitBars ? '' : 'v-rank__row--flat' }}">
                                                <span class="v-rank__name">{{ $item['name'] }}</span>
                                                <span class="v-num v-rank__units">{{ number_format($item['units']) }}</span>
                                                @if ($unitBars)
                                                    <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                                        <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                                        <rect class="v-rank__fill v-rank__fill--units" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                                    </svg>
                                                @endif
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
                                </div>
                            </div>
                        @endif
                    </x-admin.panel>
                </div>

                {{-- 4e. The rail ------------------------------------------
                     The counts that are true today but are not about today.
                     Panels, because they read as siblings of the ones
                     beside them rather than as loose text on the page. --}}
                <div class="v-split__rail v-settle v-settle--late">
                    @if ($work !== [])
                        <x-admin.panel title="Needs doing" id="v-sheet-work" flush>
                            <ul class="v-lines">
                                @foreach ($work as $line)
                                    <x-admin.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                                @endforeach
                            </ul>
                        </x-admin.panel>
                    @endif

                    <x-admin.panel
                        title="Inventory"
                        id="v-sheet-stock"
                        :note="number_format($inventory['products']) . ' ' . Str::plural('product', $inventory['products'])"
                        flush>
                        <dl class="v-facts">
                            <dt class="v-label">On the shelf, at sale price</dt>
                            <dd>
                                @php $m = $money((float) $inventory['atSale']); @endphp
                                <x-admin.money :mark="$m['mark']" :amount="$m['amount']" />
                            </dd>
                            <dt class="v-label">Out of stock</dt>
                            <dd><span class="v-num {{ $inventory['outOfStock'] === 0 ? 'v-facts__zero' : '' }}">{{ number_format($inventory['outOfStock']) }}</span></dd>
                        </dl>
                    </x-admin.panel>

                    @include('admin.partials.day-sheet-outstanding')

                    @include('admin.partials.day-sheet-automations')

                    @include('admin.partials.day-sheet-activity')
                </div>
            </div>
        @else
            {{-- 2. Before the first order -------------------------------
                 A merchant's day one is all zeros, and five saturated
                 blocks reading zero is a worse first impression than no
                 band at all. The store that has never traded keeps its own
                 composition. --}}
            <div class="v-split v-split--solo">
                <div class="v-split__main v-settle v-settle--late">
                    <x-admin.panel
                        title="To open"
                        id="v-sheet-open"
                        :link="route('admin.products.create')"
                        link-label="Add a product"
                        flush>
                        <ul class="v-lines">
                            @foreach ($openingLines as $line)
                                <x-admin.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                            @endforeach
                        </ul>
                    </x-admin.panel>

                    @if ($workNeeded ?? false)
                        <x-admin.panel title="Needs doing" id="v-sheet-work" flush>
                            <ul class="v-lines">
                                @foreach ($work as $line)
                                    <x-admin.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                                @endforeach
                            </ul>
                        </x-admin.panel>
                    @endif

                    @if ($standingNeeded ?? false)
                        @include('admin.partials.day-sheet-outstanding')
                    @endif

                    {{-- A store that has never traded has no feed to show,
                         but a stalled automation is a fact on day one too. --}}
                    @if ($automationHealth['unhealthy']->isNotEmpty())
                        @include('admin.partials.day-sheet-automations')
                    @endif
                </div>
            </div>
        @endif
    </x-admin.page>
</x-admin3.shell>

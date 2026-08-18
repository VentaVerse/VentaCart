@php
    use Illuminate\Support\Str;

    $statusTones = [
        'Pending' => 'warn', 'Unpaid' => 'warn', 'Processing' => 'info',
        'Payment Received' => 'info', 'Shipped' => 'info', 'Order Being Shipped' => 'info',
        'Delivered' => 'good', 'Complete' => 'good',
        'Canceled' => 'bad', 'Failed' => 'bad',
        'Refunded' => 'quiet', 'Expired' => 'quiet',
    ];

    $money = function (float $amount) use ($currency) {
        return ['mark' => $currency, 'amount' => number_format($amount, 2), 'zero' => $amount == 0.0];
    };

    // The band takes the two work figures a merchant acts on first, so the
    // list underneath carries only what the band did not.
    $work = $hasTraded ? ($workRemaining ?? []) : $workLines;
@endphp
<x-admin3.shell title="Today">
    <div class="v3-day v3-settle">
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
             heading printed on it. The head band is the day sheet's own
             render, so the other 96 views wear it too. --}}
        <header class="v3-day__head">
            <div class="v3-day__headtext">
                <h1 class="v3-day__title">{{ $sheetDate->format('l, j F Y') }}</h1>
                @if ($standing)<p class="v3-day__standing">{{ $standing }}</p>@endif
            </div>
            <div class="v3-day__actions">
                <a href="{{ route('admin.orders.create') }}" class="v3-btn v3-btn--primary">New order</a>
            </div>
        </header>

        @if ($hasTraded)
            {{-- 2. The band ---------------------------------------------
                 Colour here says WHICH measure, never how it is doing, and
                 the written label is beside it in every case. Every block
                 keeps its fill whatever the figure: a zero block dropped to
                 white was tried in the ERP and removed, because "nothing
                 yet today" is the normal state of a morning and a row where
                 two of five have gone pale reads as unfinished rather than
                 as calm. --}}
            <div class="v3-statband">
                @foreach ($stats as $stat)
                    @php $m = $stat['money'] ? $money((float) $stat['value']) : null; @endphp
                    <x-admin3.figure
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
                <div class="v3-chans">
                    @foreach ($mix as $i => $method)
                        @php $m = $money((float) $method['revenue']); @endphp
                        <div class="v3-chan">
                            {{-- Drawn as SVG rather than a span carrying an
                                 inline custom property: no CSS in Blade, and
                                 fill is a presentation attribute. --}}
                            <svg class="v3-chan__ic" width="38" height="38" viewBox="0 0 38 38" aria-hidden="true" focusable="false">
                                <rect class="v3-slice-{{ $i % 6 }}" width="38" height="38" rx="12"></rect>
                                <text class="v3-chan__in" x="19" y="19" text-anchor="middle" dominant-baseline="central">{{ Str::upper(Str::substr($method['label'], 0, 1)) }}</text>
                            </svg>
                            <span class="v3-chan__body">
                                <span class="v3-chan__n">{{ $method['label'] }}</span>
                                <span class="v3-chan__s">{{ number_format($method['orders']) }} {{ Str::plural('order', $method['orders']) }}</span>
                            </span>
                            <span class="v3-chan__q">
                                <x-admin3.money :mark="$m['mark']" :amount="$m['amount']" class="v3-chan__qv" />
                                <span class="v3-num v3-chan__ql">{{ number_format($method['share'] * 100, 1) }}%</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 4. The split ---------------------------------------------
                 Two thirds to the work and its history, one third to the
                 standing counts beside it. --}}
            <div class="v3-daysplit">
                <div class="v3-daysplit__main v3-settle v3-settle">

                    {{-- 4a. Latest orders --------------------------------
                         Today's first, newest at the top; when today runs
                         short, the most recent earlier orders fill the
                         ledger to seven, so a quiet morning still reads as
                         a shop with trade in its book rather than as a
                         blank line. Today's rows print a time and earlier
                         rows print their date, which is what keeps the
                         panel honest about what it holds. --}}
                    <x-admin3.panel title="Latest orders" id="v-sheet-entries" flush>
                        <x-slot:acts>
                            @if ($entriesTodayCount > 0)<span class="v3-cell-quiet">{{ $entriesTodayCount }} today</span>@endif
                            <a href="{{ route('admin.orders.index') }}" class="v3-link">All orders</a>
                        </x-slot:acts>
                        @if ($entries->isEmpty())
                            <p class="v3-blankline">Nothing on the books yet.</p>
                        @else
                            <div class="v3-tablewrap">
                                <table class="v3-table">
                                    <thead>
                                        <tr>
                                            <th>Placed</th>
                                            <th>Customer</th>
                                            <th class="v3-col-bought">Bought</th>
                                            <th class="v3-cell-num">Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
                                    <tr>
                                        <td class="v3-mono {{ $entryToday ? '' : 'v3-cell-quiet' }}">{{ $when }}</td>
                                        <td>
                                            {{-- The row opens on a click, but a pointer convenience is
                                                 not a control: this is the named link the keyboard and
                                                 the screen reader use to reach the order. --}}
                                            <a href="{{ route('admin.orders.show', $entry) }}" class="v3-quietlink">{{ $entry->full_name }}</a>
                                        </td>
                                        <td class="v3-col-bought v3-cell-quiet">
                                            @if ($lead)
                                                <span class="v3-entry__item">{{ $lead->name }}@if ($lead->variant_label), {{ $lead->variant_label }}@endif</span>
                                                @if ($extra > 0)<span class="v3-entry__more">and {{ $extra }} more</span>@endif
                                            @else
                                                <span class="v3-entry__more">No line items</span>
                                            @endif
                                        </td>
                                        <td class="v3-cell-num">
                                            <x-admin3.money :mark="$m['mark']" :amount="$m['amount']" :zero="$m['zero']" />
                                        </td>
                                        <td>
                                            <span class="v3-badge v3-badge--{{ $statusTones[$entry->order_status_name] ?? 'quiet' }}">{{ $entry->order_status_name }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($entriesTruncated)
                                <p class="v3-blankline">The rest of today is on the orders screen.</p>
                            @endif
                        @endif
                    </x-admin3.panel>

                    {{-- 4b. Sales ----------------------------------------
                         The plot answers for whichever period the picker
                         holds. The seven controls are real links carrying
                         ?range=, so the section works without JavaScript;
                         with it, day-sheet-plot.js intercepts, fetches the
                         series from the sales-series endpoint and rebuilds
                         the plot in place. The comparison table beneath is
                         the MONTH'S table and deliberately does not follow
                         the picker. --}}
                    <x-admin3.panel title="Sales" id="v-sheet-pulse">
                        <x-slot:acts>
                            <span class="v3-cell-quiet" data-sales-period>{{ $plot['title'] }}</span>
                        </x-slot:acts>

                        <x-admin3.segment inline label="Sales period"
                                         data-range-picker
                                         data-range-endpoint="{{ route('admin.dashboard.sales-series') }}">
                            @foreach ($salesRanges as $rangeKey => $rangeLabel)
                                <x-admin3.segment-item
                                    :href="route('admin.dashboard', ['range' => $rangeKey])"
                                    :active="$salesRange === $rangeKey"
                                    data-range="{{ $rangeKey }}">{{ $rangeLabel }}</x-admin3.segment-item>
                            @endforeach
                        </x-admin3.segment>

                        @include('admin.partials.pulse-plot', ['plot' => $plot, 'currency' => $currency])

                        @foreach ($bands as $band)
                            <div class="v3-bandblock">
                                <h3 class="v3-flabel v3-bandblock__title">
                                    {{ $band['title'] }}
                                    @if ($band['hint'])<x-admin3.help :text="$band['hint']" />@endif
                                </h3>

                                <div class="v3-band v3-band--pulse">
                                    <div class="v3-fig v3-fig--head" aria-hidden="true">
                                        <span class="v3-fig__name"></span>
                                        @foreach ($band['columns'] as $column)
                                            <span class="v3-fig__cell v3-flabel">{{ $column['label'] }}</span>
                                        @endforeach
                                        <span class="v3-fig__cell v3-flabel">Change</span>
                                    </div>

                                    @foreach ($band['rows'] as $row)
                                        <div class="v3-fig">
                                            <span class="v3-fig__name">
                                                {{ $row['label'] }}
                                                @if ($row['hint'])<x-admin3.help :text="$row['hint']" />@endif
                                            </span>

                                            @foreach ($band['columns'] as $column)
                                                @php
                                                    $blank = $column['key'] === 'ref' && $band['referenceMissing'];
                                                    $raw = $row['values'][$column['key']] ?? 0;
                                                    $m = $row['money'] ? $money((float) $raw) : null;
                                                    $isZero = $row['money'] ? $m['zero'] : ((int) $raw === 0);
                                                @endphp
                                                <span class="v3-fig__cell {{ $column['lead'] ? 'v3-fig__cell--now' : '' }}">
                                                    <span class="v3-fig__when">{{ $column['label'] }}</span>
                                                    @if ($blank)
                                                        <span class="v3-fig__plain v3-fig__plain--zero">No record</span>
                                                    @elseif ($row['money'])
                                                        <x-admin3.money :mark="$m['mark']" :amount="$m['amount']" :zero="$isZero" />
                                                    @else
                                                        <span class="v3-num v3-fig__plain {{ $isZero ? 'v3-fig__plain--zero' : '' }}">{{ number_format((int) $raw) }}</span>
                                                    @endif
                                                </span>
                                            @endforeach

                                            <span class="v3-fig__cell v3-fig__cell--change">
                                                <span class="v3-fig__when">Change</span>
                                                @if ($band['referenceMissing'])
                                                    <span class="v3-change v3-change--level">Nothing to compare</span>
                                                @else
                                                    <span class="v3-change v3-change--{{ $row['change']['direction'] }}">
                                                        @if ($row['change']['direction'] !== 'level')
                                                            <x-admin3.icon :name="$row['change']['direction'] === 'ahead' ? 'arrow-up-right' : 'arrow-down-right'" class="v3-change__go" />
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
                        <p class="v3-usual">
                            {{ $usualLine }}
                            <x-admin3.help :text="'The middle figure of the last ' . $pulse['usual']['samples'] . ' ' . $pulse['usual']['weekday'] . 's.'" />
                        </p>
                    </x-admin3.panel>

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
                        <x-admin3.panel :title="'Orders by ' . $ring['by']" id="v-sheet-ring" flush>
                            <x-slot:acts>
                                <span class="v3-cell-quiet">{{ $accountsLabel . ($ring['scope'] ? ' · ' . $ring['scope'] : '') }}</span>
                            </x-slot:acts>
                            <div class="v3-breakdown">
                                <div class="v3-breakdown__ring">
                                    <svg class="v3-ring" width="100%" height="180" viewBox="0 0 200 200" data-ring
                                         role="img" aria-label="Share of orders by {{ $ring['by'] }}. Every figure is in the table beside this ring.">
                                        @foreach ($ring['segments'] as $i => $seg)
                                            @php
                                                $dash = round($seg['share'] * $ringC, 2);
                                                $offset = round(-$ringAt * $ringC, 2);
                                                $ringAt += $seg['share'];
                                            @endphp
                                            <circle class="v3-ring__seg v3-stroke-{{ $i % 6 }}"
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
                                        <text class="v3-ring__v v3-num" x="100" y="94" text-anchor="middle" data-ring-v>{{ number_format($ring['total']) }}</text>
                                        <text class="v3-ring__k" x="100" y="112" text-anchor="middle" data-ring-k>{{ Str::plural('order', $ring['total']) }}</text>
                                        <text class="v3-ring__s" x="100" y="128" text-anchor="middle" data-ring-s></text>
                                    </svg>
                                </div>

                                <div class="v3-tablewrap v3-breakdown__records">
                                    <table class="v3-table">
                                        <thead>
                                            <tr>
                                                <th>{{ Str::ucfirst($ring['by']) }}</th>
                                                <th class="v3-cell-num">Orders</th>
                                                <th class="v3-cell-num">Revenue</th>
                                                <th class="v3-cell-num">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                    @foreach ($ring['segments'] as $i => $seg)
                                        @php $m = $money((float) $seg['revenue']); @endphp
                                        <tr>
                                            <td>
                                                {{-- The swatch rides on the row it describes rather
                                                     than in a legend, so matching an arc to a revenue
                                                     figure is one glance and not two lists. --}}
                                                <svg class="v3-tsw" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false">
                                                    <rect class="v3-slice-{{ $i % 6 }}" width="10" height="10" rx="3"></rect>
                                                </svg>{{ $seg['label'] }}
                                            </td>
                                            <td class="v3-cell-num"><span class="v3-num">{{ number_format($seg['orders']) }}</span></td>
                                            <td class="v3-cell-num"><x-admin3.money :mark="$m['mark']" :amount="$m['amount']" /></td>
                                            <td class="v3-cell-num"><span class="v3-num">{{ number_format($seg['share'] * 100, 1) }}%</span></td>
                                        </tr>
                                    @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </x-admin3.panel>
                    @endif

                    {{-- 4d. The running accounts ------------------------ --}}
                    <x-admin3.panel title="Running accounts" id="v-sheet-accounts" :flush="$accounts['orders'] === 0">
                        <x-slot:acts><span class="v3-cell-quiet">{{ $accountsLabel }}</span></x-slot:acts>
                        @if ($accounts['orders'] === 0)
                            <p class="v3-blankline">Nothing sold in the {{ lcfirst($accountsLabel) }}.</p>
                        @else
                            <div class="v3-accounts">
                                {{-- The bars draw each row against the list's own leader, so
                                     they only exist when there is a comparison to draw: a
                                     ranking of one would put a lone full-width strip under a
                                     lone row, which reads as a rule rather than as a share,
                                     so a single item prints its figure alone. --}}
                                <div class="v3-accounts__col">
                                    <h3 class="v3-flabel v3-accounts__title">Carrying the money</h3>
                                    @php $revenueBars = count($accounts['byRevenue']) > 1; @endphp
                                    <ul class="v3-rank">
                                        @foreach ($accounts['byRevenue'] as $item)
                                            @php $m = $money((float) $item['revenue']); @endphp
                                            <li class="v3-rank__row {{ $revenueBars ? '' : 'v3-rank__row--flat' }}">
                                                <span class="v3-rank__name">{{ $item['name'] }}</span>
                                                <x-admin3.money :mark="$m['mark']" :amount="$m['amount']" />
                                                @if ($revenueBars)
                                                    <svg class="v3-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                                        <rect class="v3-rank__track" x="0" y="0" width="100" height="4"></rect>
                                                        <rect class="v3-rank__fill" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                                    </svg>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div class="v3-accounts__col">
                                    <h3 class="v3-flabel v3-accounts__title">Moving off the shelf</h3>
                                    @php $unitBars = count($accounts['byUnits']) > 1; @endphp
                                    <ul class="v3-rank">
                                        @foreach ($accounts['byUnits'] as $item)
                                            <li class="v3-rank__row {{ $unitBars ? '' : 'v3-rank__row--flat' }}">
                                                <span class="v3-rank__name">{{ $item['name'] }}</span>
                                                <span class="v3-num v3-rank__units">{{ number_format($item['units']) }}</span>
                                                @if ($unitBars)
                                                    <svg class="v3-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                                        <rect class="v3-rank__track" x="0" y="0" width="100" height="4"></rect>
                                                        <rect class="v3-rank__fill v3-rank__fill--units" x="0" y="0" height="4" width="{{ round($item['weight'] * 100, 2) }}"></rect>
                                                    </svg>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div class="v3-accounts__col">
                                    <h3 class="v3-flabel v3-accounts__title">
                                        Buyers
                                        <x-admin3.help text="A buyer is one email address. Returning means that address had ordered before this period." />
                                    </h3>
                                    <ul class="v3-mix">
                                        <li class="v3-mix__row v3-mix__row--plain">
                                            <span class="v3-mix__name">New</span>
                                            <span class="v3-num v3-mix__share">{{ $accounts['buyers']['total'] > 0 ? number_format($accounts['buyers']['new'] / $accounts['buyers']['total'] * 100, 1) . '%' : '' }}</span>
                                            <span class="v3-num v3-mix__count">{{ number_format($accounts['buyers']['new']) }}</span>
                                        </li>
                                        <li class="v3-mix__row v3-mix__row--plain">
                                            <span class="v3-mix__name">Returning</span>
                                            <span class="v3-num v3-mix__share">{{ $accounts['buyers']['total'] > 0 ? number_format($accounts['buyers']['returning'] / $accounts['buyers']['total'] * 100, 1) . '%' : '' }}</span>
                                            <span class="v3-num v3-mix__count">{{ number_format($accounts['buyers']['returning']) }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </x-admin3.panel>
                </div>

                {{-- 4e. The rail ------------------------------------------
                     The counts that are true today but are not about today.
                     Panels, because they read as siblings of the ones
                     beside them rather than as loose text on the page. --}}
                <div class="v3-daysplit__rail v3-settle v3-settle">
                    @if ($work !== [])
                        <x-admin3.panel title="Needs doing" id="v-sheet-work" flush>
                            <ul class="v3-lines">
                                @foreach ($work as $line)
                                    <x-admin3.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                                @endforeach
                            </ul>
                        </x-admin3.panel>
                    @endif

                    <x-admin3.panel title="Inventory" id="v-sheet-stock" flush>
                        <x-slot:acts>
                            <span class="v3-cell-quiet">{{ number_format($inventory['products']) }} {{ Str::plural('product', $inventory['products']) }}</span>
                        </x-slot:acts>
                        <dl class="v3-facts">
                            <dt class="v3-flabel">On the shelf, at sale price</dt>
                            <dd>
                                @php $m = $money((float) $inventory['atSale']); @endphp
                                <x-admin3.money :mark="$m['mark']" :amount="$m['amount']" />
                            </dd>
                            <dt class="v3-flabel">Out of stock</dt>
                            <dd><span class="v3-num {{ $inventory['outOfStock'] === 0 ? 'v3-facts__zero' : '' }}">{{ number_format($inventory['outOfStock']) }}</span></dd>
                        </dl>
                    </x-admin3.panel>

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
            <div class="v3-daysplit v3-daysplit--solo">
                <div class="v3-daysplit__main v3-settle v3-settle">
                    <x-admin3.panel title="To open" id="v-sheet-open" flush>
                        <x-slot:acts><a href="{{ route('admin.products.create') }}" class="v3-link">Add a product</a></x-slot:acts>
                        <ul class="v3-lines">
                            @foreach ($openingLines as $line)
                                <x-admin3.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                            @endforeach
                        </ul>
                    </x-admin3.panel>

                    @if ($workNeeded ?? false)
                        <x-admin3.panel title="Needs doing" id="v-sheet-work" flush>
                            <ul class="v3-lines">
                                @foreach ($work as $line)
                                    <x-admin3.line :href="$line['url']" :label="$line['label']" :count="$line['count']" />
                                @endforeach
                            </ul>
                        </x-admin3.panel>
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
    </div>
</x-admin3.shell>

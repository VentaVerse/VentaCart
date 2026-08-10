{{-- The enforcement surface.

     Every promoted primitive, in its real states, on one page. If a
     shape is not here it is not part of the system, and if a state is
     not here nobody has checked it. Open this page in both themes and
     at 390 before shipping anything that touches the shared layer.

     The data is deliberately hostile where hostile data exists: the
     longest real product title and the longest real customer name in
     the NGD catalogue, a nine digit peso total, tables at zero, one
     and many rows.

     The direction contract lives on the shell; this page is the
     inventory, not a composition, so it does not carry one. --}}
@php
    // The longest real names in the migrated NGD catalogue and customer
    // book. Every truncation, clamp and ellipsis in the system has to
    // survive these, so they are the fixture rather than lorem.
    $longProduct = 'Ernie Ball 2221 Regular Slinky Nickel Wound Electric Guitar Strings 10-46 (3 Pack Bundle with Free Winder)';
    $longCustomer = 'Ma. Cristina Bernadette Villanueva-Santos de los Reyes';
    $money = ['mark' => '₱', 'amount' => '148,392,207.55'];
@endphp
<x-admin.shell title="UI Kit">
    <div class="v-day">
        <header class="v-day__head">
            <div class="v-day__headtext">
                <h1 class="v-day__date">UI Kit</h1>
                <p class="v-day__standing">Every promoted primitive, in every state the admin can put it in.</p>
            </div>
            <x-admin.button variant="primary">
                <x-admin.icon name="plus" class="w-4 h-4" /> Primary action
            </x-admin.button>
        </header>

        {{-- ------------------------------------------------------------
             Figures: the filled measure block, all six identity accents.
             Colour is identity, never state, so the row shows all six
             regardless of whether the news in each is good.
             ------------------------------------------------------------ --}}
        <div class="v-stats">
            <x-admin.figure label="Orders today" value="18" note="6 more than yesterday" accent="cyan" icon="orders" href="#" />
            <x-admin.figure label="Taken today" :value="$money['amount']" :mark="$money['mark']" note="Nine digits, wrapped" accent="violet" icon="revenue" href="#" />
            <x-admin.figure label="This month" value="1,284" note="Ahead of last month" accent="green" icon="chart" />
            <x-admin.figure label="Waiting to pack" value="0" note="Nothing waiting" accent="amber" icon="fulfillment" href="#" />
            <x-admin.figure label="Running out" value="27,709" note="Below reorder point" accent="rose" icon="products" href="#" />
        </div>

        <div class="v-split">
            <div class="v-split__main">

                {{-- Buttons: every variant, every size, and the three
                     states a control can be in besides resting. --}}
                <x-admin.panel title="Buttons" id="kit-buttons">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-admin.button variant="primary">Primary</x-admin.button>
                        <x-admin.button variant="secondary">Secondary</x-admin.button>
                        <x-admin.button variant="danger">Danger</x-admin.button>
                        <x-admin.button variant="ghost">Ghost</x-admin.button>
                        <x-admin.button variant="primary" size="sm">Small</x-admin.button>
                        <x-admin.button href="#">Link</x-admin.button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <x-admin.button variant="primary" data-loading="true">Loading</x-admin.button>
                        <x-admin.button variant="primary" disabled>Disabled</x-admin.button>
                        <x-admin.button variant="secondary" disabled>Disabled</x-admin.button>
                        <x-admin.button variant="danger" disabled>Disabled</x-admin.button>
                    </div>
                </x-admin.panel>

                {{-- Badges: the square mark and the word. The word is
                     always printed, so removing the colour removes
                     nothing. --}}
                <x-admin.panel title="Badges" id="kit-badges">
                    <div class="flex flex-wrap items-center gap-4">
                        <x-admin.badge tone="neutral">Draft</x-admin.badge>
                        <x-admin.badge tone="success">Complete</x-admin.badge>
                        <x-admin.badge tone="warning">Processing</x-admin.badge>
                        <x-admin.badge tone="danger">Failed</x-admin.badge>
                        <x-admin.badge tone="info">Shipped</x-admin.badge>
                        <x-admin.badge tone="accent">Scheduled</x-admin.badge>
                        <x-admin.badge tone="neutral" :dot="false">No mark</x-admin.badge>
                    </div>
                </x-admin.panel>

                {{-- Segment bar: the trough. Three shapes, all one
                     control. --}}
                <x-admin.panel title="Segment bar" id="kit-segment">
                    <x-admin.segment inline label="Range, inline">
                        <x-admin.segment.item href="#" active>Today</x-admin.segment.item>
                        <x-admin.segment.item href="#">7 days</x-admin.segment.item>
                        <x-admin.segment.item href="#">30 days</x-admin.segment.item>
                        <x-admin.segment.item href="#">This year</x-admin.segment.item>
                        <x-admin.segment.item href="#">All time</x-admin.segment.item>
                    </x-admin.segment>

                    <x-admin.segment label="Phase, with counts and an aside">
                        <x-admin.segment.item href="#" active count="27,709">All</x-admin.segment.item>
                        <x-admin.segment.item href="#" count="0">Unpaid</x-admin.segment.item>
                        <x-admin.segment.item href="#" count="14">To ship</x-admin.segment.item>
                        <x-admin.segment.item href="#" count="1">Shipping</x-admin.segment.item>
                        <x-admin.segment.item href="#" count="248">Delivered</x-admin.segment.item>
                        <x-slot:aside>
                            <a class="v-segbar__aside" href="#">Returns <x-admin.icon name="chevron-right" class="w-3 h-3" /></a>
                        </x-slot:aside>
                    </x-admin.segment>

                    <x-admin.segment sub label="Second tier">
                        <x-admin.segment.item href="#" active count="9">To pack</x-admin.segment.item>
                        <x-admin.segment.item href="#" count="5">To handover</x-admin.segment.item>
                    </x-admin.segment>

                    {{-- The Alpine tab set wears the same trough, so a
                         swap that happens client-side and one that
                         happens over the wire are the same control. --}}
                    <x-admin.tabs default="one" param="kit">
                        <x-slot:nav>
                            <x-admin.tabs.tab name="one">First panel</x-admin.tabs.tab>
                            <x-admin.tabs.tab name="two">Second panel</x-admin.tabs.tab>
                        </x-slot:nav>
                        <x-admin.tabs.panel name="one"><p class="text-[13px] text-v-secondary">First panel body.</p></x-admin.tabs.panel>
                        <x-admin.tabs.panel name="two"><p class="text-[13px] text-v-secondary">Second panel body.</p></x-admin.tabs.panel>
                    </x-admin.tabs>
                </x-admin.panel>

                {{-- Toolbar and filters: the row above a list. --}}
                <x-admin.panel title="Toolbar and filters" id="kit-toolbar">
                    <x-admin.toolbar>
                        <div class="v-toolbar__actions">
                            <x-admin.button variant="primary">Refresh</x-admin.button>
                            <x-admin.button variant="secondary">Export</x-admin.button>
                        </div>
                    </x-admin.toolbar>

                    <x-admin.filters>
                        <x-admin.input type="search" name="kit_q" placeholder="Order #, tracking, customer&hellip;" aria-label="Search" class="v-filters__search" />
                        <x-admin.select name="kit_per" aria-label="Per page" class="v-filters__select">
                            <option>10 per page</option>
                            <option>25 per page</option>
                        </x-admin.select>
                        <x-admin.button variant="secondary">Search</x-admin.button>
                        <a class="v-filters__reset" href="#">Reset</a>
                    </x-admin.filters>

                    <div class="v-tally">
                        <span class="v-tally__label">Parcels waiting for handover</span>
                        <span class="v-tally__item"><span class="v-tally__count">12</span> QuadX</span>
                        <span class="v-tally__item"><span class="v-tally__count">3</span> SPX</span>
                    </div>
                </x-admin.panel>

                {{-- Tables: many rows, one row, no rows, and the two
                     densities. Every column that can hold an unbounded
                     string holds one here. --}}
                <x-admin.panel title="Table, many rows" id="kit-table-many" flush>
                    <x-admin.table bare class="v-table-wrap--gutter">
                        <x-slot:head>
                            <x-admin.table.th sortable field="customer">Customer</x-admin.table.th>
                            <x-admin.table.th>Bought</x-admin.table.th>
                            <x-admin.table.th>Status</x-admin.table.th>
                            <x-admin.table.th align="right">Total</x-admin.table.th>
                        </x-slot:head>
                        <x-admin.table.row href="#">
                            <x-admin.table.cell class="text-v-text"><a href="#" class="v-quietlink">{{ $longCustomer }}</a></x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__item">{{ $longProduct }}</span><span class="v-entry__more">and 4 more</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="warning">Processing</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money :mark="$money['mark']" :amount="$money['amount']" /></x-admin.table.cell>
                        </x-admin.table.row>
                        <x-admin.table.row href="#">
                            <x-admin.table.cell class="text-v-text"><a href="#" class="v-quietlink">Ana Reyes</a></x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__item">Dunlop Tortex .88mm</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="info">Shipped</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money mark="₱" amount="899.00" /></x-admin.table.cell>
                        </x-admin.table.row>
                        <x-admin.table.row href="#">
                            <x-admin.table.cell class="text-v-text"><a href="#" class="v-quietlink">Miguel Santos</a></x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__more">No line items</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="danger">Failed</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money mark="₱" amount="0.00" zero /></x-admin.table.cell>
                        </x-admin.table.row>
                    </x-admin.table>
                </x-admin.panel>

                <x-admin.panel title="Table, one row" id="kit-table-one" flush>
                    <x-admin.table bare class="v-table-wrap--gutter">
                        <x-slot:head>
                            <x-admin.table.th>Customer</x-admin.table.th>
                            <x-admin.table.th align="right">Total</x-admin.table.th>
                        </x-slot:head>
                        <x-admin.table.row>
                            <x-admin.table.cell class="text-v-text">Ana Reyes</x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money mark="₱" amount="899.00" /></x-admin.table.cell>
                        </x-admin.table.row>
                    </x-admin.table>
                </x-admin.panel>

                <x-admin.panel title="Table, no rows" id="kit-table-none" flush>
                    <x-admin.table bare>
                        <x-slot:head>
                            <x-admin.table.th>Customer</x-admin.table.th>
                            <x-admin.table.th align="right">Total</x-admin.table.th>
                        </x-slot:head>
                    </x-admin.table>
                    <x-admin.empty-state title="No orders yet" description="Orders placed in your storefront appear here the moment they are paid.">
                        <x-admin.button variant="primary"><x-admin.icon name="plus" class="w-3.5 h-3.5" /> New order</x-admin.button>
                    </x-admin.empty-state>
                </x-admin.panel>

                {{-- The two-tier table with its line items, on its own
                     sheet: the packing density, at the size a packer
                     actually verifies against. --}}
                <x-admin.table tiered roomy>
                    <x-slot:head>
                        <th>Products</th>
                        <th class="v-col-qty">Qty</th>
                        <th class="v-col-amount">Total</th>
                        <th class="v-col-act"><span class="v-sr">Actions</span></th>
                    </x-slot:head>
                    <tr class="v-tier">
                        <td colspan="4">
                            <div class="v-tier__row">
                                <div class="v-tier__left">
                                    <span class="v-tier__label">Customer</span>
                                    <span class="v-tier__value">{{ $longCustomer }}</span>
                                    <span class="v-tier__label">Order</span>
                                    <a class="v-ref" href="#">#29851</a>
                                    <span class="v-tier__muted">(2 items)</span>
                                </div>
                                <div class="v-tier__right">
                                    <span class="v-tier__label">Created</span>
                                    <span class="v-tier__value v-tier__value--mono">Aug 10, 2026 9:14am</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr class="v-subrow">
                        <td><x-admin.item :name="$longProduct" sku="EB-2221-3PK" variant="10-46, 3 pack" /></td>
                        <td class="v-col-qty"><span class="v-qty">&times;3</span></td>
                        <td class="v-col-amount" rowspan="2">
                            <span class="v-amount">₱2,450.00</span>
                            <span class="v-amount__sub">COD</span>
                        </td>
                        <td class="v-col-act" rowspan="2">
                            <div class="v-rowactions">
                                <x-admin.button size="sm" variant="primary">Book</x-admin.button>
                                <x-admin.button size="sm" variant="secondary">Print waybill</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Cancel</x-admin.button>
                            </div>
                        </td>
                    </tr>
                    <tr class="v-subrow v-subrow--last">
                        <td><x-admin.item name="Unnamed product" /></td>
                        <td class="v-col-qty"><span class="v-none">-</span></td>
                    </tr>
                </x-admin.table>

                {{-- Forms: label, control, help, and the error state. --}}
                <x-admin.panel title="Forms" id="kit-forms">
                    <form data-v-form class="max-w-xl space-y-5" onsubmit="return false;">
                        <x-admin.field name="demo_name" label="Store name" help="Shown in the storefront header.">
                            <x-admin.input name="demo_name" value="New Gear Day" />
                        </x-admin.field>
                        <x-admin.field name="demo_country" label="Country">
                            <x-admin.select name="demo_country">
                                <option>Philippines</option>
                                <option>Singapore</option>
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.field name="demo_notes" label="Notes">
                            <x-admin.textarea name="demo_notes" rows="3" />
                        </x-admin.field>
                        <x-admin.field name="demo_disabled" label="Disabled">
                            <x-admin.input name="demo_disabled" value="Not editable" disabled />
                        </x-admin.field>
                        <div class="flex items-center gap-6">
                            <x-admin.toggle name="demo_active" :checked="true" />
                            <x-admin.checkbox name="demo_agree" label="Send review emails" :checked="true" />
                        </div>
                        <x-admin.radio-group name="demo_mode" :options="['sale' => 'Sale price (strikethrough)', 'override' => 'Override (clean replacement)']" value="sale" />
                        <x-admin.form-actions>
                            <x-admin.button variant="secondary">Cancel</x-admin.button>
                            <x-admin.button variant="primary" type="submit">Save changes</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.panel>

                {{-- Selectable cards: one that can be picked, one that
                     cannot. --}}
                <x-admin.panel title="Selectable cards" id="kit-choice">
                    <x-admin.choice>
                        <x-admin.choice.card name="QuadX" mode="Staging" fee="PHP 145.00" />
                        <x-admin.choice.card name="SPX Express" />
                        <x-admin.choice.card name="LBC Express" :available="false" state="Not configured">
                            Missing App ID / App Secret. Configure in <a href="#" class="v-choice__offlink">Settings &rarr; Courier Fulfillment &rarr; LBC Express</a>.
                        </x-admin.choice.card>
                    </x-admin.choice>
                </x-admin.panel>

                {{-- Feedback: what the system says back. --}}
                <x-admin.panel title="Feedback" id="kit-feedback">
                    <div class="flex flex-wrap gap-3" x-data>
                        <x-admin.button variant="secondary" @click="$store.toasts.push('Saved successfully.', 'success')">Success toast</x-admin.button>
                        <x-admin.button variant="secondary" @click="$store.toasts.push('Something went wrong.', 'danger')">Error toast</x-admin.button>
                        <x-admin.button variant="secondary" @click="$dispatch('open-modal', 'demo-modal')">Open modal</x-admin.button>
                        <x-admin.button variant="danger" @click="$dispatch('open-modal', 'demo-confirm')">Delete something</x-admin.button>
                        <span class="inline-flex items-center gap-1.5 text-[13px] text-v-secondary">
                            Hint
                            <x-admin.hint label="What a hint is for">The one sentence a control cannot say in its own name. Everything else belongs in the control's label.</x-admin.hint>
                        </span>
                    </div>
                    <x-admin.modal name="demo-modal" title="Demo modal">
                        <p class="text-[13px] text-v-secondary">Modal body content. Press Escape or click outside to close.</p>
                    </x-admin.modal>
                    <x-admin.confirm-dialog name="demo-confirm" title="Delete this item?" message="This action cannot be undone." confirm="Delete" action="#" method="POST" />
                </x-admin.panel>

                {{-- Empty and unconfigured: the two ends of "nothing
                     here". --}}
                <x-admin.panel title="Empty and unconfigured" id="kit-empty" flush>
                    <x-admin.empty-state title="No products yet" description="Products added here appear in your storefront immediately.">
                        <x-admin.button variant="primary"><x-admin.icon name="plus" class="w-3.5 h-3.5" /> Add product</x-admin.button>
                    </x-admin.empty-state>
                    <div class="v-panel__body">
                        <x-admin.empty-state variant="notice" title="No courier is enabled yet" description="Bookings are refused until at least one courier carries credentials.">
                            <a href="#" class="v-notice__btn">Configure a courier</a>
                        </x-admin.empty-state>
                        <div class="mt-3 v-flash">
                            <span class="v-flash__text">Booked. The AWB should have opened in a new tab.</span>
                            <a href="#" class="v-flash__btn">Open AWB</a>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Pagers: both halves of one material. --}}
                <x-admin.panel title="Pagers" id="kit-pager">
                    <div class="v-pager">
                        <span class="v-pager__info"><span class="v-pager__figure">27,709</span> records</span>
                        <div class="v-pager__list">
                            <span class="v-pager__step is-disabled">Previous</span>
                            <span class="v-pager__page is-current">1</span>
                            <a class="v-pager__page" href="#">2</a>
                            <a class="v-pager__page" href="#">3</a>
                            <span class="v-pager__gap">&hellip;</span>
                            <a class="v-pager__page" href="#">1386</a>
                            <a class="v-pager__step" href="#">Next</a>
                        </div>
                    </div>
                    <div class="v-pager mt-4">
                        <span class="v-pager__count">1 to 25 of 27,709</span>
                        <div class="v-pager__nav">
                            <x-admin.button size="sm" variant="secondary" disabled>Previous</x-admin.button>
                            <x-admin.button size="sm" variant="secondary" href="#">Next</x-admin.button>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Data display: the marks that carry figures. --}}
                <x-admin.panel title="Data display" id="kit-data">
                    <div class="v-accounts">
                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">Carrying the money</h3>
                            <ul class="v-rank">
                                <li class="v-rank__row">
                                    <span class="v-rank__name">{{ $longProduct }}</span>
                                    <x-admin.money mark="₱" amount="482,100.00" />
                                    <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                        <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                        <rect class="v-rank__fill" x="0" y="0" height="4" width="100"></rect>
                                    </svg>
                                </li>
                                <li class="v-rank__row">
                                    <span class="v-rank__name">Dunlop Tortex .88mm</span>
                                    <x-admin.money mark="₱" amount="41,050.00" />
                                    <svg class="v-rank__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                        <rect class="v-rank__track" x="0" y="0" width="100" height="4"></rect>
                                        <rect class="v-rank__fill" x="0" y="0" height="4" width="9"></rect>
                                    </svg>
                                </li>
                            </ul>
                        </div>
                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">Buyers</h3>
                            <ul class="v-mix">
                                <li class="v-mix__row"><span class="v-mix__name">New</span><span class="v-num v-mix__share">62.5%</span><span class="v-num v-mix__count">45</span></li>
                                <li class="v-mix__row"><span class="v-mix__name">Returning</span><span class="v-num v-mix__share">37.5%</span><span class="v-num v-mix__count">27</span></li>
                            </ul>
                        </div>
                        <div class="v-accounts__col">
                            <h3 class="v-label v-accounts__title">Identity accents</h3>
                            <ul class="v-mix">
                                @foreach (['Cyan', 'Violet', 'Green', 'Amber', 'Rose', 'Indigo'] as $i => $name)
                                    <li class="v-mix__row">
                                        <span class="v-mix__name">
                                            <svg class="v-tsw" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false">
                                                <rect class="v-slice-{{ $i }}" width="10" height="10" rx="3"></rect>
                                            </svg>{{ $name }}
                                        </span>
                                        <span class="v-num v-mix__share"></span>
                                        <span class="v-num v-mix__count">{{ $i }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- The v2.0 card, which is now the panel wearing its old
                     prop API. Here so a converted view can be compared
                     against an unconverted one. --}}
                <x-admin.card title="Card" description="The same sheet, reached through the v2.0 prop API.">
                    <x-slot:headerActions>
                        <x-admin.button size="sm" variant="secondary">Action</x-admin.button>
                    </x-slot:headerActions>
                    <p class="text-[13px] text-v-secondary">Card body.</p>
                    <x-slot:footer><span class="text-xs text-v-muted">Footer band.</span></x-slot:footer>
                </x-admin.card>
            </div>

            {{-- The rail: the row idioms, at the width they are read at. --}}
            <div class="v-split__rail">
                <x-admin.panel title="Lines" id="kit-lines" flush>
                    <ul class="v-lines">
                        <x-admin.line href="#" label="Waiting to be packed" :count="14" />
                        <x-admin.line href="#" label="Out of stock" :count="0" />
                        <x-admin.line href="#" label="Reviews to approve" :count="3" :detail="$longProduct" />
                    </ul>
                </x-admin.panel>

                <x-admin.panel title="Facts" id="kit-facts" flush>
                    <dl class="v-facts">
                        <dt class="v-label">On the shelf, at sale price</dt>
                        <dd><x-admin.money :mark="$money['mark']" :amount="$money['amount']" /></dd>
                        <dt class="v-label">Out of stock</dt>
                        <dd><span class="v-num v-facts__zero">0</span></dd>
                    </dl>
                </x-admin.panel>

                <x-admin.panel title="Automations" id="kit-autos" link="#" link-label="Manage" flush>
                    <ul class="v-autos">
                        <li class="v-auto">
                            <span class="v-auto__body">
                                <span class="v-auto__name">Back in stock notifications</span>
                                <span class="v-auto__sub">Last ran 3 days ago</span>
                            </span>
                            <x-admin.badge tone="danger">Failed</x-admin.badge>
                        </li>
                        <li class="v-auto">
                            <span class="v-auto__body">
                                <span class="v-auto__name">Review request emails</span>
                                <span class="v-auto__sub">Never run</span>
                            </span>
                            <x-admin.badge tone="warning">Overdue</x-admin.badge>
                        </li>
                    </ul>
                    <p class="v-autos__rest">The other 4 are on schedule.</p>
                </x-admin.panel>

                <x-admin.panel title="Feed" id="kit-feed" flush>
                    <ul class="v-feed">
                        <x-admin.feed.entry icon="orders" when="2 minutes ago" who="{{ $longCustomer }}" who-href="#">placed an order</x-admin.feed.entry>
                        <x-admin.feed.entry icon="cart" when="an hour ago" who="A deleted customer">added something to a cart</x-admin.feed.entry>
                        <x-admin.feed.entry icon="customers" when="yesterday" who="Ana Reyes" who-href="#">signed in</x-admin.feed.entry>
                    </ul>
                </x-admin.panel>

                <x-admin.panel title="Panel, nothing to show" id="kit-blank" flush>
                    <p class="v-panel__blank">Nothing recorded yet.</p>
                </x-admin.panel>
            </div>
        </div>
    </div>
</x-admin.shell>

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
    {{-- The kit's own head IS the primitive: x-admin.page, with the trail
         it derives from the path, the title, the standing line and the
         action set. There is one page head in the admin and this is it,
         so the kit demonstrates it by wearing it rather than by drawing a
         second copy of it further down the page. --}}
    <x-admin.page class="v-day v-settle" title="UI Kit"
        :breadcrumb="[['label' => 'Dashboard', 'url' => route('admin.dashboard')]]"
        standing="Every promoted primitive, in every state the admin can put it in.">
        <x-slot:actions>
            <x-admin.button variant="primary">
                <x-admin.icon name="plus" class="w-4 h-4" /> Primary action
            </x-admin.button>
        </x-slot:actions>

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

        {{-- A page that reads two measures rather than five. Same block,
             capped so the pair keeps the figure's own width instead of
             stretching across the page. --}}
        <div class="v-stats v-stats--few mb-5">
            <x-admin.figure label="Total Abandoned" value="312" accent="amber" icon="cart" />
            <x-admin.figure label="Total Value" :value="$money['amount']" :mark="$money['mark']" accent="violet" icon="banknote" />
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

                    {{-- Two badges that are also controls. Both keep the
                         12px mark and both take the 24px target: the
                         first by growing its own box, the second by a
                         transparent pad that moves nothing. --}}
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <button type="button" class="v-badgebtn">
                            <x-admin.badge tone="success">Active</x-admin.badge>
                        </button>
                        <button type="button" class="v-badgebtn">
                            <x-admin.badge tone="neutral">Disabled</x-admin.badge>
                        </button>
                        <x-admin.badge tone="accent">
                            Metro Manila
                            <button type="button" class="v-badge__x" aria-label="Remove Metro Manila">
                                <x-admin.icon name="x" class="w-3 h-3" />
                            </button>
                        </x-admin.badge>
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

                    {{-- The same row carrying labelled fields. An index whose
                         filters name themselves above the control and one whose
                         filters name themselves inside it share a baseline. --}}
                    <x-admin.filters>
                        <x-admin.field name="kit_search" label="Search" class="min-w-[200px] flex-1">
                            <x-admin.input type="search" name="kit_search" placeholder="Name, SKU&hellip;" />
                        </x-admin.field>
                        <x-admin.field name="kit_status" label="Status" class="w-40">
                            <x-admin.select name="kit_status">
                                <option>All Status</option>
                                <option>Active</option>
                            </x-admin.select>
                        </x-admin.field>
                        <x-admin.button variant="secondary">Filter</x-admin.button>
                        <a class="v-filters__reset" href="#">Clear</a>
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
                            {{-- The actions column. `v-col-act` shrinks it to
                                 its controls, and the cell drops its own
                                 padding so 28px of button lands inside a 34px
                                 row: a real target that costs no height. --}}
                            <x-admin.table.th class="v-col-act">Actions</x-admin.table.th>
                        </x-slot:head>
                        <x-admin.table.row href="#">
                            <x-admin.table.cell class="text-v-text"><a href="#" class="v-quietlink">{{ $longCustomer }}</a></x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__item">{{ $longProduct }}</span><span class="v-entry__more">and 4 more</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="warning">Processing</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money :mark="$money['mark']" :amount="$money['amount']" /></x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" href="#">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                        <x-admin.table.row href="#">
                            {{-- Two or three facts stacked in one cell: the
                                 lead line, then the identifier under it. The
                                 idiom twelve index views use for a record
                                 that cannot be named in one word. --}}
                            <x-admin.table.cell class="text-v-text">
                                <span class="v-cellstack">
                                    <a href="#" class="v-quietlink v-lead">Ana Reyes</a>
                                    <span class="v-sub">ana.reyes@example.com</span>
                                </span>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__item">Dunlop Tortex .88mm</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="info">Shipped</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money mark="₱" amount="899.00" /></x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" href="#">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                                </div>
                            </x-admin.table.cell>
                        </x-admin.table.row>
                        <x-admin.table.row href="#">
                            <x-admin.table.cell class="text-v-text"><a href="#" class="v-quietlink">Miguel Santos</a></x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary"><span class="v-entry__more">No line items</span></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.badge tone="danger">Failed</x-admin.badge></x-admin.table.cell>
                            <x-admin.table.cell><x-admin.money mark="₱" amount="0.00" zero /></x-admin.table.cell>
                            <x-admin.table.cell class="v-col-act">
                                <div class="v-rowactions v-rowactions--end">
                                    <x-admin.button size="sm" variant="ghost" href="#">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                                </div>
                            </x-admin.table.cell>
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

                {{-- A table inside a panel that pads, written the way
                     sixteen settings routes wrote it: the table drawing
                     its own sheet on top of the sheet already holding
                     it. It renders here with one edge and one ground,
                     because a sheet inside a sheet is stripped in
                     panel.css rather than left to a prop each caller
                     has to remember. `bare` still says the same thing;
                     it is no longer the only thing standing between a
                     page and a doubled border. --}}
                <x-admin.panel title="Table inside a panel" id="kit-table-nested">
                    <x-admin.table>
                        <x-slot:head>
                            <x-admin.table.th>Currency</x-admin.table.th>
                            <x-admin.table.th align="right">Rate</x-admin.table.th>
                        </x-slot:head>
                        <x-admin.table.row>
                            <x-admin.table.cell>Philippine peso</x-admin.table.cell>
                            <x-admin.table.cell align="right"><span class="v-num">1.000000</span></x-admin.table.cell>
                        </x-admin.table.row>
                        <x-admin.table.row>
                            <x-admin.table.cell>United States dollar</x-admin.table.cell>
                            <x-admin.table.cell align="right"><span class="v-num">0.017241</span></x-admin.table.cell>
                        </x-admin.table.row>
                    </x-admin.table>
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
                        <x-admin.field name="demo_name" label="Store name" required help="Shown in the storefront header.">
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
                        {{-- A stored key, in the box that owns it: masked
                             and read only at rest, revealed in place, and
                             editable without the value moving or vanishing
                             under the person editing it. The second one has
                             nothing stored, so it is an ordinary field. --}}
                        <x-admin.field name="demo_secret" label="Secret key">
                            <x-admin.secret name="demo_secret"
                                masked="••••••••••••••••••••••••••••fQ==" value="0x4AAAAAAAxHRaXaZFbqXkTfQ=="
                                placeholder="Enter secret key" />
                        </x-admin.field>
                        <x-admin.field name="demo_secret_unset" label="Secret key, none stored">
                            <x-admin.secret name="demo_secret_unset" placeholder="Enter secret key" />
                        </x-admin.field>
                        <div class="flex items-center gap-6">
                            <x-admin.toggle name="demo_active" :checked="true" />
                            {{-- The box stays 16px; `.v-check` is the
                                 target around it, 24 tall always and 24
                                 wide when there are no words. --}}
                            <x-admin.checkbox name="demo_agree" label="Send review emails" :checked="true" />
                            <x-admin.checkbox name="demo_bare" aria-label="Bare box, no words beside it" />
                        </div>
                        <x-admin.radio-group name="demo_mode" :options="['sale' => 'Sale price (strikethrough)', 'override' => 'Override (clean replacement)']" value="sale" />
                        <x-admin.form-actions>
                            <x-admin.button variant="secondary">Cancel</x-admin.button>
                            <x-admin.button variant="primary" type="submit">Save changes</x-admin.button>
                        </x-admin.form-actions>
                    </form>
                </x-admin.panel>

                {{-- Workspace: the rail a region keeps beside its work,
                     and the picker it becomes when there is no room for
                     a rail. Shown at the width it is read at; on a real
                     page it is the page, not a panel. --}}
                <x-admin.panel title="Workspace rail" id="kit-workspace">
                    <x-admin.workspace back="#" back-label="All Settings" label="Kit sections" narrow
                        :groups="[
                            ['label' => 'Store', 'items' => [
                                ['label' => 'General', 'url' => '#', 'active' => true],
                                ['label' => 'Checkout', 'url' => '#'],
                                ['label' => 'Currencies', 'url' => '#'],
                            ]],
                            ['label' => 'Selling', 'items' => [
                                ['label' => 'Payments', 'url' => '#'],
                                ['label' => 'Shipping', 'url' => '#'],
                                ['label' => 'Order Statuses', 'url' => '#'],
                            ]],
                        ]">
                        <p class="text-[13px] text-v-secondary">The work column. It carries the page, and takes the measure when the page is a form.</p>
                    </x-admin.workspace>
                </x-admin.panel>

                {{-- Slates: a grid of the store's own parts. Each says
                     what it is, whether it is on, and the way in. One
                     that IS the way in, one carrying its own controls,
                     and one numbered by its place in a chain. --}}
                <x-admin.panel title="Slates" id="kit-slates">
                    <div class="v-slates">
                        <x-admin.slate title="Mail" icon="settings" href="#"
                            note="SMTP host, sender identity, and a test send." />
                        <x-admin.slate title="Cash on Delivery" icon="cart"
                            note="The rider collects the order total.">
                            <x-slot:state>
                                <x-admin.toggle name="kit_slate_cod" :checked="true" aria-label="Cash on Delivery enabled" />
                            </x-slot:state>
                            {{-- A line inside a slate that is itself a
                                 destination, at the 24px floor. --}}
                            <a href="#" class="v-slate__link">
                                <x-admin.badge tone="warning">Restricted</x-admin.badge>
                                <span class="truncate text-xs text-v-muted">Metro Manila only, under 20,000</span>
                            </a>
                        </x-admin.slate>
                        <x-admin.slate title="Courier Fulfillment" icon="fulfillment"
                            note="Book parcels with QuadX and SPX from the queue.">
                            <x-slot:state><x-admin.badge tone="success">Enabled</x-admin.badge></x-slot:state>
                            <x-slot:meta>v2.4.0</x-slot:meta>
                            <x-slot:actions>
                                <x-admin.button variant="ghost" size="sm" href="#">Configure</x-admin.button>
                                <x-admin.button variant="ghost" size="sm" class="v-rowactions__danger">Uninstall</x-admin.button>
                            </x-slot:actions>
                        </x-admin.slate>
                        <x-admin.slate title="Free Shipping" :number="1"
                            note="Waives shipping when a rule matches the cart.">
                            <x-slot:state><x-admin.badge tone="neutral">No active rules</x-admin.badge></x-slot:state>
                            <x-slot:meta>0 rules</x-slot:meta>
                            <x-slot:actions>
                                <x-admin.hint label="What a match does">A matching rule replaces every paid method below it, rather than sitting alongside them.</x-admin.hint>
                                <x-admin.button variant="ghost" size="sm" href="#">Configure</x-admin.button>
                            </x-slot:actions>
                        </x-admin.slate>
                    </div>
                </x-admin.panel>

                {{-- Switches: one material, two drivers. A checkbox
                     posts with the form around it; a button posts on
                     its own. --}}
                <x-admin.panel title="Switches" id="kit-switch">
                    <div class="flex flex-col items-start gap-4">
                        <x-admin.toggle name="kit_switch_a" label="Enable low stock email alerts" :checked="true" />
                        <x-admin.toggle name="kit_switch_b" label="Skip notifications when every cart item is out of stock" />
                        <x-admin.toggle name="kit_switch_c" label="Enabled" lead :checked="true" />
                        <div class="flex items-center gap-4">
                            <x-admin.switch :checked="true" label="Philippines enabled" />
                            <x-admin.switch :checked="false" label="Singapore enabled" />
                            <x-admin.switch :checked="false" label="A courier that cannot be reached" disabled />
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Checklist: a bounded, scrolling set of boxes, for a
                     scope whose length the merchant decides. --}}
                <x-admin.panel title="Checklist" id="kit-checklist">
                    <x-admin.field name="kit_zones" label="Geo zones" help="None checked quotes for every zone." class="max-w-sm">
                        <div class="v-checklist">
                            @foreach (['Domestic', 'Metro Manila', 'Luzon', 'Visayas', 'Mindanao', 'International'] as $i => $zone)
                                <x-admin.checkbox name="kit_zones[]" :value="$i" :label="$zone" :checked="$i < 2" />
                            @endforeach
                        </div>
                    </x-admin.field>
                </x-admin.panel>

                {{-- Picker: the checklist's sibling, for a set too long
                     to print in full. Shown with the fixture the dual
                     listbox actually carries: a highlighted option in
                     each pane, so both states of `aria-selected` are on
                     the page, and the trailing pane's danger wash beside
                     the leading pane's accent. --}}
                <x-admin.panel title="Picker" id="kit-picker">
                    <div class="v-picker">
                        <div class="v-picker__pane v-picker__pane--in">
                            <div class="v-picker__head">
                                <span class="v-label">Available</span>
                                <input type="text" class="v-control w-full h-[var(--control-h-sm)] px-2.5 text-xs bg-v-surface text-v-text border rounded-v-md placeholder:text-v-muted" placeholder="Search..." aria-label="Search available kit items">
                            </div>
                            <div class="v-picker__list">
                                <div role="listbox" aria-multiselectable="true" aria-label="Available kit items">
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="true">Metro Manila</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">North Luzon</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">{{ $longCustomer }}</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">Visayas</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">Mindanao</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">International</button>
                                </div>
                            </div>
                        </div>
                        <div class="v-picker__mid">
                            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0" aria-label="Add all">
                                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5 -mr-1.5" />
                                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5" />
                            </x-admin.button>
                            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0" aria-label="Add highlighted">
                                <x-admin.icon name="chevron-right" class="w-3.5 h-3.5" />
                            </x-admin.button>
                            <x-admin.button variant="secondary" size="sm" class="w-8 shrink-0 !px-0" aria-label="Remove highlighted" disabled>
                                <x-admin.icon name="chevron-left" class="w-3.5 h-3.5" />
                            </x-admin.button>
                        </div>
                        <div class="v-picker__pane v-picker__pane--out">
                            <div class="v-picker__head">
                                <span class="v-label">Selected <span class="text-v-accent">(2)</span></span>
                            </div>
                            <div class="v-picker__list">
                                <div role="listbox" aria-multiselectable="true" aria-label="Selected kit items">
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="true">Domestic</button>
                                    <button type="button" class="v-picker__opt" role="option" aria-selected="false">South Luzon</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Tiles: the grid of things you look at rather than
                     read. A folder, a file, a picked file, and a file
                     carrying its own controls. --}}
                <x-admin.panel title="Tiles" id="kit-tiles">
                    <div class="v-tiles">
                        <button type="button" class="v-tile v-tile--dir">
                            <span class="v-tile__art"><x-admin.icon name="folder" class="v-tile__mark" /></span>
                            <span class="v-tile__name v-tile__name--dim">..</span>
                        </button>
                        <button type="button" class="v-tile v-tile--dir">
                            <span class="v-tile__art"><x-admin.icon name="folder" class="v-tile__mark" /></span>
                            <span class="v-tile__name">boss-pedals-and-a-very-long-folder-name</span>
                        </button>
                        <button type="button" class="v-tile" aria-pressed="true">
                            <span class="v-tile__art"><span class="v-tile__mark"><x-admin.icon name="media" class="w-full h-full" /></span></span>
                            <span class="v-tile__name">ds1-distortion-front-01.jpg</span>
                        </button>
                        <div class="v-tile-wrap">
                            <div class="v-tile">
                                <span class="v-tile__art"><span class="v-tile__mark"><x-admin.icon name="media" class="w-full h-full" /></span></span>
                                <span class="v-tile__name">ernie-ball-2221-regular-slinky.jpg</span>
                            </div>
                            <div class="v-tile__acts">
                                <button type="button" class="v-tile__act" aria-label="Copy path"><x-admin.icon name="copy" class="w-3.5 h-3.5" /></button>
                                <button type="button" class="v-tile__act v-tile__act--danger" aria-label="Delete"><x-admin.icon name="trash" class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>
                    </div>

                    <div class="v-tray mt-4">
                        <x-admin.empty-state title="No images yet" />
                    </div>

                    {{-- The drag overlay, drawn resting so it can be read.
                         On a real grid it is `x-show="dragover"`. --}}
                    <div class="relative mt-4 h-28">
                        <div class="v-drop">
                            <x-admin.icon name="upload" class="w-8 h-8 text-v-accent" />
                            <p class="v-drop__text">Drop files to upload</p>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Sortable rows: ordering that is stored rather than
                     derived, one level of nesting. --}}
                <x-admin.panel title="Sortable rows" id="kit-sort" flush>
                    <div class="v-sortlist">
                        <div>
                            <div class="v-sortrow">
                                <span class="v-sortrow__grip" aria-hidden="true"><x-admin.icon name="drag" class="w-4 h-4" /></span>
                                <div class="v-sortrow__body">
                                    <span class="v-sortrow__name">Pedals</span>
                                    <span class="v-sortrow__slug">/pedals</span>
                                </div>
                                <span class="v-sortrow__fact">248 products</span>
                                <x-admin.badge tone="success">Active</x-admin.badge>
                                <div class="v-rowactions">
                                    <x-admin.button size="sm" variant="ghost">Edit</x-admin.button>
                                    <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                                </div>
                            </div>
                            <div class="v-sortnest v-sortlist">
                                <div class="v-sortrow">
                                    <span class="v-sortrow__grip" aria-hidden="true"><x-admin.icon name="drag" class="w-4 h-4" /></span>
                                    <div class="v-sortrow__body">
                                        <span class="v-sortrow__name">Overdrive and Distortion</span>
                                        <span class="v-sortrow__slug">/pedals/overdrive-and-distortion</span>
                                    </div>
                                    <span class="v-sortrow__fact">91</span>
                                    <x-admin.badge tone="neutral">Inactive</x-admin.badge>
                                    <div class="v-rowactions">
                                        <x-admin.button size="sm" variant="ghost">Edit</x-admin.button>
                                        <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="v-sortrow">
                            <span class="v-sortrow__grip" aria-hidden="true"><x-admin.icon name="drag" class="w-4 h-4" /></span>
                            <div class="v-sortrow__body">
                                <span class="v-sortrow__name">Strings</span>
                                <span class="v-sortrow__slug">/strings</span>
                            </div>
                            <span class="v-sortrow__fact">27,709 products</span>
                            <x-admin.badge tone="success">Active</x-admin.badge>
                            <div class="v-rowactions">
                                <x-admin.button size="sm" variant="ghost">Edit</x-admin.button>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger">Delete</x-admin.button>
                            </div>
                        </div>
                    </div>
                </x-admin.panel>

                {{-- Selectable cards, down and across, in all four states.
                     The state is a word every time: with the grey removed,
                     the card still says what it is. --}}
                <x-admin.panel title="Selectable cards" id="kit-choice">
                    <x-admin.choice>
                        <x-admin.choice.card name="QuadX" mode="Staging" fee="from PHP 145.00" />
                        <x-admin.choice.card name="SPX Express" />
                        <x-admin.choice.card name="LBC Express" :available="false" state="Not configured">
                            Missing App ID / App Secret. Configure in <a href="#" class="v-choice__offlink">Settings &rarr; Courier Fulfillment &rarr; LBC Express</a>.
                        </x-admin.choice.card>
                    </x-admin.choice>

                    {{-- The same set wearing its marks. `mark` names a plate
                         a stylesheet binds artwork to, and is why the real
                         courier logos live in the extension that knows whose
                         they are; `icon` is the drawn alternative, on the
                         same square, for an option no logo exists for. The
                         three unavailable states drain their mark, so a
                         greyed card greys as one thing. --}}
                    <p class="v-choice__note mt-5">No integrated courier will carry this order to Alegria, Caluya, Antique.</p>
                    <x-admin.choice cols>
                        <x-admin.choice.card name="QuadX" mode="Staging" fee="from PHP 80.00" icon="brands" />
                        {{-- Could not check: greyed, and still a real button.
                             A courier we could not reach has refused
                             nothing. Its words are printed because this
                             failure is one we do not recognise. --}}
                        <x-admin.choice.card name="SPX Express" state="Could not check" icon="brands">
                            SPX Express said:<span class="v-choice__said">Sorry, ESF calculation failed because of the address you selected does not match available route code</span>
                        </x-admin.choice.card>
                        {{-- Not serviceable: one sentence, ours, naming what
                             was refused. A failure we recognise never prints
                             the courier's words as well. --}}
                        <x-admin.choice.card name="LBC Express" :available="false" state="Not serviceable" icon="brands">
                            LBC Express does not deliver to Alegria, Caluya, Antique.
                        </x-admin.choice.card>
                        <x-admin.choice.card name="Another courier" mode="Manual" icon="fulfillment" />
                    </x-admin.choice>

                    {{-- Before any card can be drawn: a named wait, because
                         a real network call is happening. --}}
                    <div class="v-choice__wait">
                        <span class="v-choice__waitmark" aria-hidden="true"></span>
                        <span>Asking QuadX, SPX Express and LBC Express whether they deliver to Alegria, Caluya, Antique</span>
                    </div>
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

                {{-- Measures and plots: the four shapes the brief named as
                     promoted and which the kit did not carry until phase 5.
                     The comparison band and its change words, the revenue
                     plot, the share ring with its six identity strokes, and
                     the part-to-whole chips. All four are drawn from
                     fixtures here, on the same markup the dashboard uses, so
                     a change to any of them fails visibly on this page
                     before it reaches the page the owner reads daily. --}}
                <x-admin.panel title="Measures and plots" id="kit-measures">
                    @php
                        // Hostile on purpose: a zero row, a nine digit row,
                        // and one of each change direction.
                        $kitBandRows = [
                            ['label' => 'Revenue', 'money' => true, 'now' => '148,392,207.55', 'ref' => '96,120,004.10', 'dir' => 'ahead', 'words' => '54.4% ahead'],
                            ['label' => 'Orders', 'money' => false, 'now' => '27,709', 'ref' => '31,004', 'dir' => 'behind', 'words' => '10.6% behind'],
                            ['label' => 'Refunds', 'money' => false, 'now' => '0', 'ref' => '0', 'dir' => 'level', 'words' => 'Level'],
                        ];
                    @endphp

                    <div class="v-bandblock">
                        <h3 class="v-label v-bandblock__title">
                            This month against last
                            <x-admin.hint label="This month against last">Both columns count the same order statuses.</x-admin.hint>
                        </h3>

                        <div class="v-band v-band--pulse">
                            <div class="v-fig v-fig--head" aria-hidden="true">
                                <span class="v-fig__name"></span>
                                <span class="v-fig__cell v-label">This month</span>
                                <span class="v-fig__cell v-label">Last month</span>
                                <span class="v-fig__cell v-label">Change</span>
                            </div>

                            @foreach ($kitBandRows as $row)
                                <div class="v-fig">
                                    <span class="v-fig__name">{{ $row['label'] }}</span>
                                    @foreach ([['This month', $row['now'], true], ['Last month', $row['ref'], false]] as [$when, $raw, $lead])
                                        <span class="v-fig__cell {{ $lead ? 'v-fig__cell--now' : '' }}">
                                            <span class="v-fig__when">{{ $when }}</span>
                                            @if ($row['money'])
                                                <x-admin.money mark="₱" :amount="$raw" />
                                            @else
                                                <span class="v-num v-fig__plain {{ $raw === '0' ? 'v-fig__plain--zero' : '' }}">{{ $raw }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                    <span class="v-fig__cell v-fig__cell--change">
                                        <span class="v-fig__when">Change</span>
                                        <span class="v-change v-change--{{ $row['dir'] }}">
                                            @if ($row['dir'] !== 'level')
                                                <x-admin.icon :name="$row['dir'] === 'ahead' ? 'arrow-up-right' : 'arrow-down-right'" class="v-change__go" />
                                            @endif
                                            {{ $row['words'] }}
                                        </span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Today against a usual weekday: one quiet sentence. --}}
                    <p class="v-usual">A usual Monday takes <span class="v-num">₱41,200.00</span> across <span class="v-num">14</span> orders.</p>

                    {{-- The plot. SalesPlot::payload is a pure function over
                         a series, so the kit hands it a fixture and no query
                         runs on this page. --}}
                    @php
                        $kitCurrent = [18400.0, 22750.0, 15900.0, 31200.0, 28650.0, 44100.0, 39800.0];
                        $kitEarlier = [21000.0, 19400.0, 24800.0, 26100.0, 33500.0, 30200.0, 22900.0];
                        $kitLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $kitFull = array_map(fn ($d) => $d . ' 4 Aug 2026', $kitLabels);
                        $kitSeries = [
                            'range' => 'this-week',
                            'granularity' => 'day',
                            'title' => 'This week',
                            'nowName' => 'This week',
                            'thenName' => 'Same days a year earlier',
                            'labels' => $kitLabels,
                            'full' => $kitFull,
                            'earlierLabels' => array_map(fn ($d) => $d . ' 2025', $kitLabels),
                            'current' => $kitCurrent,
                            'earlier' => $kitEarlier,
                            'now' => ['label' => 'Today', 'sofar' => true, 'value' => 39800.0],
                            'hasEarlier' => true,
                            'currentPeak' => max($kitCurrent),
                            'earlierPeak' => max($kitEarlier),
                            'peak' => max(max($kitCurrent), max($kitEarlier)),
                            'hasTrade' => true,
                        ];
                    @endphp
                    <div class="mt-4">
                        @include('admin.partials.pulse-plot', ['plot' => $kitSeries, 'currency' => '₱'])
                    </div>

                    {{-- The ring and its records, and the chips. Six segments,
                         so every identity stroke and every slice is on the
                         page at once. --}}
                    @php
                        $kitRing = [
                            ['label' => 'Metro Manila', 'orders' => 12480, 'revenue' => '6,240,000.00', 'share' => 45.1],
                            ['label' => 'Calabarzon', 'orders' => 6120, 'revenue' => '3,060,000.00', 'share' => 22.1],
                            ['label' => 'Central Luzon', 'orders' => 4010, 'revenue' => '2,005,000.00', 'share' => 14.5],
                            ['label' => 'Central Visayas', 'orders' => 2600, 'revenue' => '1,300,000.00', 'share' => 9.4],
                            ['label' => 'Davao Region', 'orders' => 1599, 'revenue' => '799,500.00', 'share' => 5.8],
                            ['label' => 'Everywhere else', 'orders' => 900, 'revenue' => '450,000.00', 'share' => 3.1],
                        ];
                        $kitR = 78;
                        $kitC = 2 * M_PI * $kitR;
                        $kitAt = 0.0;
                    @endphp
                    <div class="v-breakdown mt-4">
                        <div class="v-breakdown__ring">
                            <svg class="v-ring" width="100%" height="180" viewBox="0 0 200 200"
                                 role="img" aria-label="Share of orders by region. Every figure is in the table beside this ring.">
                                @foreach ($kitRing as $i => $seg)
                                    @php
                                        $share = $seg['share'] / 100;
                                        $dash = round($share * $kitC, 2);
                                        $offset = round(-$kitAt * $kitC, 2);
                                        $kitAt += $share;
                                    @endphp
                                    <circle class="v-ring__seg v-stroke-{{ $i % 6 }}"
                                            cx="100" cy="100" r="{{ $kitR }}"
                                            stroke-dasharray="{{ $dash }} {{ round($kitC, 2) }}"
                                            stroke-dashoffset="{{ $offset }}"
                                            transform="rotate(-90 100 100)">
                                        <title>{{ $seg['label'] }}: {{ number_format($seg['orders']) }} orders</title>
                                    </circle>
                                @endforeach
                                <text class="v-ring__v v-num" x="100" y="94" text-anchor="middle">27,709</text>
                                <text class="v-ring__k" x="100" y="112" text-anchor="middle">orders</text>
                                <text class="v-ring__s" x="100" y="128" text-anchor="middle"></text>
                            </svg>
                        </div>

                        <x-admin.table bare class="v-table-wrap--gutter v-breakdown__records">
                            <x-slot:head>
                                <x-admin.table.th>Region</x-admin.table.th>
                                <x-admin.table.th align="right">Orders</x-admin.table.th>
                                <x-admin.table.th align="right">Revenue</x-admin.table.th>
                                <x-admin.table.th align="right">Share</x-admin.table.th>
                            </x-slot:head>
                            @foreach ($kitRing as $i => $seg)
                                <tr>
                                    <x-admin.table.cell class="text-v-text">
                                        <svg class="v-tsw" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false">
                                            <rect class="v-slice-{{ $i % 6 }}" width="10" height="10" rx="3"></rect>
                                        </svg>{{ $seg['label'] }}
                                    </x-admin.table.cell>
                                    <x-admin.table.cell><span class="v-num">{{ number_format($seg['orders']) }}</span></x-admin.table.cell>
                                    <x-admin.table.cell><x-admin.money mark="₱" :amount="$seg['revenue']" /></x-admin.table.cell>
                                    <x-admin.table.cell><span class="v-num">{{ $seg['share'] }}%</span></x-admin.table.cell>
                                </tr>
                            @endforeach
                        </x-admin.table>
                    </div>

                    <div class="v-chans mt-4">
                        @foreach ([['Bank Transfer (Union Bank of the Philippines)', 4820, '2,410,000.00', '61.4'], ['Cash on Delivery', 2410, '1,205,000.00', '30.7'], ['HitPay', 620, '310,000.00', '7.9']] as $i => [$label, $orders, $revenue, $share])
                            <div class="v-chan">
                                <svg class="v-chan__ic" width="38" height="38" viewBox="0 0 38 38" aria-hidden="true" focusable="false">
                                    <rect class="v-slice-{{ $i % 6 }}" width="38" height="38" rx="12"></rect>
                                    <text class="v-chan__in" x="19" y="19" text-anchor="middle" dominant-baseline="central">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($label, 0, 1)) }}</text>
                                </svg>
                                <span class="v-chan__body">
                                    <span class="v-chan__n">{{ $label }}</span>
                                    <span class="v-chan__s">{{ number_format($orders) }} orders</span>
                                </span>
                                <span class="v-chan__q">
                                    <x-admin.money mark="₱" :amount="$revenue" class="v-chan__qv" />
                                    <span class="v-num v-chan__ql">{{ $share }}%</span>
                                </span>
                            </div>
                        @endforeach
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
                        <dt class="v-label v-facts__sum">Total</dt>
                        <dd class="v-facts__sum"><x-admin.money :mark="$money['mark']" :amount="$money['amount']" /></dd>
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

                {{-- The feed a record's own history runs: one kind of act,
                     so one plain mark; the line somebody wrote; and the
                     one control the row allows. --}}
                <x-admin.panel title="History" id="kit-history" flush>
                    <ul class="v-feed">
                        <x-admin.feed.entry icon="orders" tone="v-feed__ic--plain" when="Aug 10, 2026 9:14am by Paolo">
                            <x-admin.badge tone="success">Delivered</x-admin.badge>
                            <x-admin.badge tone="info" :dot="false">Notified</x-admin.badge>
                            <x-slot:note>Left with the building guard, second attempt. Customer confirmed by SMS.</x-slot:note>
                            <x-slot:action>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" aria-label="Delete history entry">
                                    <x-admin.icon name="trash" class="w-3.5 h-3.5" />
                                </x-admin.button>
                            </x-slot:action>
                        </x-admin.feed.entry>
                        <x-admin.feed.entry icon="orders" tone="v-feed__ic--plain" when="Aug 8, 2026 4:02pm">
                            <x-admin.badge tone="neutral">Processing</x-admin.badge>
                            <x-slot:action>
                                <x-admin.button size="sm" variant="ghost" class="v-rowactions__danger" aria-label="Delete history entry">
                                    <x-admin.icon name="trash" class="w-3.5 h-3.5" />
                                </x-admin.button>
                            </x-slot:action>
                        </x-admin.feed.entry>
                    </ul>
                </x-admin.panel>

                <x-admin.panel title="Panel, nothing to show" id="kit-blank" flush>
                    <p class="v-panel__blank">Nothing recorded yet.</p>
                </x-admin.panel>
            </div>
        </div>
    </x-admin.page>
</x-admin.shell>

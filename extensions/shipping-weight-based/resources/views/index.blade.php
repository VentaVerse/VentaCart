<x-admin.shell title="Weight Based">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Weight Based</span>
            </div>
            <h1 class="v-head__title">Weight Based</h1>
            <p class="v-head__standing">Methods priced by cart weight. One save commits every method below.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="shipping-weight-based" />
            <a href="{{ route('admin.weight-based-methods.create') }}" class="v-btn v-btn--primary"><x-admin.icon name="plus" /> Add method</a>
        </div>
    </div>

    <div class="v-settle">
        @if ($weightMethods->isEmpty())
            <div class="v-empty">
                <p class="v-empty__title">No weight-based methods</p>
                <p class="v-empty__line">Create tiers priced by cart weight.</p>
            </div>
        @else
            <x-admin.form :action="route('admin.weight-based-methods.bulk-update')" method="PUT">
                @foreach ($weightMethods as $wm)
                    <div class="v-dragcard" x-data="{
                            open: false,
                            rates: @js($wm->rates->map(fn ($r) => ['weight_from' => $r->weight_from, 'weight_to' => $r->weight_to, 'cost' => $r->cost])),
                            addRate() {
                                const last = this.rates.length > 0 ? this.rates[this.rates.length - 1] : null;
                                this.rates.push({
                                    weight_from: last ? last.weight_to : '0.000',
                                    weight_to: last ? (parseFloat(last.weight_to) + 5).toFixed(3) : '5.000',
                                    cost: last ? last.cost : '0.00',
                                });
                            },
                        }">
                        <div class="v-dragrow v-dragrow--bare">
                            <span class="v-dir__body">
                                <span class="v-dir__name">{{ $wm->admin_name ?: $wm->name }}</span>
                                <span class="v-dir__line v-block">
                                    {{ $wm->geoZones->isNotEmpty() ? $wm->geoZones->pluck('name')->implode(', ') : 'All zones' }}
                                    · {{ $wm->customerGroups->isNotEmpty() ? $wm->customerGroups->pluck('name')->implode(', ') : 'All groups' }}
                                    · {{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}
                                </span>
                            </span>
                            <span class="v-dragrow__end">
                                <span class="v-badge {{ $wm->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $wm->is_active ? 'Active' : 'Inactive' }}</span>
                                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="open = !open"
                                        :aria-expanded="open ? 'true' : 'false'" x-text="open ? 'Close' : 'Edit'">Edit</button>
                            </span>
                        </div>

                        <div x-show="open" x-cloak class="v-dragcard__body">
                            <div class="v-grid v-grid--3">
                                <x-admin.field :name="'methods.' . $wm->id . '.admin_name'" label="Admin name" hint="Never shown to customers.">
                                    <input type="text" name="methods[{{ $wm->id }}][admin_name]" value="{{ $wm->admin_name }}" placeholder="Internal reference" class="v-control">
                                </x-admin.field>
                                <x-admin.field :name="'methods.' . $wm->id . '.name'" label="Checkout name" required>
                                    <input type="text" name="methods[{{ $wm->id }}][name]" value="{{ $wm->name }}" required class="v-control">
                                </x-admin.field>
                                <div class="v-actrow">
                                    <x-admin.field :name="'methods.' . $wm->id . '.sort_order'" label="Sort">
                                        <input type="number" name="methods[{{ $wm->id }}][sort_order]" value="{{ $wm->sort_order }}" class="v-control v-mono v-w-sort">
                                    </x-admin.field>
                                    <label class="v-toggle">
                                        <input type="hidden" name="methods[{{ $wm->id }}][is_active]" value="0">
                                        <input type="checkbox" name="methods[{{ $wm->id }}][is_active]" value="1" @checked($wm->is_active)>
                                        <span class="v-toggle__track" aria-hidden="true"></span>
                                        Active
                                    </label>
                                </div>
                            </div>

                            <div class="v-grid v-grid--2 v-gap">
                                <x-admin.field :name="'methods.' . $wm->id . '.geo_zone_ids'" label="Geo zones" hint="None checked quotes for every zone.">
                                    <div class="v-checklist">
                                        @foreach ($geoZones as $zone)
                                            <label class="v-check">
                                                <input type="checkbox" name="methods[{{ $wm->id }}][geo_zone_ids][]" value="{{ $zone->id }}"
                                                       @checked($wm->geoZones->contains('id', $zone->id))>
                                                {{ $zone->name }}
                                            </label>
                                        @endforeach
                                        @if ($geoZones->isEmpty())
                                            <p class="v-cell-quiet">No geo zones</p>
                                        @endif
                                    </div>
                                </x-admin.field>
                                <x-admin.field :name="'methods.' . $wm->id . '.customer_group_ids'" label="Customer groups" hint="None checked quotes for every customer.">
                                    <div class="v-checklist">
                                        @foreach ($customerGroups as $group)
                                            <label class="v-check">
                                                <input type="checkbox" name="methods[{{ $wm->id }}][customer_group_ids][]" value="{{ $group->id }}"
                                                       @checked($wm->customerGroups->contains('id', $group->id))>
                                                {{ $group->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </x-admin.field>
                            </div>

                            <div class="v-gap">
                                <div class="v-actrow v-gap-sm">
                                    <span class="v-label">Weight tiers</span>
                                    <button type="button" class="v-btn v-btn--secondary v-btn--sm" @click="addRate(); touch()">
                                        <x-admin.icon name="plus" /> Add tier
                                    </button>
                                </div>
                                <template x-if="rates.length > 0">
                                    <div>
                                        <template x-for="(rate, i) in rates" :key="i">
                                            <div class="v-row">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" aria-label="Weight from" class="v-control v-mono">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" aria-label="Weight to" class="v-control v-mono">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" aria-label="Cost" class="v-control v-mono">
                                                <button type="button" class="v-row__x" @click="rates.splice(i, 1); touch()" aria-label="Remove tier"><x-admin.icon name="x" /></button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="rates.length === 0">
                                    <p class="v-cell-quiet">No tiers yet.</p>
                                </template>
                            </div>

                            <div class="v-gap v-actrow">
                                <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-wm-{{ $wm->id }}')">Delete method</button>
                                <button type="button" class="v-btn v-btn--ghost v-btn--sm" @click="document.getElementById('duplicate-wm-{{ $wm->id }}').submit()">Duplicate</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </x-admin.form>

            {{-- Delete and duplicate cannot live inside the bulk form: forms do not nest. --}}
            @foreach ($weightMethods as $wm)
                <form id="duplicate-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.duplicate', $wm) }}" hidden>
                    @csrf
                </form>
                <x-admin.confirm name="delete-wm-{{ $wm->id }}" title="Delete {{ $wm->admin_name ?: $wm->name }}?"
                    message="All weight tiers for this method are removed."
                    confirm="Delete method" :action="route('admin.weight-based-methods.destroy', $wm)" method="DELETE" />
            @endforeach
        @endif
    </div>
</x-admin.shell>

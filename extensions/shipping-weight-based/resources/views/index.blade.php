<x-admin3.shell title="Weight Based">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Weight Based</span>
            </div>
            <h1 class="v3-head__title">Weight Based</h1>
            <p class="v3-head__standing">Methods priced by cart weight. One save commits every method below.</p>
        </div>
        <div class="v3-head__actions">
            <x-admin3.extension-switch name="shipping-weight-based" />
            <a href="{{ route('admin.weight-based-methods.create') }}" class="v3-btn v3-btn--primary"><x-admin3.icon name="plus" /> Add method</a>
        </div>
    </div>

    <div class="v3-settle">
        @if ($weightMethods->isEmpty())
            <div class="v3-empty">
                <p class="v3-empty__title">No weight-based methods</p>
                <p class="v3-empty__line">Create tiers priced by cart weight.</p>
            </div>
        @else
            <x-admin3.form :action="route('admin.weight-based-methods.bulk-update')" method="PUT">
                @foreach ($weightMethods as $wm)
                    <div class="v3-dragcard" x-data="{
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
                        <div class="v3-dragrow v3-dragrow--bare">
                            <span class="v3-dir__body">
                                <span class="v3-dir__name">{{ $wm->admin_name ?: $wm->name }}</span>
                                <span class="v3-dir__line v3-block">
                                    {{ $wm->geoZones->isNotEmpty() ? $wm->geoZones->pluck('name')->implode(', ') : 'All zones' }}
                                    · {{ $wm->customerGroups->isNotEmpty() ? $wm->customerGroups->pluck('name')->implode(', ') : 'All groups' }}
                                    · {{ $wm->rates_count }} {{ Str::plural('tier', $wm->rates_count) }}
                                </span>
                            </span>
                            <span class="v3-dragrow__end">
                                <span class="v3-badge {{ $wm->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $wm->is_active ? 'Active' : 'Inactive' }}</span>
                                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="open = !open"
                                        :aria-expanded="open ? 'true' : 'false'" x-text="open ? 'Close' : 'Edit'">Edit</button>
                            </span>
                        </div>

                        <div x-show="open" x-cloak class="v3-dragcard__body">
                            <div class="v3-grid v3-grid--3">
                                <x-admin3.field :name="'methods.' . $wm->id . '.admin_name'" label="Admin name" hint="Never shown to customers.">
                                    <input type="text" name="methods[{{ $wm->id }}][admin_name]" value="{{ $wm->admin_name }}" placeholder="Internal reference" class="v3-control">
                                </x-admin3.field>
                                <x-admin3.field :name="'methods.' . $wm->id . '.name'" label="Checkout name" required>
                                    <input type="text" name="methods[{{ $wm->id }}][name]" value="{{ $wm->name }}" required class="v3-control">
                                </x-admin3.field>
                                <div class="v3-actrow">
                                    <x-admin3.field :name="'methods.' . $wm->id . '.sort_order'" label="Sort">
                                        <input type="number" name="methods[{{ $wm->id }}][sort_order]" value="{{ $wm->sort_order }}" class="v3-control v3-mono v3-w-sort">
                                    </x-admin3.field>
                                    <label class="v3-toggle">
                                        <input type="hidden" name="methods[{{ $wm->id }}][is_active]" value="0">
                                        <input type="checkbox" name="methods[{{ $wm->id }}][is_active]" value="1" @checked($wm->is_active)>
                                        <span class="v3-toggle__track" aria-hidden="true"></span>
                                        Active
                                    </label>
                                </div>
                            </div>

                            <div class="v3-grid v3-grid--2 v3-gap">
                                <x-admin3.field :name="'methods.' . $wm->id . '.geo_zone_ids'" label="Geo zones" hint="None checked quotes for every zone.">
                                    <div class="v3-checklist">
                                        @foreach ($geoZones as $zone)
                                            <label class="v3-check">
                                                <input type="checkbox" name="methods[{{ $wm->id }}][geo_zone_ids][]" value="{{ $zone->id }}"
                                                       @checked($wm->geoZones->contains('id', $zone->id))>
                                                {{ $zone->name }}
                                            </label>
                                        @endforeach
                                        @if ($geoZones->isEmpty())
                                            <p class="v3-cell-quiet">No geo zones</p>
                                        @endif
                                    </div>
                                </x-admin3.field>
                                <x-admin3.field :name="'methods.' . $wm->id . '.customer_group_ids'" label="Customer groups" hint="None checked quotes for every customer.">
                                    <div class="v3-checklist">
                                        @foreach ($customerGroups as $group)
                                            <label class="v3-check">
                                                <input type="checkbox" name="methods[{{ $wm->id }}][customer_group_ids][]" value="{{ $group->id }}"
                                                       @checked($wm->customerGroups->contains('id', $group->id))>
                                                {{ $group->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </x-admin3.field>
                            </div>

                            <div class="v3-gap">
                                <div class="v3-actrow v3-gap-sm">
                                    <span class="v3-label">Weight tiers</span>
                                    <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm" @click="addRate(); touch()">
                                        <x-admin3.icon name="plus" /> Add tier
                                    </button>
                                </div>
                                <template x-if="rates.length > 0">
                                    <div>
                                        <template x-for="(rate, i) in rates" :key="i">
                                            <div class="v3-row">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_from]'" x-model="rate.weight_from" step="0.001" min="0" aria-label="Weight from" class="v3-control v3-mono">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][weight_to]'" x-model="rate.weight_to" step="0.001" min="0" aria-label="Weight to" class="v3-control v3-mono">
                                                <input type="number" :name="'methods[{{ $wm->id }}][rates]['+i+'][cost]'" x-model="rate.cost" step="0.01" min="0" aria-label="Cost" class="v3-control v3-mono">
                                                <button type="button" class="v3-row__x" @click="rates.splice(i, 1); touch()" aria-label="Remove tier"><x-admin3.icon name="x" /></button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="rates.length === 0">
                                    <p class="v3-cell-quiet">No tiers yet.</p>
                                </template>
                            </div>

                            <div class="v3-gap v3-actrow">
                                <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-wm-{{ $wm->id }}')">Delete method</button>
                                <button type="button" class="v3-btn v3-btn--ghost v3-btn--sm" @click="document.getElementById('duplicate-wm-{{ $wm->id }}').submit()">Duplicate</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </x-admin3.form>

            {{-- Delete and duplicate cannot live inside the bulk form: forms do not nest. --}}
            @foreach ($weightMethods as $wm)
                <form id="duplicate-wm-{{ $wm->id }}" method="POST" action="{{ route('admin.weight-based-methods.duplicate', $wm) }}" hidden>
                    @csrf
                </form>
                <x-admin3.confirm name="delete-wm-{{ $wm->id }}" title="Delete {{ $wm->admin_name ?: $wm->name }}?"
                    message="All weight tiers for this method are removed."
                    confirm="Delete method" :action="route('admin.weight-based-methods.destroy', $wm)" method="DELETE" />
            @endforeach
        @endif
    </div>
</x-admin3.shell>

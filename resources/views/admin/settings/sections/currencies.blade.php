@php $defaultCurrency = $currencies->firstWhere('is_default', true); @endphp
<x-admin.settings-shell title="Currencies" :wide="true">
    <div class="space-y-6">
        <x-admin.card title="Currencies">
            <x-slot name="headerActions">
                <x-admin.hint label="How rates are read" class="v-hint--end">Every rate is relative to the default currency, which sits at 1.</x-admin.hint>
                <form method="POST" action="{{ route('admin.theme.currencies.fetch-rates') }}" data-v-form>
                    @csrf
                    <x-admin.button variant="secondary" type="submit" size="sm">Fetch latest rates</x-admin.button>
                </form>
            </x-slot>

            {{--
                A <form> cannot legally wrap table rows/cells (invalid HTML: form is not a
                permitted child of <tr>). Each row's edit form is instead declared as an
                empty, id'd <form> and every input/button inside the row references it via
                the HTML5 form="" attribute — keeps the table structure valid while each
                row still submits independently.
            --}}
            @foreach ($currencies as $cur)
                <form id="cur-update-{{ $cur->id }}" method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" data-v-form class="hidden">
                    @csrf @method('PUT')
                </form>
            @endforeach

            <x-admin.table>
                <x-slot name="head">
                    <x-admin.table.th>Code</x-admin.table.th>
                    <x-admin.table.th>Name</x-admin.table.th>
                    <x-admin.table.th>Symbol</x-admin.table.th>
                    <x-admin.table.th>Rate</x-admin.table.th>
                    <x-admin.table.th>Status</x-admin.table.th>
                    <x-admin.table.th align="right">Actions</x-admin.table.th>
                </x-slot>

                @foreach ($currencies as $cur)
                    @php $editForm = 'cur-update-'.$cur->id; @endphp
                    <x-admin.table.row class="{{ $cur->is_enabled ? '' : 'opacity-60' }}">
                        <x-admin.table.cell mono>{{ $cur->code }}</x-admin.table.cell>
                        <x-admin.table.cell>
                            {{-- Preserve enabled state: the controller reads is_enabled on every update. --}}
                            <input type="hidden" form="{{ $editForm }}" name="is_enabled" value="{{ $cur->is_enabled ? 1 : 0 }}">
                            <input type="text" form="{{ $editForm }}" name="name" value="{{ $cur->name }}" required maxlength="255" placeholder="Name"
                                class="v-control w-full h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <input type="text" form="{{ $editForm }}" name="symbol" value="{{ $cur->symbol }}" required maxlength="10" placeholder="₱"
                                class="v-control w-16 h-[var(--control-h-sm)] px-2 text-center text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            <input type="number" form="{{ $editForm }}" name="rate" value="{{ $cur->rate }}" step="0.000001" min="0.000001" required
                                @if ($cur->is_default) readonly @endif
                                title="{{ $cur->is_default ? 'Base currency: its rate is always 1.' : 'Units of '.$cur->code.' per 1 '.($defaultCurrency?->code ?? 'base') }}"
                                class="v-control w-32 h-[var(--control-h-sm)] px-2 text-[13px] border border-v-border rounded-v-md {{ $cur->is_default ? 'bg-v-sunken text-v-muted cursor-not-allowed' : 'bg-v-surface text-v-text' }}">
                        </x-admin.table.cell>
                        <x-admin.table.cell>
                            @if ($cur->is_default)
                                <x-admin.badge tone="success">Default</x-admin.badge>
                            @else
                                <x-admin.badge :tone="$cur->is_enabled ? 'success' : 'neutral'">{{ $cur->is_enabled ? 'Enabled' : 'Disabled' }}</x-admin.badge>
                            @endif
                        </x-admin.table.cell>
                        <x-admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-3 text-[13px]">
                                <x-admin.button variant="secondary" size="sm" type="submit" form="{{ $editForm }}">Save</x-admin.button>
                                @unless ($cur->is_default)
                                    <form method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" data-v-form>
                                        @csrf @method('PUT')
                                        <input type="hidden" name="name" value="{{ $cur->name }}">
                                        <input type="hidden" name="symbol" value="{{ $cur->symbol }}">
                                        <input type="hidden" name="rate" value="{{ $cur->rate }}">
                                        <input type="hidden" name="is_enabled" value="{{ $cur->is_enabled ? 0 : 1 }}">
                                        <button type="submit" class="{{ $cur->is_enabled ? 'text-v-warning hover:underline' : 'text-v-accent hover:underline' }}">
                                            {{ $cur->is_enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" data-v-form>
                                        @csrf @method('PUT')
                                        <input type="hidden" name="name" value="{{ $cur->name }}">
                                        <input type="hidden" name="symbol" value="{{ $cur->symbol }}">
                                        <input type="hidden" name="rate" value="{{ $cur->rate }}">
                                        <input type="hidden" name="is_default" value="1">
                                        <input type="hidden" name="is_enabled" value="1">
                                        <button type="submit" class="text-v-accent hover:underline">Set default</button>
                                    </form>
                                    <button type="button" class="text-v-danger hover:underline"
                                            @click="$dispatch('open-modal', 'delete-currency-{{ $cur->id }}')">Delete</button>
                                @endunless
                            </div>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach
            </x-admin.table>
        </x-admin.card>

        @foreach ($currencies as $cur)
            @unless ($cur->is_default)
                <x-admin.confirm-dialog name="delete-currency-{{ $cur->id }}" title="Delete {{ $cur->code }}?"
                    message="{{ $cur->name }} is removed from the storefront switcher and from checkout. Prices already stored in it are unaffected."
                    confirm="Delete" :action="route('admin.theme.currencies.destroy', $cur)" method="DELETE" />
            @endunless
        @endforeach

        <x-admin.card title="Add currency">
            <form method="POST" action="{{ route('admin.theme.currencies.store') }}" data-v-form class="grid grid-cols-2 gap-4 sm:grid-cols-5 sm:items-end">
                @csrf
                <x-admin.field name="code" label="Code" required>
                    <x-admin.input name="code" maxlength="3" required class="uppercase" placeholder="USD" />
                </x-admin.field>
                <x-admin.field name="name" label="Name" required>
                    <x-admin.input name="name" required placeholder="US Dollar" />
                </x-admin.field>
                <x-admin.field name="symbol" label="Symbol" required>
                    <x-admin.input name="symbol" maxlength="10" required placeholder="$" />
                </x-admin.field>
                <x-admin.field name="rate" label="Rate" required>
                    <x-admin.input name="rate" type="number" step="0.000001" required placeholder="0.018" />
                </x-admin.field>
                <x-admin.button variant="primary" type="submit">Add</x-admin.button>
            </form>
        </x-admin.card>
    </div>
</x-admin.settings-shell>

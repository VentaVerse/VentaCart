<x-admin.settings-shell title="Status Placement" active="fulfillment">
    @php
        // The select's own vocabulary, built once: the empty value is a real
        // choice (not placed), and its LABEL carries the one consequence that
        // is not obvious from the control - an unplaced status's orders still
        // show up, in the All tab. No help card, no paragraph: the option is
        // named for what it does (owner's UI copy rule, 2026-08-09).
        $options = ['' => 'Not placed (All tab only)'] + $placementNames;
    @endphp

    <form method="POST" action="{{ route('admin.fulfillment.settings.placement.update') }}" data-v-form class="space-y-6">
        @csrf

        @if ($unplaced->isNotEmpty())
            <x-admin.card title="Not placed">
                @if ($unplaced->contains(fn ($row) => $row['suggestion'] !== null))
                    <x-slot:headerActions>
                        {{-- Bare x-data: Alpine only binds handlers inside a
                             component scope, and this button needs no state of
                             its own. Fills the empty selects from each row's
                             suggested placement without saving anything. --}}
                        <x-admin.button type="button" variant="secondary" size="sm" x-data
                            x-on:click="$el.closest('form').querySelectorAll('select[data-suggested]').forEach(s => { if (!s.value) s.value = s.dataset.suggested })">
                            Suggest placements
                        </x-admin.button>
                    </x-slot:headerActions>
                @endif

                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Order status</x-admin.table.th>
                        <x-admin.table.th>Placement</x-admin.table.th>
                        <x-admin.table.th align="right">Orders</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($unplaced as $row)
                        <x-admin.table.row>
                            <x-admin.table.cell>{{ $row['name'] }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <select name="placements[{{ $row['id'] }}]"
                                    @if ($row['suggestion']) data-suggested="{{ $row['suggestion'] }}" @endif
                                    aria-label="Placement for {{ $row['name'] }}"
                                    class="v-control w-full max-w-xs h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    @foreach ($options as $value => $label)
                                        <option value="{{ $value }}" @selected($value === '')>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>{{ number_format($row['orders']) }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            </x-admin.card>
        @endif

        <x-admin.card title="Placed">
            @if ($placed->isEmpty())
                <p class="text-[13px] text-v-secondary">No order status is placed yet.</p>
            @else
                <x-admin.table>
                    <x-slot:head>
                        <x-admin.table.th>Order status</x-admin.table.th>
                        <x-admin.table.th>Placement</x-admin.table.th>
                        <x-admin.table.th align="right">Orders</x-admin.table.th>
                    </x-slot:head>
                    @foreach ($placed as $row)
                        <x-admin.table.row>
                            <x-admin.table.cell>{{ $row['name'] }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <select name="placements[{{ $row['id'] }}]"
                                    aria-label="Placement for {{ $row['name'] }}"
                                    class="v-control w-full max-w-xs h-[var(--control-h-sm)] px-2 text-[13px] bg-v-surface text-v-text border border-v-border rounded-v-md">
                                    @foreach ($options as $value => $label)
                                        <option value="{{ $value }}" @selected($value === $row['placement'])>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </x-admin.table.cell>
                            <x-admin.table.cell align="right" mono>{{ number_format($row['orders']) }}</x-admin.table.cell>
                        </x-admin.table.row>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>

        <x-admin.form-actions>
            <x-admin.button type="submit">Save</x-admin.button>
        </x-admin.form-actions>
    </form>
</x-admin.settings-shell>

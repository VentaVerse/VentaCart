<x-courier-fulfillment::space title="Status Mapping" active="status-mapping">
    @php
        // The select's own vocabulary, built once: the empty value is a real
        // choice (not placed), and its LABEL carries the one consequence that
        // is not obvious from the control - an unplaced status's orders still
        // show up, in the All tab. No help card, no paragraph: the option is
        // named for what it does (owner's UI copy rule, 2026-08-09).
        $options = ['' => 'Not placed (All tab only)'] + $placementNames;
    @endphp

    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.settings') }}">Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>Status Mapping</span>
            </div>
            <h1 class="v-head__title">Status Mapping</h1>
        </div>
    </div>


    <x-admin.form :action="route('admin.fulfillment.settings.placement.update')" class="v-settle">

        @if ($unplaced->isNotEmpty())
            <x-admin.panel title="Not placed" flush>
                @if ($unplaced->contains(fn ($row) => $row['suggestion'] !== null))
                    <x-slot:acts>
                        {{-- Fills the empty selects from each row's suggested
                             placement without saving anything. --}}
                        <button type="button" class="v-btn v-btn--secondary v-btn--sm"
                            @click="$el.closest('form').querySelectorAll('select[data-suggested]').forEach(s => { if (!s.value) { s.value = s.dataset.suggested; s.dispatchEvent(new Event('change', { bubbles: true })); } })">
                            Suggest placements
                        </button>
                    </x-slot:acts>
                @endif

                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Order status</th><th>Placement</th><th class="v-cell-num">Orders</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($unplaced as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        <select name="placements[{{ $row['id'] }}]"
                                            @if ($row['suggestion']) data-suggested="{{ $row['suggestion'] }}" @endif
                                            aria-label="Placement for {{ $row['name'] }}"
                                            class="v-control v-w-name">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected($value === '')>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="v-cell-num v-mono">{{ number_format($row['orders']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
        @endif

        <x-admin.panel title="Placed" flush>
            @if ($placed->isEmpty())
                <div class="v-empty"><p class="v-empty__title">No order status is placed yet</p></div>
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Order status</th><th>Placement</th><th class="v-cell-num">Orders</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($placed as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        <select name="placements[{{ $row['id'] }}]"
                                            aria-label="Placement for {{ $row['name'] }}"
                                            class="v-control v-w-name">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected($value === $row['placement'])>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="v-cell-num v-mono">{{ number_format($row['orders']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

    </x-admin.form>
</x-courier-fulfillment::space>

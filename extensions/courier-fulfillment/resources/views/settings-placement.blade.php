<x-admin3.shell title="Status Placement">
    @php
        // The select's own vocabulary, built once: the empty value is a real
        // choice (not placed), and its LABEL carries the one consequence that
        // is not obvious from the control - an unplaced status's orders still
        // show up, in the All tab. No help card, no paragraph: the option is
        // named for what it does (owner's UI copy rule, 2026-08-09).
        $options = ['' => 'Not placed (All tab only)'] + $placementNames;
    @endphp

    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.fulfillment.settings') }}">Courier Fulfillment</a>
                <span aria-hidden="true">/</span>
                <span>Status placement</span>
            </div>
            <h1 class="v3-head__title">Status placement</h1>
        </div>
    </div>

    <x-admin3.form :action="route('admin.fulfillment.settings.placement.update')" class="v3-settle">

        @if ($unplaced->isNotEmpty())
            <x-admin3.panel title="Not placed" flush>
                @if ($unplaced->contains(fn ($row) => $row['suggestion'] !== null))
                    <x-slot:acts>
                        {{-- Fills the empty selects from each row's suggested
                             placement without saving anything. --}}
                        <button type="button" class="v3-btn v3-btn--secondary v3-btn--sm"
                            @click="$el.closest('form').querySelectorAll('select[data-suggested]').forEach(s => { if (!s.value) { s.value = s.dataset.suggested; s.dispatchEvent(new Event('change', { bubbles: true })); } })">
                            Suggest placements
                        </button>
                    </x-slot:acts>
                @endif

                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Order status</th><th>Placement</th><th class="v3-cell-num">Orders</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($unplaced as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        <select name="placements[{{ $row['id'] }}]"
                                            @if ($row['suggestion']) data-suggested="{{ $row['suggestion'] }}" @endif
                                            aria-label="Placement for {{ $row['name'] }}"
                                            class="v3-control v3-w-name">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected($value === '')>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="v3-cell-num v3-mono">{{ number_format($row['orders']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>
        @endif

        <x-admin3.panel title="Placed" flush>
            @if ($placed->isEmpty())
                <div class="v3-empty"><p class="v3-empty__title">No order status is placed yet</p></div>
            @else
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Order status</th><th>Placement</th><th class="v3-cell-num">Orders</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($placed as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        <select name="placements[{{ $row['id'] }}]"
                                            aria-label="Placement for {{ $row['name'] }}"
                                            class="v3-control v3-w-name">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected($value === $row['placement'])>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="v3-cell-num v3-mono">{{ number_format($row['orders']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin3.panel>

    </x-admin3.form>
</x-admin3.shell>

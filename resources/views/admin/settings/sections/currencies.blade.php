@php $defaultCurrency = $currencies->firstWhere('is_default', true); @endphp
<x-admin.settings-shell title="Currencies" active="currencies">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Currencies</h1>
            <p class="v-head__standing">Every rate is read against {{ $defaultCurrency?->code ?? 'the default' }}, which sits at 1.</p>
        </div>
        <div class="v-head__actions">
            <form method="POST" action="{{ route('admin.theme.currencies.fetch-rates') }}">
                @csrf
                <button type="submit" class="v-btn v-btn--secondary">Fetch latest rates</button>
            </form>
        </div>
    </div>

    <div class="v-settle">
        {{-- Always-editable rows are ONE save scope (the doctrine). Set
             default stays its own action because it recalculates every
             rate; deletes stay record actions. --}}
        <x-admin.form :action="route('admin.theme.currencies.update-all')" method="PUT">
            <x-admin.panel title="Currencies" flush>
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Rate</th>
                                <th>Enabled</th>
                                <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($currencies as $cur)
                                <tr class="{{ $cur->is_enabled ? '' : 'v-row-dim' }}">
                                    <td class="v-mono">
                                        {{ $cur->code }}
                                        @if ($cur->is_default)<span class="v-badge v-badge--good">Default</span>@endif
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $cur->id }}][name]" value="{{ old('rows.'.$cur->id.'.name', $cur->name) }}" required maxlength="255"
                                               aria-label="{{ $cur->code }} name" class="v-control">
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $cur->id }}][symbol]" value="{{ old('rows.'.$cur->id.'.symbol', $cur->symbol) }}" required maxlength="10"
                                               aria-label="{{ $cur->code }} symbol" class="v-control v-w-sym">
                                    </td>
                                    <td>
                                        <input type="number" name="rows[{{ $cur->id }}][rate]" value="{{ old('rows.'.$cur->id.'.rate', $cur->rate) }}" step="0.000001" min="0.000001" required
                                               @if ($cur->is_default) readonly @endif
                                               title="{{ $cur->is_default ? 'Base currency: its rate is always 1.' : 'Units of '.$cur->code.' per 1 base unit' }}"
                                               aria-label="{{ $cur->code }} rate" class="v-control v-mono v-w-rate">
                                    </td>
                                    <td>
                                        <label class="v-toggle">
                                            <input type="hidden" name="rows[{{ $cur->id }}][is_enabled]" value="0">
                                            <input type="checkbox" name="rows[{{ $cur->id }}][is_enabled]" value="1"
                                                   @checked(old('rows.'.$cur->id.'.is_enabled', $cur->is_enabled)) @disabled($cur->is_default)
                                                   aria-label="{{ $cur->code }} enabled">
                                            <span class="v-toggle__track" aria-hidden="true"></span>
                                            @if ($cur->is_default)<input type="hidden" name="rows[{{ $cur->id }}][is_enabled]" value="1">@endif
                                        </label>
                                    </td>
                                    <td class="v-cell-act">
                                        @unless ($cur->is_default)
                                            <button type="submit" form="cur-default-{{ $cur->id }}" class="v-btn v-btn--ghost v-btn--sm">Set default</button>
                                            <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-currency-{{ $cur->id }}')">Delete</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.panel>
        </x-admin.form>

        @foreach ($currencies as $cur)
            @unless ($cur->is_default)
                <form id="cur-default-{{ $cur->id }}" method="POST" action="{{ route('admin.theme.currencies.update', $cur) }}" hidden>
                    @csrf @method('PUT')
                    <input type="hidden" name="name" value="{{ $cur->name }}">
                    <input type="hidden" name="symbol" value="{{ $cur->symbol }}">
                    <input type="hidden" name="rate" value="{{ $cur->rate }}">
                    <input type="hidden" name="is_default" value="1">
                    <input type="hidden" name="is_enabled" value="1">
                </form>
                <x-admin.confirm name="delete-currency-{{ $cur->id }}" title="Delete {{ $cur->code }}?"
                    message="{{ $cur->name }} is removed from the storefront switcher and from checkout. Prices already stored in it are unaffected."
                    confirm="Delete" :action="route('admin.theme.currencies.destroy', $cur)" method="DELETE" />
            @endunless
        @endforeach

        <x-admin.panel title="Add currency">
            <form method="POST" action="{{ route('admin.theme.currencies.store') }}" class="v-addrow">
                @csrf
                <x-admin.field name="code" label="Code" required>
                    <input type="text" name="code" maxlength="3" required placeholder="USD" class="v-control v-mono v-upper">
                </x-admin.field>
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required placeholder="US Dollar" class="v-control">
                </x-admin.field>
                <x-admin.field name="symbol" label="Symbol" required>
                    <input type="text" name="symbol" maxlength="10" required placeholder="$" class="v-control">
                </x-admin.field>
                <x-admin.field name="rate" label="Rate" required>
                    <input type="number" name="rate" step="0.000001" required placeholder="0.018" class="v-control v-mono">
                </x-admin.field>
                <button type="submit" class="v-btn v-btn--primary">Add currency</button>
            </form>
        </x-admin.panel>
    </div>
</x-admin.settings-shell>

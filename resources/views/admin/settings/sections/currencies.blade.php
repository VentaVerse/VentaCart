@php $defaultCurrency = $currencies->firstWhere('is_default', true); @endphp
<x-admin3.settings-shell title="Currencies" active="currencies">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Currencies</h1>
            <p class="v3-head__standing">Every rate is read against {{ $defaultCurrency?->code ?? 'the default' }}, which sits at 1.</p>
        </div>
        <div class="v3-head__actions">
            <form method="POST" action="{{ route('admin.theme.currencies.fetch-rates') }}">
                @csrf
                <button type="submit" class="v3-btn v3-btn--secondary">Fetch latest rates</button>
            </form>
        </div>
    </div>

    <div class="v3-settle">
        {{-- Always-editable rows are ONE save scope (the doctrine). Set
             default stays its own action because it recalculates every
             rate; deletes stay record actions. --}}
        <x-admin3.form :action="route('admin.theme.currencies.update-all')" method="PUT">
            <x-admin3.panel title="Currencies" flush>
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Rate</th>
                                <th>Enabled</th>
                                <th class="v3-cell-act"><span class="v3-sr">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($currencies as $cur)
                                <tr class="{{ $cur->is_enabled ? '' : 'v3-row-dim' }}">
                                    <td class="v3-mono">
                                        {{ $cur->code }}
                                        @if ($cur->is_default)<span class="v3-badge v3-badge--good">Default</span>@endif
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $cur->id }}][name]" value="{{ old('rows.'.$cur->id.'.name', $cur->name) }}" required maxlength="255"
                                               aria-label="{{ $cur->code }} name" class="v3-control">
                                    </td>
                                    <td>
                                        <input type="text" name="rows[{{ $cur->id }}][symbol]" value="{{ old('rows.'.$cur->id.'.symbol', $cur->symbol) }}" required maxlength="10"
                                               aria-label="{{ $cur->code }} symbol" class="v3-control v3-w-sym">
                                    </td>
                                    <td>
                                        <input type="number" name="rows[{{ $cur->id }}][rate]" value="{{ old('rows.'.$cur->id.'.rate', $cur->rate) }}" step="0.000001" min="0.000001" required
                                               @if ($cur->is_default) readonly @endif
                                               title="{{ $cur->is_default ? 'Base currency: its rate is always 1.' : 'Units of '.$cur->code.' per 1 base unit' }}"
                                               aria-label="{{ $cur->code }} rate" class="v3-control v3-mono v3-w-rate">
                                    </td>
                                    <td>
                                        <label class="v3-check">
                                            <input type="hidden" name="rows[{{ $cur->id }}][is_enabled]" value="0">
                                            <input type="checkbox" name="rows[{{ $cur->id }}][is_enabled]" value="1"
                                                   @checked(old('rows.'.$cur->id.'.is_enabled', $cur->is_enabled)) @disabled($cur->is_default)
                                                   aria-label="{{ $cur->code }} enabled">
                                            @if ($cur->is_default)<input type="hidden" name="rows[{{ $cur->id }}][is_enabled]" value="1">@endif
                                        </label>
                                    </td>
                                    <td class="v3-cell-act">
                                        @unless ($cur->is_default)
                                            <button type="submit" form="cur-default-{{ $cur->id }}" class="v3-btn v3-btn--ghost v3-btn--sm">Set default</button>
                                            <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-currency-{{ $cur->id }}')">Delete</button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin3.panel>
        </x-admin3.form>

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
                <x-admin3.confirm name="delete-currency-{{ $cur->id }}" title="Delete {{ $cur->code }}?"
                    message="{{ $cur->name }} is removed from the storefront switcher and from checkout. Prices already stored in it are unaffected."
                    confirm="Delete" :action="route('admin.theme.currencies.destroy', $cur)" method="DELETE" />
            @endunless
        @endforeach

        <x-admin3.panel title="Add currency">
            <form method="POST" action="{{ route('admin.theme.currencies.store') }}" class="v3-addrow">
                @csrf
                <x-admin3.field name="code" label="Code" required>
                    <input type="text" name="code" maxlength="3" required placeholder="USD" class="v3-control v3-mono v3-upper">
                </x-admin3.field>
                <x-admin3.field name="name" label="Name" required>
                    <input type="text" name="name" required placeholder="US Dollar" class="v3-control">
                </x-admin3.field>
                <x-admin3.field name="symbol" label="Symbol" required>
                    <input type="text" name="symbol" maxlength="10" required placeholder="$" class="v3-control">
                </x-admin3.field>
                <x-admin3.field name="rate" label="Rate" required>
                    <input type="number" name="rate" step="0.000001" required placeholder="0.018" class="v3-control v3-mono">
                </x-admin3.field>
                <button type="submit" class="v3-btn v3-btn--primary">Add currency</button>
            </form>
        </x-admin3.panel>
    </div>
</x-admin3.settings-shell>

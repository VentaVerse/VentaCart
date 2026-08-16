<x-admin3.settings-shell title="Free Shipping" active="shipping">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Free Shipping</span>
            </div>
            <h1 class="v3-head__title">Free Shipping</h1>
            <p class="v3-head__standing">The first matching rule applies; the ones below it are not evaluated.</p>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.free-shipping-rules.create') }}" class="v3-btn v3-btn--primary"><x-admin3.icon name="plus" /> Add rule</a>
        </div>
    </div>

    <div class="v3-settle">
        <x-admin3.panel title="Rules" flush>
            @if ($rules->isEmpty())
                <div class="v3-empty">
                    <p class="v3-empty__title">No free shipping rules</p>
                    <p class="v3-empty__line">Create a rule to waive shipping automatically when its conditions are met.</p>
                </div>
            @else
                <div class="v3-tablewrap">
                    <table class="v3-table">
                        <thead>
                            <tr><th>Rule</th><th>Conditions</th><th>Order</th><th>Active</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td><a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="v3-cell-link">{{ $rule->name }}</a></td>
                                    <td class="v3-cell-quiet">{{ $rule->getConditionsSummary() }}</td>
                                    <td class="v3-mono">{{ $rule->sort_order }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}">
                                            @csrf
                                            <button type="submit" class="v3-toggle v3-toggle--btn" role="switch"
                                                    aria-checked="{{ $rule->is_active ? 'true' : 'false' }}"
                                                    aria-label="{{ $rule->name }} active">
                                                <span class="v3-toggle__track" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="v3-cell-act">
                                        <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                        <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-fsr-{{ $rule->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin3.panel>

        @foreach ($rules as $rule)
            <x-admin3.confirm name="delete-fsr-{{ $rule->id }}" title="Delete {{ $rule->name }}?"
                message="The rule stops applying immediately."
                confirm="Delete" :action="route('admin.free-shipping-rules.destroy', $rule)" method="DELETE" />
        @endforeach
    </div>
</x-admin3.settings-shell>

<x-admin.shell title="Free Shipping">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.shipping-settings.index') }}">Shipping</a>
                <span aria-hidden="true">/</span>
                <span>Free Shipping</span>
            </div>
            <h1 class="v-head__title">Free Shipping</h1>
            <p class="v-head__standing">The first matching rule applies; the ones below it are not evaluated.</p>
        </div>
        <div class="v-head__actions">
            <x-admin.extension-switch name="shipping-free" />
            <a href="{{ route('admin.free-shipping-rules.create') }}" class="v-btn v-btn--primary"><x-admin.icon name="plus" /> Add rule</a>
        </div>
    </div>

    <div class="v-settle">
        <x-admin.panel title="Rules" flush>
            @if ($freeShippingRules->isEmpty())
                <div class="v-empty">
                    <p class="v-empty__title">No free shipping rules</p>
                    <p class="v-empty__line">Create a rule to waive shipping automatically when its conditions are met.</p>
                </div>
            @else
                <div class="v-tablewrap">
                    <table class="v-table">
                        <thead>
                            <tr><th>Rule</th><th>Conditions</th><th>Order</th><th>Active</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($freeShippingRules as $rule)
                                <tr>
                                    <td><a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="v-cell-link">{{ $rule->name }}</a></td>
                                    <td class="v-cell-quiet">{{ $rule->getConditionsSummary() }}</td>
                                    <td class="v-mono">{{ $rule->sort_order }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('admin.free-shipping-rules.toggle', $rule) }}">
                                            @csrf
                                            <button type="submit" class="v-toggle v-toggle--btn" role="switch"
                                                    aria-checked="{{ $rule->is_active ? 'true' : 'false' }}"
                                                    aria-label="{{ $rule->name }} active">
                                                <span class="v-toggle__track" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="v-cell-act">
                                        <a href="{{ route('admin.free-shipping-rules.edit', $rule) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                        <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-fsr-{{ $rule->id }}')">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.panel>

        @foreach ($freeShippingRules as $rule)
            <x-admin.confirm name="delete-fsr-{{ $rule->id }}" title="Delete {{ $rule->name }}?"
                message="The rule stops applying immediately."
                confirm="Delete" :action="route('admin.free-shipping-rules.destroy', $rule)" method="DELETE" />
        @endforeach
    </div>
</x-admin.shell>

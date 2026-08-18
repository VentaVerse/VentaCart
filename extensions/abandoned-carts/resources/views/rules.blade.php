<x-abandoned-carts::space title="Recovery Rules" active="rules">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Rules</span>
            </div>
            <h1 class="v3-head__title">Abandoned Carts</h1>
        </div>
        <div class="v3-head__actions">
            <a href="{{ route('admin.abandoned-carts.rules.create') }}" class="v3-btn v3-btn--primary">Create rule</a>
        </div>
    </div>


    <div class="v3-settle">
        <x-admin3.panel flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
                    <thead>
                        <tr><th class="v3-cell-num">Priority</th><th>Template</th><th>Customer group</th><th class="v3-cell-num">Max sends</th><th class="v3-cell-num">Min age</th><th>Status</th><th class="v3-cell-act"><span class="v3-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td class="v3-cell-num v3-mono">{{ $rule->priority }}</td>
                                <td>{{ $rule->emailTemplate->name ?? '-' }}</td>
                                <td class="v3-cell-quiet">{{ $rule->customerGroup->name ?? 'All groups' }}</td>
                                <td class="v3-cell-num v3-mono">{{ $rule->max_sends }}</td>
                                <td class="v3-cell-num v3-mono">{{ $rule->min_cart_age_hours }}h</td>
                                <td><span class="v3-badge {{ $rule->is_active ? 'v3-badge--good' : 'v3-badge--quiet' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="v3-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.rules.edit', $rule) }}" class="v3-btn v3-btn--ghost v3-btn--sm">Edit</a>
                                    <button type="button" class="v3-btn v3-btn--danger v3-btn--sm" @click="$dispatch('v3-confirm', 'delete-rule-{{ $rule->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="v3-empty"><p class="v3-empty__title">No rules yet</p><p class="v3-empty__line">A rule decides which template a quiet cart receives, and when.</p><a href="{{ route('admin.abandoned-carts.rules.create') }}" class="v3-btn v3-btn--primary v3-btn--sm">Create the first rule</a></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin3.panel>

        @foreach ($rules as $rule)
            <x-admin3.confirm name="delete-rule-{{ $rule->id }}" title="Delete rule?"
                message="Delete this rule? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.rules.destroy', $rule)" method="DELETE" />
        @endforeach
    </div>
</x-abandoned-carts::space>

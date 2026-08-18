<x-abandoned-carts::space title="Recovery Rules" active="rules">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Abandoned Carts</span>
                <span aria-hidden="true">/</span>
                <span>Rules</span>
            </div>
            <h1 class="v-head__title">Abandoned Carts</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.abandoned-carts.rules.create') }}" class="v-btn v-btn--primary">Create rule</a>
        </div>
    </div>


    <div class="v-settle">
        <x-admin.panel flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr><th class="v-cell-num">Priority</th><th>Template</th><th>Customer group</th><th class="v-cell-num">Max sends</th><th class="v-cell-num">Min age</th><th>Status</th><th class="v-cell-act"><span class="v-sr">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr>
                                <td class="v-cell-num v-mono">{{ $rule->priority }}</td>
                                <td>{{ $rule->emailTemplate->name ?? '-' }}</td>
                                <td class="v-cell-quiet">{{ $rule->customerGroup->name ?? 'All groups' }}</td>
                                <td class="v-cell-num v-mono">{{ $rule->max_sends }}</td>
                                <td class="v-cell-num v-mono">{{ $rule->min_cart_age_hours }}h</td>
                                <td><span class="v-badge {{ $rule->is_active ? 'v-badge--good' : 'v-badge--quiet' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="v-cell-act">
                                    <a href="{{ route('admin.abandoned-carts.rules.edit', $rule) }}" class="v-btn v-btn--ghost v-btn--sm">Edit</a>
                                    <button type="button" class="v-btn v-btn--danger v-btn--sm" @click="$dispatch('v-confirm', 'delete-rule-{{ $rule->id }}')">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="v-empty"><p class="v-empty__title">No rules yet</p><p class="v-empty__line">A rule decides which template a quiet cart receives, and when.</p><a href="{{ route('admin.abandoned-carts.rules.create') }}" class="v-btn v-btn--primary v-btn--sm">Create the first rule</a></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        @foreach ($rules as $rule)
            <x-admin.confirm name="delete-rule-{{ $rule->id }}" title="Delete rule?"
                message="Delete this rule? This cannot be undone." confirm="Delete"
                :action="route('admin.abandoned-carts.rules.destroy', $rule)" method="DELETE" />
        @endforeach
    </div>
</x-abandoned-carts::space>

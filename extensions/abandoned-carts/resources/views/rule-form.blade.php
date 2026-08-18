<x-abandoned-carts::space :title="$rule ? 'Edit Rule' : 'Create Rule'" active="rules">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.abandoned-carts.rules') }}">Abandoned Carts</a>
                <span aria-hidden="true">/</span>
                <span>{{ $rule ? 'Edit rule' : 'New rule' }}</span>
            </div>
            <h1 class="v-head__title">{{ $rule ? 'Edit Rule' : 'Create Rule' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$rule ? route('admin.abandoned-carts.rules.update', $rule) : route('admin.abandoned-carts.rules.store')"
                   :method="$rule ? 'PUT' : 'POST'">
        <div class="v-settle">
            <x-admin.panel title="Rule">
                <x-admin.field name="email_template_id" label="Email template" required>
                    <select name="email_template_id" required class="v-control">
                        <option value="">Select template…</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('email_template_id', $rule?->email_template_id) == $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <div class="v-gap">
                    <x-admin.field name="customer_group_id" label="Customer group">
                        <select name="customer_group_id" class="v-control">
                            <option value="">All groups</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(old('customer_group_id', $rule?->customer_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </x-admin.field>
                </div>
                <div class="v-gap v-grid v-grid--3">
                    <x-admin.field name="max_sends" label="Max sends" required hint="Per cart, per rule.">
                        <input type="number" name="max_sends" min="1" max="100" required class="v-control" value="{{ old('max_sends', $rule?->max_sends ?? 1) }}">
                    </x-admin.field>
                    <x-admin.field name="min_cart_age_hours" label="Min cart age (hours)" required hint="How old a cart must be before the first send.">
                        <input type="number" name="min_cart_age_hours" min="1" max="8760" required class="v-control" value="{{ old('min_cart_age_hours', $rule?->min_cart_age_hours ?? 1) }}">
                    </x-admin.field>
                    <x-admin.field name="priority" label="Priority" required hint="Higher is checked first.">
                        <input type="number" name="priority" min="0" max="999" required class="v-control" value="{{ old('priority', $rule?->priority ?? 0) }}">
                    </x-admin.field>
                </div>
                <div class="v-gap">
                    <label class="v-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true))>
                        <span class="v-toggle__track" aria-hidden="true"></span>
                        <span>Active</span>
                    </label>
                </div>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-abandoned-carts::space>

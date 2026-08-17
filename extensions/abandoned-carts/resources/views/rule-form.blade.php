<x-admin3.shell :title="$rule ? 'Edit Rule' : 'Create Rule'">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.abandoned-carts.rules') }}">Abandoned Carts</a>
                <span aria-hidden="true">/</span>
                <span>{{ $rule ? 'Edit rule' : 'New rule' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $rule ? 'Edit Rule' : 'Create Rule' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$rule ? route('admin.abandoned-carts.rules.update', $rule) : route('admin.abandoned-carts.rules.store')"
                   :method="$rule ? 'PUT' : 'POST'">
        <div class="v3-settle">
            <x-admin3.panel title="Rule">
                <x-admin3.field name="email_template_id" label="Email template" required>
                    <select name="email_template_id" required class="v3-control">
                        <option value="">Select template…</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('email_template_id', $rule?->email_template_id) == $template->id)>{{ $template->name }}</option>
                        @endforeach
                    </select>
                </x-admin3.field>
                <div class="v3-gap">
                    <x-admin3.field name="customer_group_id" label="Customer group">
                        <select name="customer_group_id" class="v3-control">
                            <option value="">All groups</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" @selected(old('customer_group_id', $rule?->customer_group_id) == $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </x-admin3.field>
                </div>
                <div class="v3-gap v3-grid v3-grid--3">
                    <x-admin3.field name="max_sends" label="Max sends" required hint="Per cart, per rule.">
                        <input type="number" name="max_sends" min="1" max="100" required class="v3-control" value="{{ old('max_sends', $rule?->max_sends ?? 1) }}">
                    </x-admin3.field>
                    <x-admin3.field name="min_cart_age_hours" label="Min cart age (hours)" required hint="How old a cart must be before the first send.">
                        <input type="number" name="min_cart_age_hours" min="1" max="8760" required class="v3-control" value="{{ old('min_cart_age_hours', $rule?->min_cart_age_hours ?? 1) }}">
                    </x-admin3.field>
                    <x-admin3.field name="priority" label="Priority" required hint="Higher is checked first.">
                        <input type="number" name="priority" min="0" max="999" required class="v3-control" value="{{ old('priority', $rule?->priority ?? 0) }}">
                    </x-admin3.field>
                </div>
                <div class="v3-gap">
                    <label class="v3-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true))>
                        <span class="v3-toggle__track" aria-hidden="true"></span>
                        <span>Active</span>
                    </label>
                </div>
            </x-admin3.panel>
        </div>
    </x-admin3.form>
</x-admin3.shell>

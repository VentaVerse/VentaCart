<x-admin.shell :title="$rule ? 'Edit Rule' : 'Create Rule'">
    <x-admin.page :title="$rule ? 'Edit Rule' : 'Create Rule'">
        <x-slot:actions>
            <x-admin.button variant="ghost" href="{{ route('admin.abandoned-carts.rules') }}">Cancel</x-admin.button>
        </x-slot:actions>

        <div class="max-w-2xl">
            <x-admin.card>
                <form method="POST" action="{{ $rule ? route('admin.abandoned-carts.rules.update', $rule) : route('admin.abandoned-carts.rules.store') }}"
                    data-v-form class="space-y-6">
                    @csrf
                    @if ($rule) @method('PUT') @endif

                    <x-admin.field name="email_template_id" label="Email Template">
                        <x-admin.select name="email_template_id" required>
                            <option value="">Select template...</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" {{ old('email_template_id', $rule?->email_template_id) == $template->id ? 'selected' : '' }}>
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </x-admin.select>
                    </x-admin.field>

                    <x-admin.field name="customer_group_id" label="Customer Group">
                        <x-admin.select name="customer_group_id">
                            <option value="">All Groups</option>
                            @foreach ($customerGroups as $group)
                                <option value="{{ $group->id }}" {{ old('customer_group_id', $rule?->customer_group_id) == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </x-admin.select>
                    </x-admin.field>

                    <div class="grid grid-cols-3 gap-4">
                        <x-admin.field name="max_sends" label="Max Sends" help="Per cart, per rule">
                            <x-admin.input type="number" name="max_sends" min="1" max="100" :value="$rule?->max_sends ?? 1" required />
                        </x-admin.field>
                        <x-admin.field name="min_cart_age_hours" label="Min Cart Age (hours)" help="How old before first send">
                            <x-admin.input type="number" name="min_cart_age_hours" min="1" max="8760" :value="$rule?->min_cart_age_hours ?? 1" required />
                        </x-admin.field>
                        <x-admin.field name="priority" label="Priority" help="Higher = checked first">
                            <x-admin.input type="number" name="priority" min="0" max="999" :value="$rule?->priority ?? 0" required />
                        </x-admin.field>
                    </div>

                    <x-admin.checkbox name="is_active" label="Active" :checked="$rule?->is_active ?? true" />

                    <x-admin.form-actions>
                        <x-admin.button variant="secondary" href="{{ route('admin.abandoned-carts.rules') }}">Cancel</x-admin.button>
                        <x-admin.button type="submit">{{ $rule ? 'Update Rule' : 'Create Rule' }}</x-admin.button>
                    </x-admin.form-actions>
                </form>
            </x-admin.card>
        </div>
    </x-admin.page>
</x-admin.shell>

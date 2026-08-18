<x-admin3.shell :title="$group ? 'Edit Customer Group' : 'Create Customer Group'" active="customers">
    <div class="v3-head">
        <div class="v3-head__body">
            <div class="v3-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.customer-groups.index') }}">Groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name ?? 'New' }}</span>
            </div>
            <h1 class="v3-head__title">{{ $group ? $group->name : 'Create group' }}</h1>
        </div>
    </div>

    <x-admin3.form :action="$group ? route('admin.customer-groups.update', $group) : route('admin.customer-groups.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v3-settle">
        <input type="hidden" name="store_id" value="1">
        <x-admin3.panel title="Group">
            <x-admin3.field name="name" label="Name" required>
                <input type="text" name="name" required class="v3-control" value="{{ old('name', $group->name ?? '') }}">
            </x-admin3.field>
            <div class="v3-gap">
                <x-admin3.field name="description" label="Description">
                    <textarea name="description" rows="3" class="v3-control">{{ old('description', $group->description ?? '') }}</textarea>
                </x-admin3.field>
            </div>
            <div class="v3-gap">
                <label class="v3-toggle">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $group->is_default ?? false))>
                    <span class="v3-toggle__track" aria-hidden="true"></span>
                    <span>Default group for new customers</span>
                    <x-admin3.help text="Only one group can be the default. Setting this unsets the current one." />
                </label>
            </div>
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.shell>

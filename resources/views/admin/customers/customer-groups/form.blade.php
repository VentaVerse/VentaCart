<x-admin.shell :title="$group ? 'Edit Customer Group' : 'Create Customer Group'" active="customers">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.customers.index') }}">Customers</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('admin.customer-groups.index') }}">Groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $group ? $group->name : 'Create group' }}</h1>
        </div>
    </div>

    <x-admin.form :action="$group ? route('admin.customer-groups.update', $group) : route('admin.customer-groups.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v-settle">
        <input type="hidden" name="store_id" value="1">
        <x-admin.panel title="Group">
            <x-admin.field name="name" label="Name" required>
                <input type="text" name="name" required class="v-control" value="{{ old('name', $group->name ?? '') }}">
            </x-admin.field>
            <div class="v-gap">
                <x-admin.field name="description" label="Description">
                    <textarea name="description" rows="3" class="v-control">{{ old('description', $group->description ?? '') }}</textarea>
                </x-admin.field>
            </div>
            <div class="v-gap">
                <label class="v-toggle">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $group->is_default ?? false))>
                    <span class="v-toggle__track" aria-hidden="true"></span>
                    <span>Default group for new customers</span>
                    <x-admin.help text="Only one group can be the default. Setting this unsets the current one." />
                </label>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.shell>

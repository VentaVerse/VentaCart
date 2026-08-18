<x-admin.settings-shell :title="$group ? 'Edit user group' : 'Add user group'" active="user-groups">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.user-groups.index') }}">User Groups</a>
                <span aria-hidden="true">/</span>
                <span>{{ $group->name ?? 'New' }}</span>
            </div>
            <h1 class="v-head__title">{{ $group ? $group->name : 'Add user group' }}</h1>
        </div>
    </div>

    @php $locked = ($group?->slug ?? null) === 'super-admin'; @endphp

    <x-admin.form :action="$group ? route('admin.user-groups.update', $group) : route('admin.user-groups.store')"
                   :method="$group ? 'PUT' : 'POST'" class="v-settle">
        <x-admin.panel title="Group">
            <div class="v-grid v-grid--2">
                <x-admin.field name="name" label="Name" required>
                    <input type="text" name="name" required class="v-control" value="{{ old('name', $group->name ?? '') }}">
                </x-admin.field>
                <x-admin.field name="description" label="Description">
                    <input type="text" name="description" class="v-control" value="{{ old('description', $group->description ?? '') }}">
                </x-admin.field>
            </div>
        </x-admin.panel>

        <div class="v-gap">
            <x-admin.panel title="Permissions"
                :note="$locked ? 'Super Admin always holds every permission, including ones added later.' : null">
                <div class="v-permgrid">
                    @foreach ($permissionGroups as $groupKey => $perms)
                        <div class="v-permblock" x-data="{ mark(on) {
                                this.$root.querySelectorAll('input[type=checkbox]').forEach(i => {
                                    if (i.checked !== on) { i.checked = on; i.dispatchEvent(new Event('change', { bubbles: true })); }
                                });
                            } }">
                            <div class="v-permblock__head">
                                <span class="v-field__label">{{ ucfirst($groupKey) }}</span>
                                @unless ($locked)
                                    <span class="v-permblock__marks">
                                        <button type="button" class="v-link" @click="mark(true)">All</button>
                                        <button type="button" class="v-link" @click="mark(false)">None</button>
                                    </span>
                                @endunless
                            </div>
                            <div class="v-permblock__acts">
                                @foreach ($perms as $permission)
                                    <label class="v-check">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                               @disabled($locked)
                                               @checked($locked || in_array($permission->id, old('permissions', $group?->permissions?->pluck('id')->all() ?? [])))>
                                        {{ ucfirst(Str::afterLast($permission->slug, '.')) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-admin.panel>
        </div>
    </x-admin.form>
</x-admin.settings-shell>

<x-admin.settings-shell title="Permissions" active="permissions">
    <div class="v-head">
        <div class="v-head__body">
            <h1 class="v-head__title">Permissions</h1>
            <p class="v-head__standing">What each role may touch. One save commits the whole matrix.</p>
        </div>
    </div>

    <x-admin.form :action="route('admin.permissions.update')" class="v-settle">
        <x-admin.panel title="Role matrix" flush>
            <div class="v-tablewrap">
                <table class="v-table">
                    <thead>
                        <tr>
                            <th>Permission</th>
                            @foreach ($roles as $role)
                                <th>{{ $role->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group => $perms)
                            <tr class="v-tier">
                                <td colspan="{{ $roles->count() + 1 }}">{{ ucfirst($group) }}</td>
                            </tr>
                            @foreach ($perms as $permission)
                                <tr>
                                    <td class="v-cell-pad">{{ $permission->name }}</td>
                                    @foreach ($roles as $role)
                                        <td>
                                            <label class="v-check">
                                                <input type="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $permission->id }}"
                                                       aria-label="{{ $permission->name }} for {{ $role->name }}"
                                                       @checked($role->permissions->contains($permission->id))>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
    </x-admin.form>
</x-admin.settings-shell>

<x-admin3.settings-shell title="Permissions" active="permissions">
    <div class="v3-head">
        <div class="v3-head__body">
            <h1 class="v3-head__title">Permissions</h1>
            <p class="v3-head__standing">What each role may touch. One save commits the whole matrix.</p>
        </div>
    </div>

    <x-admin3.form :action="route('admin.permissions.update')" class="v3-settle">
        <x-admin3.panel title="Role matrix" flush>
            <div class="v3-tablewrap">
                <table class="v3-table">
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
                            <tr class="v3-tier">
                                <td colspan="{{ $roles->count() + 1 }}">{{ ucfirst($group) }}</td>
                            </tr>
                            @foreach ($perms as $permission)
                                <tr>
                                    <td class="v3-cell-pad">{{ $permission->name }}</td>
                                    @foreach ($roles as $role)
                                        <td>
                                            <label class="v3-check">
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
        </x-admin3.panel>
    </x-admin3.form>
</x-admin3.settings-shell>

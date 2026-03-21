<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $groups = $permissions->groupBy('group');

        return view('admin.users.permissions', compact('roles', 'permissions', 'groups'));
    }

    public function update(Request $request)
    {
        $assignments = $request->input('permissions', []);

        $roles = Role::all();

        foreach ($roles as $role) {
            $permissionIds = $assignments[$role->id] ?? [];
            $role->permissions()->sync($permissionIds);
        }

        return back()->with('success', 'Permissions updated.');
    }
}

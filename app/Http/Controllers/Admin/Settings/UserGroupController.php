<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserGroupController extends Controller
{
    public function index()
    {
        $groups = Role::withCount(['users', 'permissions'])->orderBy('name')->get();
        $permissionTotal = Permission::count();

        return view('admin.settings.users.groups.index', compact('groups', 'permissionTotal'));
    }

    public function create()
    {
        return view('admin.settings.users.groups.form', [
            'group' => null,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        DB::transaction(function () use ($validated) {
            $group = Role::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'description' => $validated['description'] ?? null,
            ]);

            $group->permissions()->sync($validated['permissions'] ?? []);
        });

        return redirect()->route('admin.user-groups.index')->with('success', 'User group created.');
    }

    public function edit(Role $userGroup)
    {
        $userGroup->load('permissions');

        return view('admin.settings.users.groups.form', [
            'group' => $userGroup,
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(Request $request, Role $userGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $userGroup->id,
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        DB::transaction(function () use ($validated, $userGroup) {
            // The slug is the group's stable programmatic anchor
            // (super-admin guards key on it), so a rename never moves it.
            $userGroup->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            // Super Admin always holds every permission; a saved form
            // can never strip the group that administers this page.
            $userGroup->permissions()->sync(
                $userGroup->slug === 'super-admin'
                    ? Permission::pluck('id')
                    : ($validated['permissions'] ?? [])
            );
        });

        return redirect()->route('admin.user-groups.index')->with('success', 'User group updated.');
    }

    public function destroy(Role $userGroup)
    {
        if ($userGroup->slug === 'super-admin') {
            return back()->with('error', 'The Super Admin group cannot be deleted.');
        }

        if ($userGroup->users()->exists()) {
            return back()->with('error', 'Reassign this group\'s users before deleting it.');
        }

        DB::transaction(function () use ($userGroup) {
            $userGroup->permissions()->detach();
            $userGroup->delete();
        });

        return redirect()->route('admin.user-groups.index')->with('success', 'User group deleted.');
    }

    private function permissionGroups()
    {
        return Permission::orderBy('group')->orderBy('id')->get()->groupBy('group');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'group';
        $slug = $base;
        $n = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}

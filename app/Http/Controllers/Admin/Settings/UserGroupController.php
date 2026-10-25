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
    /**
     * Permission groups arranged under the sidebar sections they gate,
     * so the matrix reads like the nav. Unmapped groups (extensions
     * registering their own permissions later) fall under Extensions.
     */
    private const SECTIONS = [
        'Overview' => ['dashboard', 'reports'],
        'Sales' => ['orders', 'couriers', 'reviews'],
        'Catalog' => ['products', 'categories', 'brands', 'tags', 'attributes', 'media'],
        'Customers' => ['customers', 'customer-groups'],
        'Marketing' => ['marketing'],
        'Content' => ['pages', 'blog', 'menus'],
        'Layout and themes' => ['layout', 'themes'],
        'Extensions' => ['extensions'],
        'System' => ['users', 'settings'],
    ];

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
            'permissionSections' => $this->permissionSections(),
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
        $userGroup->load('permissions')->loadCount('users');

        return view('admin.settings.users.groups.form', [
            'group' => $userGroup,
            'permissionSections' => $this->permissionSections(),
        ]);
    }

    public function duplicate(Role $userGroup)
    {
        $copy = DB::transaction(function () use ($userGroup) {
            $name = $this->uniqueName($userGroup->name);
            $copy = Role::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'description' => $userGroup->description,
            ]);
            $copy->permissions()->sync($userGroup->permissions()->pluck('permissions.id'));

            return $copy;
        });

        return redirect()->route('admin.user-groups.edit', $copy)
            ->with('success', 'Group duplicated. This copy has no users yet.');
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

    private function permissionSections(): array
    {
        // toBase(): Eloquent's except() keys by model, this map keys by
        // group name.
        $byGroup = Permission::orderBy('id')->get()->groupBy('group')->toBase();
        $mapped = collect(self::SECTIONS)->flatten()->all();

        $sections = [];
        foreach (self::SECTIONS as $section => $keys) {
            $groups = collect($keys)
                ->filter(fn ($key) => $byGroup->has($key))
                ->mapWithKeys(fn ($key) => [$key => $byGroup->get($key)]);

            if ($section === 'Extensions') {
                // Extensions owns every group no section claims, so
                // extension-registered permissions need no mapping here.
                $groups = $groups->merge($byGroup->except($mapped)->sortKeys());
            }

            if ($groups->isNotEmpty()) {
                $sections[$section] = $groups;
            }
        }

        return $sections;
    }

    private function uniqueName(string $name): string
    {
        $base = $name . ' (copy)';
        $candidate = $base;
        $n = 2;

        while (Role::where('name', $candidate)->exists()) {
            $candidate = $base . ' ' . $n++;
        }

        return $candidate;
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

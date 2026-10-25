<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeRequest;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use Illuminate\Support\Facades\DB;

/**
 * Attributes: the named facts products carry (origin, weight, lens width),
 * listed under their groups. A group is named on the attribute form and
 * disappears when its last attribute goes.
 */
class AttributeController extends Controller
{
    public function index()
    {
        $groups = AttributeGroup::with(['attributes' => fn ($q) => $q->withCount('values')])
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.catalog.attributes.index', compact('groups'));
    }

    public function create()
    {
        return view('admin.catalog.attributes.form', ['attribute' => null, 'groupNames' => $this->groupNames()]);
    }

    public function store(AttributeRequest $request)
    {
        DB::transaction(fn () => Attribute::create($this->fields($request)));

        return redirect()->route('admin.attributes.index')->with('success', 'Attribute created.');
    }

    public function edit(Attribute $attribute)
    {
        return view('admin.catalog.attributes.form', ['attribute' => $attribute->load('group'), 'groupNames' => $this->groupNames()]);
    }

    public function update(AttributeRequest $request, Attribute $attribute)
    {
        DB::transaction(function () use ($request, $attribute) {
            $old = $attribute->attribute_group_id;
            $attribute->update($this->fields($request));
            $this->dropIfEmpty($old);
        });

        return redirect()->route('admin.attributes.index')->with('success', 'Attribute updated.');
    }

    public function destroy(Attribute $attribute)
    {
        DB::transaction(function () use ($attribute) {
            $group = $attribute->attribute_group_id;
            $attribute->delete();
            $this->dropIfEmpty($group);
        });

        return redirect()->route('admin.attributes.index')->with('success', 'Attribute deleted.');
    }

    private function fields(AttributeRequest $request): array
    {
        $data = $request->validated();
        $group = AttributeGroup::firstOrCreate(['name' => trim($data['group'])]);

        return [
            'attribute_group_id' => $group->id,
            'name' => trim($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_filterable' => $request->boolean('is_filterable'),
            'show_on_card' => $request->boolean('show_on_card'),
        ];
    }

    private function dropIfEmpty(int $groupId): void
    {
        AttributeGroup::whereKey($groupId)->whereDoesntHave('attributes')->delete();
    }

    private function groupNames(): array
    {
        return AttributeGroup::orderBy('name')->pluck('name')->all();
    }
}

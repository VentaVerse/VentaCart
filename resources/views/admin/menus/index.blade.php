<x-admin.shell title="Menus">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Content</span>
                <span aria-hidden="true">/</span>
                <span>Menus</span>
            </div>
            <h1 class="v-head__title">Menus</h1>
            <p class="v-head__standing">The store's menus. The theme draws the main menu in the header, and the others where it is set to show them.</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    {{-- Which menu the page edits: a real link per menu, so switching
         works with JavaScript off. --}}
    <x-admin.segment label="Menus">
        @foreach ($menus as $vMenu)
            <x-admin.segment-item :href="\App\Http\Controllers\Admin\Content\MenuController::urlFor($vMenu)" :active="$vMenu->is($menu)">{{ $vMenu->name }}</x-admin.segment-item>
        @endforeach
        @can('menus.create')
            <x-slot:aside>
                <button type="button" class="v-btn v-btn--sm" x-data @click="$dispatch('v-menu-form', 'add')">Add a menu</button>
            </x-slot:aside>
        @endcan
    </x-admin.segment>

    @include('admin.menus.tree')
    @include('admin.menus.manage')
</x-admin.shell>

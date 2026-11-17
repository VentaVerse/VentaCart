<x-admin.shell title="Menus">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <span>Content</span>
                <span aria-hidden="true">/</span>
                <span>Menus</span>
            </div>
            <h1 class="v-head__title">Menus</h1>
            <p class="v-head__standing">The links in the store header. Every theme draws the same menu.</p>
        </div>
        <div class="v-head__actions">
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="v-btn v-btn--sm">View store</a>
        </div>
    </div>

    @include('admin.menus.tree')
</x-admin.shell>

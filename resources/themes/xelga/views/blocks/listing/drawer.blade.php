{{-- The filter drawer: on phones, or on every screen when the section says so, the filter form moves in here. --}}
<dialog class="drawer filter-drawer {{ $settings['scheme'] }}" id="{{ $section['id'] }}-filter-drawer" aria-labelledby="{{ $section['id'] }}-filter-title" data-filter-drawer>
    <div class="drawer__head">
        <h2 class="filters__title" id="{{ $section['id'] }}-filter-title">Filter</h2>
        <button type="button" class="icon-btn" data-filter-close aria-label="Close filters"><svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg></button>
    </div>
    <div class="drawer__body" data-filter-slot></div>
</dialog>

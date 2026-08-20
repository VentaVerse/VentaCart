<section class="py-10 md:py-14">
    <div class="container-store">
        <h2 class="section-title reveal">{{ $placement->title }}</h2>
@php
    $config = $placement->config ?? [];
@endphp

<div class="category-tabs" x-data="categoryTabs()">
    <div class="text-center text-[var(--color-text)] py-8" class="font-body">
        <p>Category tabs will be available after category selection is configured.</p>
    </div>
</div>
    </div>
</section>

@php
    $config = $section->config ?? [];
@endphp

<div class="category-tabs" x-data="categoryTabs()">
    <div class="text-center text-[var(--color-text)] py-8" class="font-body">
        <p>Category tabs will be available after category selection is configured.</p>
    </div>
</div>

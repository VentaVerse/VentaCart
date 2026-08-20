<section class="py-10 md:py-14">
    <div class="container-store">
        <h2 class="section-title reveal">{{ $placement->title }}</h2>
@php
    $config = $placement->config ?? [];
    // Blog posts will be populated when blog system is built
@endphp

<div class="text-center text-[var(--color-text-muted)] py-8">
    <p>Blog posts will appear here once articles are published.</p>
</div>
    </div>
</section>

{{-- Footer layout: columns. The original arrangement. --}}
<footer class="site-footer mt-16 pt-12 pb-6">
    <div class="container-store">
        <hr class="divider-glow mb-10">

        <div class="grid grid-cols-2 md:grid-cols-{{ $footerColumns->count() ?: 3 }} gap-10 mb-10">
            @foreach ($footerColumns as $column)
                <div>
                    <h4>{{ $column->title }}</h4>
                    @include('partials.footer.parts.column-content')
                </div>
            @endforeach
        </div>

        <hr class="divider-glow mb-6">
        <div class="text-center text-xs text-[var(--color-text-muted)]" class="font-body">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </div>
</footer>

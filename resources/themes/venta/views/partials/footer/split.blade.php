{{-- Footer layout: split. Identity and story on the left,
     the working columns on the right. --}}
<footer class="site-footer mt-16 pt-12 pb-6">
    <div class="container-store">
        <hr class="divider-glow mb-10">

        <div class="flex flex-col lg:flex-row gap-12 mb-10">
            <div class="lg:w-72 flex-shrink-0">
                <div class="mb-4"><span class="text-logo">{{ $siteName }}</span></div>
                @foreach ($footerColumns->where('type', 'text') as $column)
                    <div class="mb-4">
                        @include('partials.footer.parts.column-content')
                    </div>
                @endforeach
                @foreach ($footerColumns->where('type', 'social') as $column)
                    @include('partials.footer.parts.column-content')
                @endforeach
            </div>

            <div class="flex-1 grid grid-cols-2 md:grid-cols-{{ max($footerColumns->whereIn('type', ['links', 'payment_icons'])->count(), 1) }} gap-10">
                @foreach ($footerColumns->whereIn('type', ['links', 'payment_icons']) as $column)
                    <div>
                        <h4>{{ $column->title }}</h4>
                        @include('partials.footer.parts.column-content')
                    </div>
                @endforeach
            </div>
        </div>

        <hr class="divider-glow mb-6">
        <div class="text-center text-xs text-[var(--color-text-muted)]" class="font-body">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </div>
</footer>

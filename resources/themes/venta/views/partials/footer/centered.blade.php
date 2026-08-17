{{-- Footer layout: centered. One quiet centered stack: identity,
     every link in a single row, socials, payment marks, copyright. --}}
<footer class="site-footer mt-16 pt-12 pb-6">
    <div class="container-store text-center">
        <hr class="divider-glow mb-10">

        <div class="mb-8">
            <span class="text-logo">{{ $siteName }}</span>
        </div>

        @php
            $vFootLinks = $footerColumns->where('type', 'links')->flatMap(fn ($c) => $c->config['links'] ?? []);
        @endphp
        @if ($vFootLinks->isNotEmpty())
            <div class="footer-links footer-links--row mb-8">
                @foreach ($vFootLinks as $link)
                    <a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                @endforeach
            </div>
        @endif

        @foreach ($footerColumns->whereIn('type', ['social', 'payment_icons']) as $column)
            <div class="mb-6 flex justify-center">
                @include('partials.footer.parts.column-content')
            </div>
        @endforeach

        @foreach ($footerColumns->where('type', 'text') as $column)
            <div class="mb-6 max-w-xl mx-auto">
                @include('partials.footer.parts.column-content')
            </div>
        @endforeach

        <hr class="divider-glow mb-6">
        <div class="text-center text-xs text-[var(--color-text-muted)]" class="font-body">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </div>
</footer>

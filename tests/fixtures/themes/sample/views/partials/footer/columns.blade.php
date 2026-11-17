{{-- The footer: the store's footer menu, when it has one. --}}
<footer class="site-footer mt-16 pt-12 pb-6">
    <div class="container-store">
        @if ($vFooterMenu = \App\Support\NavTree::for('footer'))
            <ul class="footer-links">
                @foreach ($vFooterMenu as $vNode)
                    @if (($vUrl = \App\Support\SafeInput::link((string) ($vNode['url'] ?? ''))) !== '')
                        <li><a href="{{ $vUrl }}">{{ $vNode['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        @endif
        <div class="text-center text-xs text-[var(--color-text-muted)]">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </div>
</footer>

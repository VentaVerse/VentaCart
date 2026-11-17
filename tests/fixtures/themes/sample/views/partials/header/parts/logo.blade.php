            {{-- Logo --}}
            <a href="{{ route('home') }}" class="logo flex-shrink-0">
                @if ($vLogo = \App\Support\StoreIdentity::logoUrl())
                    <img src="{{ $vLogo }}" alt="{{ $siteName }}">
                @else
                    <span class="text-logo">{{ $siteName }}</span>
                @endif
            </a>

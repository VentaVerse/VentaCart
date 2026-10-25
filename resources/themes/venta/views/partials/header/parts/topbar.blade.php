    {{-- Top Bar --}}
    <div class="topbar">
        <div class="container-store flex items-center justify-between">
            <div class="flex items-center gap-5">
                @php
                    $topbarLinks = $theme->getJson('header.topbar_links', []);
                @endphp
                @foreach ($topbarLinks as $link)
                    <a href="{{ $link['url'] ?? '#' }}">{{ $link['label'] ?? '' }}</a>
                @endforeach
            </div>
            <div class="flex items-center gap-5">
                {{-- Currency Selector --}}
                @if ($currencies->count() > 1)
                    <div class="currency-selector" x-data="currencySelector({ current: @js((string) $activeCurrency?->code), symbol: @js((string) $activeCurrency?->symbol) })">
                        <button @click="toggle()" type="button">
                            <span class="led led-green mr-1"></span>
                            <span x-text="current"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="dropdown" x-show="open" @click.away="open = false" x-cloak>
                            @foreach ($currencies as $currency)
                                <button @click="select('{{ $currency->code }}', '{{ $currency->symbol }}')" :class="{ 'active': current === '{{ $currency->code }}' }">
                                    {{ $currency->symbol }} {{ $currency->code }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @elseif ($defaultCurrency)
                    <span class="text-xs text-[var(--color-text-muted)]">
                        <span class="led led-green mr-1"></span>
                        {{ $defaultCurrency->code }}
                    </span>
                @endif

                {{-- Account Link --}}
                @if ($customerLoggedIn)
                    <a href="{{ route('account.dashboard') }}" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $customerUser->first_name }}
                    </a>
                @else
                    <a href="{{ route('customer.login') }}" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Account
                    </a>
                @endif
            </div>
        </div>
    </div>

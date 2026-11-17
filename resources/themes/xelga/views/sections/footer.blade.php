{{-- Footer: store name and about text, menus, contact details, payment methods and the legal line. --}}
@php
    $id = $section['id'];
    $flag = static fn ($v) => $v ? 'true' : 'false';
@endphp
<footer class="s {{ $section['class'] }} {{ $settings['scheme'] }}"
        data-section-id="{{ $id }}" data-section-type="footer"
        data-variant="{{ $settings['variant'] }}"
        data-show-socials="{{ $flag($settings['show_socials']) }}"
        data-show-payments="{{ $flag($settings['show_payments']) }}"
        @if ($section['disabled']) hidden @endif>
    <div class="s__inner">
        <div class="footer__top">
            <div class="footer__brand">
                <a class="wordmark" href="{{ route('home') }}">{{ $data['storeName'] }}</a>
                <p class="footer__about" data-setting="about" data-optional @if ($settings['about'] === '') hidden @endif>{{ $settings['about'] }}</p>
                @if ($data['socials'] !== [])
                    <ul class="socials">
                        @foreach ($data['socials'] as $social)
                            <li><a class="icon-btn icon-btn--outline" href="{{ $social['url'] }}" data-social="{{ $social['network'] }}" aria-label="{{ $data['storeName'] }} on {{ $social['label'] }}" rel="noopener"><svg class="icon" aria-hidden="true"><use href="#i-{{ $social['network'] }}"></use></svg></a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            @foreach ($blocks as $block)
                @include('xelga::blocks.footer.' . $block['type'], ['block' => $block])
            @endforeach
        </div>
        <div class="footer__bottom">
            <p data-setting="legal">{{ $data['legal'] }}</p>
            @if ($data['payments'] !== [])
                <ul class="pay-list" aria-label="Payment methods">
                    @foreach ($data['payments'] as $payment)
                        <li class="pay">{{ $payment }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</footer>

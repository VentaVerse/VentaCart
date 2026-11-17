{{-- Announcement bar: up to three short messages above the header, in one line, taking turns or scrolling. --}}
@php
    $messages = [];
    foreach ($blocks as $block) {
        $text = trim((string) ($block['settings']['text'] ?? ''));
        if ($text !== '' || $context->preview) {
            $messages[] = ['block' => $block, 'text' => $text, 'url' => \Themes\Xelga\Settings\LinkValue::resolve($block['settings']['link'] ?? null)];
        }
    }
@endphp
@if ($messages !== [] || $context->preview)
    <div class="s {{ $section['class'] }} {{ $settings['scheme'] }}" @include('xelga::snippets.section-attrs')
         data-interval="{{ $settings['interval'] }}" data-show-arrows="{{ $settings['show_arrows'] ? 'true' : 'false' }}"
         role="region" aria-label="{{ $section['label'] }}">
        <div class="s__inner announce">
            <button type="button" class="icon-btn announce__btn" data-announce-prev aria-label="Previous message"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-left"></use></svg></button>
            <div class="announce__track">
                <ul class="announce__group">
                    @foreach ($messages as $message)
                        <li class="announce__msg" data-block-id="{{ $message['block']['id'] }}" data-block-type="message" @if ($message['block']['disabled']) hidden @endif>
                            @if ($message['url'] !== null || $context->preview)
                                <a href="{{ $message['url'] ?? '#' }}" data-setting-href="link"><span data-setting="text">{{ $message['text'] }}</span></a>
                            @else
                                <span data-setting="text">{{ $message['text'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="icon-btn announce__btn" data-announce-next aria-label="Next message"><svg class="icon icon--sm" aria-hidden="true"><use href="#i-chevron-right"></use></svg></button>
        </div>
    </div>
@endif

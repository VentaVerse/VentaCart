{{-- A footer column of the merchant's own text. Rich text was cleaned when it was saved, and is cleaned again here in case a shipped file was not. --}}
<div class="footer__col footer__col--text" data-block-id="{{ $block['id'] }}" data-block-type="text"
     @if ($block['disabled']) hidden @endif>
    <h2 class="footer__heading" data-setting="heading">{{ $block['settings']['heading'] }}</h2>
    <div class="rte" data-setting="text" data-richtext>{!! \Themes\Xelga\Settings\RichTextPolicy::clean($block['settings']['text']) ?? '' !!}</div>
</div>

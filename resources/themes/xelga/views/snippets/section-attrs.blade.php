{{-- The attributes every section shares: its id, type, variant, spacing, background and whether it is hidden. --}}
data-section-id="{{ $section['id'] }}" data-section-type="{{ $section['type'] }}"
@isset($settings['variant']) data-variant="{{ $settings['variant'] }}" @endisset
@isset($settings['pad']) data-pad="{{ $settings['pad'] }}" @endisset
@isset($settings['texture']) data-texture="{{ $settings['texture'] }}" data-bg-overlay="{{ $settings['bg_overlay'] ?? 40 }}" data-has-bg="{{ \Themes\Xelga\Rendering\Picture::of($settings['bg_image'] ?? '') ? 'true' : 'false' }}" @endisset
@if ($section['disabled']) hidden @endif

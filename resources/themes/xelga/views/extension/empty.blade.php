{{-- An empty state for an extension's page: `title`, `text` (optional), `link` (optional {label, url}). --}}
@php $xelgaLink = is_array($link ?? null) ? \App\Support\SafeInput::link($link['url'] ?? null) : ''; @endphp
<div class="empty-note">
    <p class="empty-note__title">{{ $title ?? '' }}</p>
    @if (! empty($text))<p class="muted">{{ $text }}</p>@endif
    @if ($xelgaLink !== '')<p><a class="btn" href="{{ $xelgaLink }}">{{ $link['label'] ?? '' }}</a></p>@endif
</div>

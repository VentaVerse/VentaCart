{{-- The trail drawn inside a content page's own head (blog, post, page, contact). --}}
@if (count($crumbs) > 1)
    <nav class="page-crumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($crumbs as $crumb)
                @if ($crumb['url'] !== null)
                    <li><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
                @else
                    <li aria-current="page">{{ $crumb['label'] }}</li>
                @endif
            @endforeach
        </ol>
    </nav>
@endif

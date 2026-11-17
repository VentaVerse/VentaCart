{{-- The trail above an account page: home, the account, then this page. Uses the store's breadcrumb styles. --}}
<nav class="fixed-crumbs" aria-label="Breadcrumb">
    <ol class="crumbs">
        <li><a href="{{ route('home') }}">Home</a></li>
        @foreach ($trail as $crumb)
            @if (! empty($crumb['url']))
                <li><a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a></li>
            @else
                <li><span aria-current="page">{{ $crumb['label'] }}</span></li>
            @endif
        @endforeach
    </ol>
</nav>

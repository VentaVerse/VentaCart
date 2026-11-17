{{--
    The frame an extension's own page draws in (theme contract, extension
    hooks): the header and footer, the trail, the title and the content.
    Sections the extension's view fills: `title` and `content`. Optional:
    $trail, a list of {label, url?} after Home.

        @extends(view()->exists('extension.page') ? 'extension.page' : 'marketplace::layouts.plain')

    A stable name: the partials it wraps may change, this view does not
    within the hooks' API version.
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page(\App\Support\StorefrontPageType::current() ?? 'page');
    $xelgaTitle = trim($__env->yieldContent('title'));
    $xelgaTrail = \App\Support\StorefrontPage::breadcrumbs($trail ?? []);
    if ($xelgaTrail === [] && $xelgaTitle !== '') {
        $xelgaTrail = [['label' => $xelgaTitle, 'url' => null]];
    }
@endphp
<x-store-layout :title="$xelgaTitle !== '' ? $xelgaTitle : null">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="extension">
        <div class="s__inner">
            @if ($xelgaTrail !== [])
                @include('xelga::partials.fixed.crumbs', ['trail' => $xelgaTrail])
            @endif
            <div class="acct__main">
                @if ($xelgaTitle !== '')
                    <header class="acct__head">
                        <h1 class="acct__title">{{ $xelgaTitle }}</h1>
                    </header>
                @endif
                @yield('content')
            </div>
        </div>
    </div>
</x-store-layout>

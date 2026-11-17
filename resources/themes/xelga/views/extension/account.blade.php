{{--
    The frame an extension's page in the customer's account draws in: the
    page frame with the account menu beside it (the theme's links, then the
    links extensions added with App\Support\AccountMenu). Sections: `title`
    and `content`; optional $trail after "Your account".

        @extends(view()->exists('extension.account') ? 'extension.account' : 'marketplace::layouts.plain')
--}}
@php
    app(\Themes\Xelga\Rendering\Storefront::class)->page('account');
    $xelgaTitle = trim($__env->yieldContent('title'));
    $xelgaTrail = [
        ['label' => 'Your account', 'url' => \Illuminate\Support\Facades\Route::has('account.dashboard') ? route('account.dashboard') : null],
        ...\App\Support\StorefrontPage::breadcrumbs($trail ?? ($xelgaTitle !== '' ? [['label' => $xelgaTitle]] : [])),
    ];
@endphp
<x-store-layout :title="$xelgaTitle !== '' ? $xelgaTitle : null">
    @include('xelga::partials.fixed.page-assets')
    <div class="s fixed-page scheme-1" data-fixed-page="extension-account">
        <div class="s__inner">
            @include('xelga::partials.fixed.crumbs', ['trail' => $xelgaTrail])
            <div class="acct">
                @include('xelga::partials.fixed.account-nav')
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
    </div>
</x-store-layout>

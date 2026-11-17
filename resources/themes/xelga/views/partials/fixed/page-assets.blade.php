{{-- The stylesheets of the sign-in, account and checkout pages, kept out of the bundle other pages load. --}}
@push('head')
    @vite(['resources/themes/xelga/css/pages/account.css', ...(! empty($checkout) ? ['resources/themes/xelga/css/pages/checkout.css'] : [])])
@endpush

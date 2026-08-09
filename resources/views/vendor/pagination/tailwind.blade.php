{{-- Laravel resolves every ->links() call in the application through
     this one view, and this application has two interfaces on it: the
     admin, which themes on html[data-theme] and owns the v-* token
     set, and the storefront, whose three themes carry their own CSS.

     The stock view is written in raw gray-* utilities plus Tailwind
     `dark:` variants. Those variants key off prefers-color-scheme,
     which is not how the admin themes, so the admin got a white pager
     block on a near-black page with its "Showing 1 to 20 of 27,698
     results" line at 1.73:1.

     Rather than convert the shared view and break three storefront
     themes that were built against its markup, the two audiences are
     split here. The storefront keeps the stock view byte for byte;
     the admin gets one written in the admin's own tokens, so it
     follows data-theme like everything else on the page.

     The test is the route name, not the URL, because the admin prefix
     is configurable and both first-party extensions register their
     admin screens under the same admin. prefix. --}}
@if (request()->routeIs('admin.*'))
    @include('pagination::venta-admin')
@else
    @include('pagination::storefront-tailwind')
@endif

{{--
    A page of a page type an extension added (App\Support\StorefrontPage::render):
    its Xelga template's sections, which read what the extension's controller
    handed the page. The trail goes in as core cleaned it.
--}}
<x-store-layout :title="$title">
    @include('xelga::snippets.template', [
        'pageType' => $pageType,
        'subject' => $subject,
        'page' => ['breadcrumbs' => $breadcrumbs] + $page,
    ])
</x-store-layout>

<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Http;

use App\Http\Controllers\Controller;
use App\Support\StorefrontPage;
use Illuminate\Contracts\View\View;
use Tests\Fixtures\Extensions\SampleExtension\SampleExtensionServiceProvider;

/** A storefront page of the extension's own page type. */
class SamplePageController extends Controller
{
    public function show(string $slug): View
    {
        $thing = (object) ['id' => 7, 'slug' => $slug, 'name' => 'Demo thing ' . $slug];

        return StorefrontPage::render(SampleExtensionServiceProvider::PAGE_TYPE, 'sample-extension::page', [
            'title' => $thing->name,
            'subject' => $thing,
            'thing' => $thing,
            'breadcrumbs' => [['label' => 'Things', 'url' => url('/sample-extension/things')], ['label' => $thing->name]],
        ]);
    }
}

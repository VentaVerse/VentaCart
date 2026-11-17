<?php

namespace Tests\Feature\Storefront;

use App\Services\ThemeService;
use App\Support\StorefrontPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\UsesSampleExtension;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * A page of an extension's own page type is drawn with the live theme's
 * `extension-page` view when the theme has one, else with the extension's
 * own view.
 */
class StorefrontPageTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use UsesSampleTheme;

    public function test_a_theme_without_the_page_view_gets_the_extensions_own_view(): void
    {
        $this->installSampleExtension();

        $this->get('/sample-extension/things/lamp')
            ->assertOk()
            ->assertSee('data-sample-page="fallback"', false)
            ->assertSee('Demo thing lamp');
    }

    public function test_a_theme_with_the_page_view_draws_the_page_with_the_data(): void
    {
        $this->installSampleExtension();
        $this->installSampleTheme(activate: true);
        file_put_contents(
            storage_path('framework/testing/themes/' . $this->sampleTheme . '/views/extension-page.blade.php'),
            '<x-store-layout :title="$title"><main data-theme-page="{{ $pageType }}" data-subject="{{ $subject->id }}">'
            . '{{ $page[\'thing\']->name }}@foreach ($breadcrumbs as $crumb)<i data-url="{{ $crumb[\'url\'] }}">{{ $crumb[\'label\'] }}</i>@endforeach'
            . '</main></x-store-layout>',
        );
        app(ThemeService::class)->clearCache();

        $this->get('/sample-extension/things/lamp')
            ->assertOk()
            ->assertSee('data-theme-page="sample_page"', false)
            ->assertSee('data-subject="7"', false)
            ->assertSee('Demo thing lamp')
            ->assertSee('<i data-url="">Demo thing lamp</i>', false)
            ->assertDontSee('data-sample-page="fallback"', false);
    }

    public function test_a_page_type_no_extension_registered_gets_the_extensions_view_and_a_log_line(): void
    {
        $this->installSampleExtension();
        Log::spy();

        $view = StorefrontPage::render('not_registered', 'sample-extension::page', ['title' => 'Lamp']);

        $this->assertSame('sample-extension::page', $view->name());
        $this->assertSame('Lamp', $view->getData()['title']);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_core_page_types_are_not_extension_types(): void
    {
        $this->assertFalse(StorefrontPage::isExtensionType('product'));
        $this->assertFalse(StorefrontPage::isExtensionType('sample_page'), 'Not registered while the extension is not installed.');
    }

    public function test_the_trail_keeps_plain_labels_and_safe_links_only(): void
    {
        $trail = StorefrontPage::breadcrumbs([
            ['label' => 'Shops', 'url' => 'https://example.com/shops'],
            ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
            ['label' => '', 'url' => '/empty'],
            'not a crumb',
            ['label' => "Demo\x07 Store"],
        ]);

        $this->assertSame([
            ['label' => 'Shops', 'url' => 'https://example.com/shops'],
            ['label' => 'Bad', 'url' => null],
            ['label' => 'Demo  Store', 'url' => null],
        ], $trail);
        $this->assertSame([], StorefrontPage::breadcrumbs('nope'));
    }
}

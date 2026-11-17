<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The shopper's currency choice: a list in the header and the phone menu
 * when the store has two or more enabled currencies, hidden by the header
 * setting, and prices shown in the chosen currency while the order is
 * charged in the store's own.
 */
class XelgaCurrencyTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();

        Currency::query()->delete();
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'is_default' => true, 'is_enabled' => true]);
        Currency::create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => 'EUR ', 'rate' => 0.5, 'is_default' => false, 'is_enabled' => true]);
        app(ThemeService::class)->clearCache();
        CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Retail'], ['is_default' => true]);
    }

    /** One storefront request, read fresh as a new visit would be. */
    private function visit(string $url, ?string $currency = null): TestResponse
    {
        $this->app->forgetInstance(ThemeService::class);
        app(ThemeService::class)->clearCache();
        Storefront::forgetRequestState();

        $request = $currency === null ? $this : $this->withUnencryptedCookie('currency', $currency);

        return $request->get($url)->assertOk();
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Demo Shirt', 'slug' => 'demo-shirt', 'sku' => 'DEMO-001',
            'price' => 450, 'quantity' => 10, 'is_active' => true, 'track_stock' => true,
        ]);
    }

    public function test_the_header_lists_every_enabled_currency_with_the_one_in_use_selected(): void
    {
        Currency::create(['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => 'GBP ', 'rate' => 0.4, 'is_default' => false, 'is_enabled' => false]);

        $html = $this->visit('/', 'EUR')->getContent();

        $this->assertMatchesRegularExpression('#<header[^>]*data-show-currency="true"#', $html);
        $this->assertStringContainsString('aria-haspopup="listbox" aria-expanded="false"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertMatchesRegularExpression('#role="option" data-currency="EUR" aria-selected="true"#', $html);
        $this->assertMatchesRegularExpression('#role="option" data-currency="USD" aria-selected="false"#', $html);
        $this->assertMatchesRegularExpression('#<span class="currency-picker__code">EUR</span>#', $html);
        // The phone menu offers the same choice.
        $this->assertMatchesRegularExpression('#class="drawer__currency-choice" data-currency="EUR" aria-pressed="true"#', $html);
        $this->assertMatchesRegularExpression('#class="drawer__currency-choice" data-currency="USD" aria-pressed="false"#', $html);
        // A disabled currency is never offered.
        $this->assertStringNotContainsString('data-currency="GBP"', $html);
    }

    public function test_a_store_with_one_currency_shows_no_choice(): void
    {
        Currency::where('code', 'EUR')->update(['is_enabled' => false]);

        $html = $this->visit('/', 'EUR')->getContent();

        $this->assertStringNotContainsString('data-currency-picker', $html);
        $this->assertStringNotContainsString('drawer__currency', $html);
        $this->assertStringNotContainsString('role="listbox"', $html);
    }

    public function test_the_header_setting_hides_the_choice(): void
    {
        $admin = $this->admin();
        $header = $this->defaults('header');
        $this->assertTrue($header['sections']['header-1']['settings']['show_currency'], 'Shown by default.');
        $header['sections']['header-1']['settings']['show_currency'] = false;

        $this->saveDraft($admin, 'header', $header, 0)->assertOk();
        $this->publish($admin, ['header' => 1])->assertOk();
        $this->asGuest();

        $html = $this->visit('/')->getContent();
        $this->assertMatchesRegularExpression('#<header[^>]*data-show-currency="false"#', $html);

        // The attribute is what hides it, in the bar and in the phone menu.
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/sections/header.css');
        $this->assertStringContainsString('.s-header[data-show-currency="false"] .header__currency', $css);
        $this->assertStringContainsString('.s-header[data-show-currency="false"] .drawer__currency', $css);
    }

    public function test_a_value_the_setting_does_not_take_is_stored_as_a_toggle(): void
    {
        $header = $this->defaults('header');
        $header['sections']['header-1']['settings']['show_currency'] = '<script>';

        $this->saveDraft($this->admin(), 'header', $header, 0)->assertOk();

        $value = \Themes\Xelga\Documents\Document::where('handle', 'header')->firstOrFail()->draft['sections']['header-1']['settings']['show_currency'];
        $this->assertIsBool($value);
    }

    public function test_the_cookie_shows_prices_in_the_chosen_currency(): void
    {
        $product = $this->product();
        $url = route('product.show', $product->slug);

        $usd = $this->visit($url)->getContent();
        $this->assertStringContainsString('$450.00', $usd);
        $this->assertMatchesRegularExpression('#<html[^>]*data-money-symbol="\$"[^>]*data-money-rate="1"#', $usd);

        $eur = $this->visit($url, 'EUR')->getContent();
        $this->assertStringContainsString('EUR 225.00', $eur);
        $this->assertStringNotContainsString('$450.00', $eur);
        // The storefront script formats amounts with the same symbol and rate.
        $this->assertMatchesRegularExpression('#<html[^>]*data-money-symbol="EUR "[^>]*data-money-rate="0.5"#', $eur);
    }

    public function test_a_disabled_or_unknown_currency_falls_back_to_the_default(): void
    {
        Currency::create(['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => 'GBP ', 'rate' => 0.4, 'is_default' => false, 'is_enabled' => false]);
        $product = $this->product();

        foreach (['GBP', 'XYZ', '"><b>'] as $code) {
            $html = $this->visit(route('product.show', $product->slug), $code)->getContent();
            $this->assertStringContainsString('$450.00', $html, $code);
            $this->assertStringNotContainsString('GBP 180.00', $html, $code);
            $this->assertMatchesRegularExpression('#role="option" data-currency="USD" aria-selected="true"#', $html, $code);
            $this->assertStringNotContainsString('<b>', $html, $code);
        }
    }

    public function test_checkout_says_the_order_is_charged_in_the_store_currency_only_when_another_is_shown(): void
    {
        $customer = Customer::create([
            'store_id' => 1, 'customer_group_id' => CustomerGroup::where('name', 'Retail')->value('id'),
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.test', 'password' => bcrypt('secret-pass'),
        ]);
        $country = Country::firstOrCreate(['iso2' => 'XT'], ['name' => 'Testland', 'iso3' => 'XTL', 'phone_code' => '99', 'is_active' => true]);
        CustomerAddress::create([
            'customer_id' => $customer->id, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'address_1' => '12 Demo Street', 'city' => 'Demo City', 'state' => 'Demo Region', 'postcode' => '1000',
            'country_id' => $country->id, 'phone' => '9001234567', 'is_default' => true,
        ]);
        $this->actingAs($customer, 'customer')->postJson(route('cart.add'), ['product_id' => $this->product()->id, 'quantity' => 2])->assertOk();

        $usd = $this->actingAs($customer, 'customer')->visit(route('checkout.index'))->getContent();
        $this->assertStringNotContainsString('data-currency-note', $usd);
        $this->assertStringNotContainsString('Your order is charged in', $usd);
        $this->assertStringContainsString('$900.00', $usd);

        $eur = $this->actingAs($customer, 'customer')->visit(route('checkout.index'), 'EUR')->getContent();
        $this->assertStringContainsString('Prices shown in EUR. Your order is charged in USD.', $eur);
        $this->assertStringContainsString('data-currency-note', $eur);
        $this->assertStringContainsString('EUR 450.00', $eur);
    }
}

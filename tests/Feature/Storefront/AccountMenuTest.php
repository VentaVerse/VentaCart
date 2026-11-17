<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use App\Support\AccountMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/** Links extensions add to the customer's account menu. */
class AccountMenuTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    public function test_links_need_a_route_and_come_by_position(): void
    {
        Route::get('/account/test-b', fn () => 'b')->name('test.b');
        Route::get('/account/test-c', fn () => 'c')->name('test.c');
        Route::get('/account/test-d/{id}', fn () => 'd')->name('test.d');
        app('router')->getRoutes()->refreshNameLookups();

        AccountMenu::add('test.missing', 'No route', 'gift');
        AccountMenu::add('test.c', 'Third', 'star', null, 120);
        AccountMenu::add('test.b', "Store <b>credit</b>\x07", 'not-an-icon', null, 50);
        AccountMenu::add('test.d', 'Needs a parameter');

        $links = AccountMenu::links();

        $this->assertSame(['Store <b>credit</b>', 'Third'], array_column($links, 'label'), 'Text is kept as text for the theme to escape.');
        $this->assertSame(['file', 'star'], array_column($links, 'icon'));
        $this->assertSame([url('/account/test-b'), url('/account/test-c')], array_column($links, 'url'));
        $this->assertSame('test.b*', $links[0]['match']);
    }

    public function test_a_link_without_a_route_name_or_label_is_refused(): void
    {
        Log::spy();

        AccountMenu::add('javascript:alert(1)', 'Bad');
        AccountMenu::add('test.ok', '   ');

        $this->assertSame([], AccountMenu::links());
        Log::shouldHaveReceived('warning')->twice();
    }

    public function test_the_fixture_extensions_link_is_current_on_its_pages(): void
    {
        $this->installSampleExtension();
        Route::middleware('web')->get('/account/sample-things/menu', fn () => response()->json(AccountMenu::links()))->name('sample-extension.account.menu');
        app('router')->getRoutes()->refreshNameLookups();

        $customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria@example.test', 'password' => bcrypt('secret'),
        ]);

        $this->get(route('sample-extension.account'))->assertRedirect();

        $this->actingAs($customer, 'customer')->get(route('sample-extension.account'))
            ->assertOk()
            ->assertSee('data-sample-frame="plain"', false)
            ->assertSee('Nothing here yet.');

        $this->actingAs($customer, 'customer')->getJson('/account/sample-things/menu')
            ->assertOk()
            ->assertJsonPath('0.label', 'Sample things')
            ->assertJsonPath('0.icon', 'store')
            ->assertJsonPath('0.current', true);
    }

    public function test_switched_off_the_link_is_gone(): void
    {
        $this->installSampleExtension();
        $this->assertCount(1, AccountMenu::links());

        $this->switchOffSampleExtension();

        $this->assertSame([], AccountMenu::links());
    }
}

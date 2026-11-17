<?php

namespace Tests\Feature\Storefront;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Product;
use App\Support\CheckoutPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/**
 * One extension may draw the checkout page itself. Only the page changes
 * hands: the route, its sign-in rule and every other checkout step stay
 * core's.
 */
class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'juan@example.test', 'password' => bcrypt('secret'),
        ]);
        $cart = Cart::create(['store_id' => 1, 'customer_id' => $this->customer->id]);
        foreach ([['DEMO-001', 'Demo mug'], ['OTHER-1', 'Plain mug']] as [$sku, $name]) {
            $product = Product::create([
                'store_id' => 1, 'name' => $name, 'slug' => strtolower($sku), 'sku' => $sku,
                'price' => 500, 'quantity' => 20, 'is_active' => true,
            ]);
            $cart->items()->create(['product_id' => $product->id, 'name' => $name, 'price' => 500, 'quantity' => 1]);
        }
    }

    public function test_without_a_registration_core_draws_the_checkout(): void
    {
        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()
            ->assertViewIs('checkout.index');
    }

    public function test_the_fixture_extension_draws_the_checkout_with_its_groups(): void
    {
        $this->installSampleExtension();

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()
            ->assertViewIs('sample-extension::checkout')
            ->assertSee('data-sample-frame="plain"', false)
            ->assertSee('<section data-group="demo-store">', false)
            ->assertSee('<h2>Demo Store</h2>', false)
            ->assertSee('action="' . route('checkout.place') . '"', false);
    }

    public function test_the_sign_in_rule_still_applies(): void
    {
        $this->installSampleExtension();

        $this->get(route('checkout.index'))->assertRedirect(route('customer.login'));
    }

    public function test_the_other_checkout_routes_stay_cores_and_keep_csrf(): void
    {
        $this->installSampleExtension();

        foreach (['checkout.index', 'checkout.place', 'checkout.address.save', 'checkout.shipping-rates', 'checkout.confirmation'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertStringStartsWith('App\\Http\\Controllers\\Storefront\\CheckoutController@', $route->getActionName(), $name);
            $this->assertContains('customer.auth', $route->gatherMiddleware(), $name);
            $this->assertContains('web', $route->gatherMiddleware(), $name);
        }

        // Outside the test environment the token is checked as on a live store.
        $this->app['env'] = 'local';
        $this->actingAs($this->customer, 'customer')->post(route('checkout.place'), [])->assertStatus(419);
    }

    public function test_a_controller_with_an_index_method_is_called_too(): void
    {
        CheckoutPage::use(IndexCheckout::class);

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('checkout drawn by index for Juan');
    }

    public function test_a_missing_class_falls_back_to_core_and_logs(): void
    {
        Log::spy();
        CheckoutPage::use('Extensions\\Gone\\CheckoutController');

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertOk()->assertViewIs('checkout.index');
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'does not exist'))->once();
    }

    public function test_a_class_that_is_not_a_controller_falls_back_to_core_and_logs(): void
    {
        Log::spy();
        CheckoutPage::use(NotACheckout::class);

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertOk()->assertViewIs('checkout.index');
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'not a controller'))->once();
    }

    public function test_a_second_registration_is_refused(): void
    {
        Log::spy();
        CheckoutPage::use(IndexCheckout::class);
        CheckoutPage::use(NotACheckout::class);

        $this->assertSame(IndexCheckout::class, CheckoutPage::controller());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_controllers_own_middleware_runs_before_it(): void
    {
        CheckoutPage::use(GuardedCheckout::class);
        GuardedCheckout::$called = false;

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertForbidden();
        $this->assertFalse(GuardedCheckout::$called, 'The controller never ran.');
    }

    public function test_middleware_a_controller_registers_in_its_constructor_runs_too(): void
    {
        CheckoutPage::use(ConstructorGuardedCheckout::class);

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))
            ->assertOk()
            ->assertHeader('X-Checkout-Checked', 'yes')
            ->assertSee('checked checkout');
    }

    public function test_middleware_limited_to_another_method_does_not_run(): void
    {
        CheckoutPage::use(OnlyElsewhereCheckout::class);

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertOk()->assertSee('open checkout');
    }

    public function test_switched_off_core_draws_the_checkout_again(): void
    {
        $this->installSampleExtension();
        $this->switchOffSampleExtension();

        $this->actingAs($this->customer, 'customer')->get(route('checkout.index'))->assertOk()->assertViewIs('checkout.index');
    }
}

class IndexCheckout
{
    public function index(\Illuminate\Http\Request $request): string
    {
        return 'checkout drawn by index for ' . e($request->user('customer')->first_name);
    }
}

class NotACheckout
{
    public function show(): string
    {
        return 'never';
    }
}

class GuardedCheckout implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static bool $called = false;

    public static function middleware(): array
    {
        return [fn ($request, $next) => abort(403)];
    }

    public function __invoke(): string
    {
        self::$called = true;

        return 'never';
    }
}

class ConstructorGuardedCheckout
{
    private array $middleware = [];

    public function __construct()
    {
        $this->middleware[] = ['middleware' => function ($request, $next) {
            $response = $next($request);
            $response->headers->set('X-Checkout-Checked', 'yes');

            return $response;
        }, 'options' => []];
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function index(): string
    {
        return 'checked checkout';
    }
}

class OnlyElsewhereCheckout implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return [new \Illuminate\Routing\Controllers\Middleware(fn ($request, $next) => abort(403), only: ['store'])];
    }

    public function __invoke(): string
    {
        return 'open checkout';
    }
}

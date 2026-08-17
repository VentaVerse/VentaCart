<?php

use App\Http\Controllers\Storefront\BlogController as StorefrontBlogController;
use App\Http\Controllers\Storefront\CategoryController as StorefrontCategoryController;
use App\Http\Controllers\Storefront\HomeController as StorefrontHomeController;
use App\Http\Controllers\Storefront\CartController as StorefrontCartController;
use App\Http\Controllers\Storefront\ProductController as StorefrontProductController;
use App\Http\Controllers\Storefront\SearchController as StorefrontSearchController;
use App\Http\Controllers\Storefront\ContactController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\CustomerPasswordResetController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AccountAddressController;
use App\Http\Controllers\Storefront\OrderHistoryController;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckoutController;
use App\Http\Controllers\Storefront\CouponController as StorefrontCouponController;
use App\Http\Controllers\Storefront\AddressLookupController as StorefrontAddressLookupController;
use App\Http\Controllers\Storefront\DeepAddressLookupController;
use App\Http\Controllers\Storefront\BrandController as StorefrontBrandController;
use App\Http\Controllers\Storefront\RecentlyViewedController;
use App\Http\Controllers\Storefront\ReviewController as StorefrontReviewController;
use Illuminate\Support\Facades\Route;

// Storefront
Route::get('/', [StorefrontHomeController::class, 'index'])->name('home');
Route::get('/search/autocomplete', [StorefrontSearchController::class, 'autocomplete'])->name('search.autocomplete');
Route::get('/search', [StorefrontSearchController::class, 'results'])->middleware('noindex:follow')->name('search.results');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/recently-viewed', [RecentlyViewedController::class, 'index'])->middleware('noindex')->name('recently-viewed');
Route::get('/brand', [StorefrontBrandController::class, 'index'])->name('brand.index');
Route::get('/brand/{brand:slug}', [StorefrontBrandController::class, 'show'])->name('brand.show');
Route::get('/category/{category:slug}', [StorefrontCategoryController::class, 'show'])->name('category.show');
Route::get('/product/{product:slug}', [StorefrontProductController::class, 'show'])->name('product.show');
Route::get('/blog', [StorefrontBlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [StorefrontBlogController::class, 'show'])->name('blog.show');

// Reviews (public — accessed via token link)
Route::get('/review/{token}', [StorefrontReviewController::class, 'show'])->name('review.submit');
Route::post('/review/{token}', [StorefrontReviewController::class, 'store'])->name('review.store');

// Cart
Route::get('/cart', [StorefrontCartController::class, 'index'])->middleware('noindex')->name('cart.index');
Route::get('/cart/mini', [StorefrontCartController::class, 'mini'])->name('cart.mini');
Route::post('/cart/add', [StorefrontCartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{cartItem}', [StorefrontCartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [StorefrontCartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart', [StorefrontCartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/shipping-estimate', [StorefrontCartController::class, 'estimateShipping'])->name('cart.estimate-shipping');

// HitPay webhook moved to the payment-hitpay extension
// (extensions/payment-hitpay/routes/web.php).

// Checkout (authenticated customers)
Route::middleware(['customer.auth', 'noindex'])->group(function () {
    Route::get('/checkout', [StorefrontCheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/address', [StorefrontCheckoutController::class, 'saveAddress'])->name('checkout.address.save');
    Route::post('/checkout/shipping-rates', [StorefrontCheckoutController::class, 'getShippingRates'])->name('checkout.shipping-rates');
    Route::post('/checkout/place', [StorefrontCheckoutController::class, 'placeOrder'])->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [StorefrontCheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::get('/checkout/hitpay/return', [StorefrontCheckoutController::class, 'hitpayReturn'])->name('checkout.hitpay.return');
    Route::post('/checkout/coupon/apply', [StorefrontCouponController::class, 'apply'])->name('checkout.coupon.apply');
    Route::delete('/checkout/coupon/remove', [StorefrontCouponController::class, 'remove'])->name('checkout.coupon.remove');
});

// Customer Auth (public)
Route::prefix('account')->name('customer.')->middleware('noindex')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->name('register.submit');
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::get('/forgot-password', [CustomerPasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [CustomerPasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [CustomerPasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [CustomerPasswordResetController::class, 'reset'])->name('password.update');
    Route::post('/migration-reset', [CustomerPasswordResetController::class, 'sendMigrationResetLink'])->name('password.migration');
});

// Storefront address lookup (public, no auth — used by address forms)
Route::prefix('address-lookup')->name('storefront.address.')->group(function () {
    Route::get('/countries', [StorefrontAddressLookupController::class, 'countries'])->name('countries');
    Route::get('/zones', [StorefrontAddressLookupController::class, 'zones'])->name('zones');
    Route::get('/providers', [StorefrontAddressLookupController::class, 'providers'])->name('providers');
    // Deep sub-levels (e.g. PH city/barangay) — read on-demand from bundled files, config-driven per country
    Route::get('/deep/{country}/{level}', [DeepAddressLookupController::class, 'level'])
        ->where(['country' => '[A-Za-z]{2}', 'level' => '[a-z_]+'])
        ->name('deep');
});

// Customer Account (authenticated)
Route::prefix('account')->name('account.')->middleware(['customer.auth', 'noindex'])->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [AccountController::class, 'editProfile'])->name('profile');
    Route::post('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/password', [AccountController::class, 'changePassword'])->name('password.update');

    Route::get('/addresses', [AccountAddressController::class, 'index'])->name('addresses');
    Route::get('/addresses/create', [AccountAddressController::class, 'create'])->name('addresses.create');
    Route::post('/addresses', [AccountAddressController::class, 'store'])->name('addresses.store');
    Route::get('/addresses/{address}/edit', [AccountAddressController::class, 'edit'])->name('addresses.edit');
    Route::put('/addresses/{address}', [AccountAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AccountAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{address}/default', [AccountAddressController::class, 'setDefault'])->name('addresses.default');

    Route::get('/orders', [OrderHistoryController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderHistoryController::class, 'show'])->name('orders.show');
});

require __DIR__.'/admin.php';

Route::get('/sitemap', [\App\Http\Controllers\Storefront\SitemapController::class, 'index'])->name('sitemap');

require __DIR__.'/auth.php';

// CMS Pages (catch-all — must be absolutely last, after auth routes)
Route::get('/{slug}', [\App\Http\Controllers\Storefront\PageController::class, 'show'])
    ->name('page.show')
    ->where('slug', '^(?!' . config('admin.prefix', 'admin') . ')[a-z0-9][a-z0-9-]*$');

// Final fallback — matches any request no other route claimed (including
// multi-segment paths the /{slug} rule rejects). Putting it inside the web
// group lets HandleRedirects look up the path *before* we 404, and lets
// LogNotFound record the miss *after*. Without this, multi-segment URLs
// (e.g. /brands/foo, /guitars/bar) would skip both middlewares entirely
// because Laravel throws NotFoundHttpException out of the router itself
// before any route's middleware can run.
Route::fallback(fn () => abort(404));

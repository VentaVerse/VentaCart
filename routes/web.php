<?php

use App\Http\Controllers\Admin\AddressLookupController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ApiSyncLogController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\DphController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\FooterColumnController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\NavMenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\PaymentSettingController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingSettingController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Admin\StockNotificationController as AdminStockNotificationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
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
use App\Http\Controllers\Storefront\HitPayWebhookController;
use App\Http\Controllers\Storefront\DphWebhookController;
use App\Http\Controllers\Storefront\AddressLookupController as StorefrontAddressLookupController;
use App\Http\Controllers\Storefront\BrandController as StorefrontBrandController;
use App\Http\Controllers\Storefront\RecentlyViewedController;
use App\Http\Controllers\Storefront\ReviewController as StorefrontReviewController;
use App\Http\Controllers\Storefront\StockNotificationController as StorefrontStockNotificationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Illuminate\Support\Facades\Route;

// Storefront
Route::get('/', [StorefrontHomeController::class, 'index'])->name('home');
Route::get('/search/autocomplete', [StorefrontSearchController::class, 'autocomplete'])->name('search.autocomplete');
Route::get('/search', [StorefrontSearchController::class, 'results'])->name('search.results');
Route::post('/notify-stock', [StorefrontStockNotificationController::class, 'store'])->name('notify-stock.store');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/recently-viewed', [RecentlyViewedController::class, 'index'])->name('recently-viewed');
Route::get('/brand/{brand:slug}', [StorefrontBrandController::class, 'show'])->name('brand.show');
Route::get('/category/{category:slug}', [StorefrontCategoryController::class, 'show'])->name('category.show');
Route::get('/product/{product:slug}', [StorefrontProductController::class, 'show'])->name('product.show');
Route::get('/blog', [StorefrontBlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [StorefrontBlogController::class, 'show'])->name('blog.show');

// Reviews (public — accessed via token link)
Route::get('/review/{token}', [StorefrontReviewController::class, 'show'])->name('review.submit');
Route::post('/review/{token}', [StorefrontReviewController::class, 'store'])->name('review.store');

// Cart
Route::get('/cart', [StorefrontCartController::class, 'index'])->name('cart.index');
Route::get('/cart/mini', [StorefrontCartController::class, 'mini'])->name('cart.mini');
Route::post('/cart/add', [StorefrontCartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{cartItem}', [StorefrontCartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartItem}', [StorefrontCartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart', [StorefrontCartController::class, 'clear'])->name('cart.clear');
Route::post('/cart/shipping-estimate', [StorefrontCartController::class, 'estimateShipping'])->name('cart.estimate-shipping');

// HitPay webhook (no CSRF, no auth — signature-verified)
Route::post('/hitpay/webhook', HitPayWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('hitpay.webhook');

// DPH webhook (no CSRF, no auth — secret-validated via query param)
Route::match(['get', 'post'], '/dph/webhook', DphWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('dph.webhook');

// Checkout (authenticated customers)
Route::middleware('customer.auth')->group(function () {
    Route::get('/checkout', [StorefrontCheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/address', [StorefrontCheckoutController::class, 'saveAddress'])->name('checkout.address.save');
    Route::post('/checkout/shipping-rates', [StorefrontCheckoutController::class, 'getShippingRates'])->name('checkout.shipping-rates');
    Route::post('/checkout/place', [StorefrontCheckoutController::class, 'placeOrder'])->name('checkout.place');
    Route::get('/checkout/confirmation/{order}', [StorefrontCheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::get('/checkout/hitpay/return', [StorefrontCheckoutController::class, 'hitpayReturn'])->name('checkout.hitpay.return');
});

// Customer Auth (public)
Route::prefix('account')->name('customer.')->group(function () {
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
    // Country-specific routes (e.g. ph-provinces, ph-cities) are provided by their respective address extensions
});

// Customer Account (authenticated)
Route::prefix('account')->name('account.')->middleware('customer.auth')->group(function () {
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

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::get('/brands/search', [BrandController::class, 'search'])->name('brands.search');
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::get('/customers/{customer}/impersonate', [ImpersonationController::class, 'start'])->name('customers.impersonate');
    Route::get('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::resource('customer-groups', CustomerGroupController::class)->except(['show']);

    // Address Lookups
    Route::get('/address/countries', [AddressLookupController::class, 'countries'])->name('address.countries');
    Route::get('/address/zones', [AddressLookupController::class, 'zones'])->name('address.zones');
    // PH-specific routes (ph-provinces, ph-cities, ph-districts, ph-barangays) are provided by the PhilippineAddress extension

    // Orders
    Route::get('/orders/search-products', [OrderController::class, 'searchProducts'])->name('orders.search-products');
    Route::get('/orders/search-customers', [OrderController::class, 'searchCustomers'])->name('orders.search-customers');
    Route::post('/orders/{order}/history', [OrderController::class, 'addHistory'])->name('orders.history.store');
    Route::delete('/orders/{order}/history/{history}', [OrderController::class, 'deleteHistory'])->name('orders.history.destroy');
    Route::post('/orders/{order}/tracking', [OrderController::class, 'updateTracking'])->name('orders.update-tracking');
    Route::resource('orders', OrderController::class);

    // Order Statuses
    Route::get('/order-statuses', [\App\Http\Controllers\Admin\OrderStatusController::class, 'index'])->name('order-statuses.index');
    Route::post('/order-statuses', [\App\Http\Controllers\Admin\OrderStatusController::class, 'store'])->name('order-statuses.store');
    Route::put('/order-statuses/{orderStatus}', [\App\Http\Controllers\Admin\OrderStatusController::class, 'update'])->name('order-statuses.update');
    Route::delete('/order-statuses/{orderStatus}', [\App\Http\Controllers\Admin\OrderStatusController::class, 'destroy'])->name('order-statuses.destroy');

    // Shipping Couriers
    Route::get('/shipping-couriers', [\App\Http\Controllers\Admin\ShippingCourierController::class, 'index'])->name('shipping-couriers.index');
    Route::post('/shipping-couriers', [\App\Http\Controllers\Admin\ShippingCourierController::class, 'store'])->name('shipping-couriers.store');
    Route::put('/shipping-couriers/{shippingCourier}', [\App\Http\Controllers\Admin\ShippingCourierController::class, 'update'])->name('shipping-couriers.update');
    Route::delete('/shipping-couriers/{shippingCourier}', [\App\Http\Controllers\Admin\ShippingCourierController::class, 'destroy'])->name('shipping-couriers.destroy');

    // Shipments (DPH)
    Route::get('/orders/{order}/shipment/rates', [ShipmentController::class, 'getAvailableRates'])->name('orders.shipment.rates');
    Route::post('/orders/{order}/shipment/book', [ShipmentController::class, 'book'])->name('orders.shipment.book');
    Route::post('/orders/{order}/shipment/cancel', [ShipmentController::class, 'cancel'])->name('orders.shipment.cancel');

    // Product Image Manager
    Route::post('/products/images/upload', [ProductImageController::class, 'upload'])->name('products.images.upload');
    Route::post('/products/images/import-url', [ProductImageController::class, 'importUrl'])->name('products.images.import-url');
    Route::get('/products/images/browse', [ProductImageController::class, 'browse'])->name('products.images.browse');
    Route::post('/products/images/upload-to-catalog', [ProductImageController::class, 'uploadToCatalog'])->name('products.images.upload-to-catalog');
    Route::post('/products/images/delete', [ProductImageController::class, 'delete'])->name('products.images.delete');

    // Media Manager
    Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    Route::get('/media/folders', [MediaController::class, 'folders'])->name('media.folders');
    Route::post('/media/folders', [MediaController::class, 'createFolder'])->name('media.folders.store');
    Route::put('/media/folders/{folder}', [MediaController::class, 'updateFolder'])->name('media.folders.update');
    Route::delete('/media/folders/{folder}', [MediaController::class, 'deleteFolder'])->name('media.folders.destroy');
    Route::get('/media/list', [MediaController::class, 'list'])->name('media.list');
    Route::post('/media/upload', [MediaController::class, 'store'])->name('media.upload');
    Route::delete('/media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/media/cleanup-temp', [MediaController::class, 'cleanupTemp'])->name('media.cleanup-temp');

    // Theme Module
    Route::get('/theme', [ThemeController::class, 'index'])->name('theme.index');
    Route::post('/theme/settings', [ThemeController::class, 'updateSettings'])->name('theme.settings.update');
    Route::post('/theme/upload-logo', [ThemeController::class, 'uploadLogo'])->name('theme.upload-logo');
    Route::delete('/theme/remove-logo', [ThemeController::class, 'removeLogo'])->name('theme.remove-logo');
    Route::post('/theme/upload-favicon', [ThemeController::class, 'uploadFavicon'])->name('theme.upload-favicon');
    Route::delete('/theme/remove-favicon', [ThemeController::class, 'removeFavicon'])->name('theme.remove-favicon');
    Route::post('/theme/switch', [ThemeController::class, 'switchTheme'])->name('theme.switch');
    Route::post('/theme/theme-settings', [ThemeController::class, 'updateThemeSettings'])->name('theme.update-theme-settings');

    // Homepage Sections
    Route::post('/theme/sections', [HomepageSectionController::class, 'store'])->name('theme.sections.store');
    Route::put('/theme/sections/{section}', [HomepageSectionController::class, 'update'])->name('theme.sections.update');
    Route::delete('/theme/sections/{section}', [HomepageSectionController::class, 'destroy'])->name('theme.sections.destroy');
    Route::post('/theme/sections/reorder', [HomepageSectionController::class, 'reorder'])->name('theme.sections.reorder');

    // Nav Menu
    Route::post('/theme/nav', [NavMenuController::class, 'store'])->name('theme.nav.store');
    Route::put('/theme/nav/{item}', [NavMenuController::class, 'update'])->name('theme.nav.update');
    Route::delete('/theme/nav/{item}', [NavMenuController::class, 'destroy'])->name('theme.nav.destroy');
    Route::post('/theme/nav/reorder', [NavMenuController::class, 'reorder'])->name('theme.nav.reorder');

    // Footer Columns
    Route::post('/theme/footer', [FooterColumnController::class, 'store'])->name('theme.footer.store');
    Route::put('/theme/footer/{column}', [FooterColumnController::class, 'update'])->name('theme.footer.update');
    Route::delete('/theme/footer/{column}', [FooterColumnController::class, 'destroy'])->name('theme.footer.destroy');
    Route::post('/theme/footer/reorder', [FooterColumnController::class, 'reorder'])->name('theme.footer.reorder');

    // Currencies
    Route::post('/theme/currencies', [CurrencyController::class, 'store'])->name('theme.currencies.store');
    Route::put('/theme/currencies/{currency}', [CurrencyController::class, 'update'])->name('theme.currencies.update');
    Route::delete('/theme/currencies/{currency}', [CurrencyController::class, 'destroy'])->name('theme.currencies.destroy');
    Route::post('/theme/currencies/fetch-rates', [CurrencyController::class, 'fetchRates'])->name('theme.currencies.fetch-rates');

    // Blog
    Route::resource('blog', BlogPostController::class)->except(['show']);
    Route::resource('blog-categories', BlogCategoryController::class)->except(['show']);
    Route::resource('pages', PageController::class)->except(['show']);

    // Tags
    Route::resource('tags', TagController::class)->except(['show']);

    // Settings
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');
    Route::post('/settings/test-dph', [SettingController::class, 'testDphConnection'])->name('settings.test-dph');
    Route::post('/settings/test-dhl', [SettingController::class, 'testDhl'])->name('settings.test-dhl');
    Route::post('/settings/generate-dph-secret', [SettingController::class, 'generateDphWebhookSecret'])->name('settings.generate-dph-secret');
    Route::post('/settings/generate-erp-token', [SettingController::class, 'generateErpToken'])->name('settings.generate-erp-token');
    Route::post('/settings/upload-gsc-credentials', [SettingController::class, 'uploadGscCredentials'])->name('settings.upload-gsc-credentials');

    // API Sync Logs
    Route::get('/api-sync-logs', [ApiSyncLogController::class, 'index'])->name('api-sync-logs.index');

    Route::get('shipping-zones', [ShippingZoneController::class, 'index'])->name('shipping-zones.index');
    Route::get('shipping-zones/{geoZone}/edit', [ShippingZoneController::class, 'edit'])->name('shipping-zones.edit');
    Route::put('shipping-zones/{geoZone}', [ShippingZoneController::class, 'update'])->name('shipping-zones.update');


    // Stock Notifications
    Route::get('/marketing', [\App\Http\Controllers\Admin\MarketingController::class, 'index'])->name('marketing.index');
    Route::get('/stock-notifications', [AdminStockNotificationController::class, 'index'])->name('stock-notifications.index');
    Route::delete('/stock-notifications/{stockNotification}', [AdminStockNotificationController::class, 'destroy'])->name('stock-notifications.destroy');
    Route::post('/stock-notifications/bulk-delete', [AdminStockNotificationController::class, 'bulkDelete'])->name('stock-notifications.bulk-delete');
    Route::post('/stock-notifications/template', [AdminStockNotificationController::class, 'updateTemplate'])->name('stock-notifications.update-template');
    Route::post('/stock-notifications/test-email', [AdminStockNotificationController::class, 'sendTest'])->name('stock-notifications.send-test');

    // Reviews
    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/{review}', [AdminReviewController::class, 'show'])->name('reviews.show');
    Route::post('/reviews/{review}/approve', [AdminReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/reviews/{review}/reject', [AdminReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    // Email Templates
    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('/email-templates/{emailTemplate}/toggle', [EmailTemplateController::class, 'toggleActive'])->name('email-templates.toggle');
    Route::post('/email-templates/{emailTemplate}/test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.send-test');

    // SEO
    Route::get('/seo', [SeoController::class, 'index'])->name('seo.index');
    Route::post('/seo/generate', [SeoController::class, 'generateNow'])->name('seo.generate');
    Route::post('/seo/suggestions/{suggestion}/accept', [SeoController::class, 'accept'])->name('seo.accept');
    Route::post('/seo/suggestions/{suggestion}/reject', [SeoController::class, 'reject'])->name('seo.reject');
    Route::post('/seo/suggestions/bulk-accept', [SeoController::class, 'bulkAccept'])->name('seo.bulk-accept');

    Route::post('/dph/mapping', [DphController::class, 'updateMapping'])->name('dph.update-mapping');
    Route::post('/dph/execute', [DphController::class, 'execute'])->name('dph.execute');
    Route::get('/dph/logs', [DphController::class, 'logs'])->name('dph.logs');

    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Role Permissions
    Route::get('/permissions', [RolePermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions', [RolePermissionController::class, 'update'])->name('permissions.update');

    // Localization
    Route::get('/localization', [\App\Http\Controllers\Admin\LocalizationController::class, 'index'])->name('localization.index');
    Route::post('/localization/countries/{country}/toggle', [\App\Http\Controllers\Admin\LocalizationController::class, 'toggleCountry'])->name('localization.toggle-country');
    Route::put('/localization/countries/{country}', [\App\Http\Controllers\Admin\LocalizationController::class, 'updateCountry'])->name('localization.update-country');
    Route::post('/localization/zones/{zone}/toggle', [\App\Http\Controllers\Admin\LocalizationController::class, 'toggleZone'])->name('localization.toggle-zone');
    Route::post('/localization/zones', [\App\Http\Controllers\Admin\LocalizationController::class, 'storeZone'])->name('localization.store-zone');
    Route::put('/localization/zones/{zone}', [\App\Http\Controllers\Admin\LocalizationController::class, 'updateZone'])->name('localization.update-zone');
    Route::delete('/localization/zones/{zone}', [\App\Http\Controllers\Admin\LocalizationController::class, 'destroyZone'])->name('localization.destroy-zone');
    Route::post('/localization/geo-zones', [\App\Http\Controllers\Admin\LocalizationController::class, 'storeGeoZone'])->name('localization.store-geo-zone');
    Route::get('/localization/geo-zones/{geoZone}/edit', [\App\Http\Controllers\Admin\LocalizationController::class, 'editGeoZone'])->name('localization.edit-geo-zone');
    Route::put('/localization/geo-zones/{geoZone}', [\App\Http\Controllers\Admin\LocalizationController::class, 'updateGeoZone'])->name('localization.update-geo-zone');
    Route::post('/localization/geo-zones/{geoZone}/entries', [\App\Http\Controllers\Admin\LocalizationController::class, 'syncGeoZoneEntries'])->name('localization.sync-geo-zone-entries');
    Route::post('/localization/geo-zones/{geoZone}/toggle', [\App\Http\Controllers\Admin\LocalizationController::class, 'toggleGeoZone'])->name('localization.toggle-geo-zone');
    Route::delete('/localization/geo-zones/{geoZone}', [\App\Http\Controllers\Admin\LocalizationController::class, 'destroyGeoZone'])->name('localization.destroy-geo-zone');

    // Payment Settings (standalone)
    Route::get('/payment-settings', [PaymentSettingController::class, 'index'])->name('payment-settings.index');

    // Bank Accounts
    Route::post('bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::get('bank-accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->name('bank-accounts.edit');
    Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
    Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

    // Payment Restrictions
    Route::resource('payment-restrictions', \App\Http\Controllers\Admin\PaymentRestrictionController::class)->except(['show'])->parameters(['payment-restrictions' => 'paymentRestriction']);
    Route::post('payment-restrictions/{paymentRestriction}/toggle', [\App\Http\Controllers\Admin\PaymentRestrictionController::class, 'toggleActive'])->name('payment-restrictions.toggle');

    // Shipping Settings (standalone)
    Route::get('/shipping-settings', [ShippingSettingController::class, 'index'])->name('shipping-settings.index');
    Route::resource('free-shipping-rules', \App\Http\Controllers\Admin\FreeShippingRuleController::class)->except(['show']);
    Route::post('free-shipping-rules/{freeShippingRule}/toggle', [\App\Http\Controllers\Admin\FreeShippingRuleController::class, 'toggleActive'])->name('free-shipping-rules.toggle');

    // Flat Rate CRUD
    Route::get('flat-rates/create', [\App\Http\Controllers\Admin\FlatRateController::class, 'create'])->name('flat-rates.create');
    Route::post('flat-rates', [\App\Http\Controllers\Admin\FlatRateController::class, 'store'])->name('flat-rates.store');
    Route::get('flat-rates/{flatRate}/edit', [\App\Http\Controllers\Admin\FlatRateController::class, 'edit'])->name('flat-rates.edit');
    Route::put('flat-rates/{flatRate}', [\App\Http\Controllers\Admin\FlatRateController::class, 'update'])->name('flat-rates.update');
    Route::delete('flat-rates/{flatRate}', [\App\Http\Controllers\Admin\FlatRateController::class, 'destroy'])->name('flat-rates.destroy');

    // Weight-Based Method CRUD
    Route::get('weight-based-methods/create', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'create'])->name('weight-based-methods.create');
    Route::post('weight-based-methods', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'store'])->name('weight-based-methods.store');
    Route::get('weight-based-methods/{weightBasedMethod}/edit', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'edit'])->name('weight-based-methods.edit');
    Route::put('weight-based-methods/{weightBasedMethod}', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'update'])->name('weight-based-methods.update');
    Route::delete('weight-based-methods/{weightBasedMethod}', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'destroy'])->name('weight-based-methods.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/sitemap', [\App\Http\Controllers\Storefront\SitemapController::class, 'index'])->name('sitemap');

require __DIR__.'/auth.php';

// CMS Pages (catch-all — must be absolutely last, after auth routes)
Route::get('/{slug}', [\App\Http\Controllers\Storefront\PageController::class, 'show'])
    ->name('page.show')
    ->where('slug', '^(?!admin|login|register|forgot-password|reset-password|confirm-password|verify-email|logout)[a-z0-9][a-z0-9-]*$');

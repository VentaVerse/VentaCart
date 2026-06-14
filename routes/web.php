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
use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\EmailTemplateController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\FooterColumnController;
use App\Http\Controllers\Admin\HomepageSectionController;
use App\Http\Controllers\Admin\NavMenuController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\PaymentSettingController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\ShippingSettingController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CouponController;
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
use App\Http\Controllers\Storefront\CouponController as StorefrontCouponController;
use App\Http\Controllers\Storefront\HitPayWebhookController;
use App\Http\Controllers\Storefront\AddressLookupController as StorefrontAddressLookupController;
use App\Http\Controllers\Storefront\DeepAddressLookupController;
use App\Http\Controllers\Storefront\BrandController as StorefrontBrandController;
use App\Http\Controllers\Storefront\RecentlyViewedController;
use App\Http\Controllers\Storefront\ReviewController as StorefrontReviewController;
use App\Http\Controllers\Storefront\StockNotificationController as StorefrontStockNotificationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Illuminate\Support\Facades\Route;

// Storefront
Route::get('/', [StorefrontHomeController::class, 'index'])->name('home');
Route::get('/search/autocomplete', [StorefrontSearchController::class, 'autocomplete'])->name('search.autocomplete');
Route::get('/search', [StorefrontSearchController::class, 'results'])->middleware('noindex:follow')->name('search.results');
Route::post('/notify-stock', [StorefrontStockNotificationController::class, 'store'])->name('notify-stock.store');
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

// HitPay webhook (no CSRF, no auth — signature-verified)
Route::post('/hitpay/webhook', HitPayWebhookController::class)
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('hitpay.webhook');

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

Route::middleware(['auth', 'admin.session.cap'])->prefix(config('admin.prefix', 'admin'))->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('products/quick-create', [ProductController::class, 'quickCreate'])->name('products.quick-create');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::get('/brands/search', [BrandController::class, 'search'])->name('brands.search');
    Route::post('/brands/quick-create', [BrandController::class, 'quickCreate'])->name('brands.quick-create');
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::get('/customers/{customer}/impersonate', [ImpersonationController::class, 'start'])->name('customers.impersonate');
    Route::get('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::resource('customer-groups', CustomerGroupController::class)->except(['show']);

    // Address Lookups
    Route::get('/address/countries', [AddressLookupController::class, 'countries'])->name('address.countries');
    Route::get('/address/zones', [AddressLookupController::class, 'zones'])->name('address.zones');
    // Deep-address lookups (e.g. PH city/barangay) are read on-demand from bundled files; routes added when the deep tier is enabled.

    // Orders
    Route::get('/orders/search-products', [OrderController::class, 'searchProducts'])->name('orders.search-products');
    Route::get('/orders/search-customers', [OrderController::class, 'searchCustomers'])->name('orders.search-customers');
    Route::post('/orders/available-shipping', [OrderController::class, 'availableShipping'])->name('orders.available-shipping');
    Route::post('/orders/available-payments', [OrderController::class, 'availablePayments'])->name('orders.available-payments');
    Route::post('/orders/validate-coupon', [OrderController::class, 'validateCoupon'])->name('orders.validate-coupon');
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
    Route::post('/theme/revert-colors', [ThemeController::class, 'revertThemeColors'])->name('theme.revert-colors');
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
    Route::post('/theme/footer/bulk-update', [FooterColumnController::class, 'bulkUpdate'])->name('theme.footer.bulk-update');
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
    Route::post('/settings/test-dhl', [SettingController::class, 'testDhl'])->name('settings.test-dhl');
    Route::post('/settings/generate-erp-token', [SettingController::class, 'generateErpToken'])->name('settings.generate-erp-token');
    Route::post('/settings/upload-gsc-credentials', [SettingController::class, 'uploadGscCredentials'])->name('settings.upload-gsc-credentials');

    // Automations (Settings > Automations tab — DB-managed scheduled tasks)
    Route::get('/automations', [AutomationController::class, 'index'])->name('automations.index');
    Route::patch('/automations/{automation}', [AutomationController::class, 'update'])->name('automations.update');
    Route::post('/automations/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automations.toggle');
    Route::post('/automations/{automation}/run', [AutomationController::class, 'runNow'])->name('automations.runNow');

    // API Sync Logs
    Route::get('/api-sync-logs', [ApiSyncLogController::class, 'index'])->name('api-sync-logs.index');
    Route::delete('/api-sync-logs', [ApiSyncLogController::class, 'destroyAll'])->name('api-sync-logs.destroy-all');

    Route::get('shipping-zones', [ShippingZoneController::class, 'index'])->name('shipping-zones.index');
    Route::get('shipping-zones/{geoZone}/edit', [ShippingZoneController::class, 'edit'])->name('shipping-zones.edit');
    Route::put('shipping-zones/{geoZone}', [ShippingZoneController::class, 'update'])->name('shipping-zones.update');


    // Modules
    Route::get('/modules', [\App\Http\Controllers\Admin\ModuleController::class, 'index'])->name('modules.index');

    // Abandoned Carts
    Route::get('/abandoned-carts', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
    Route::get('/abandoned-carts/rules', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'rules'])->name('abandoned-carts.rules');
    Route::get('/abandoned-carts/rules/create', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'createRule'])->name('abandoned-carts.rules.create');
    Route::post('/abandoned-carts/rules', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'storeRule'])->name('abandoned-carts.rules.store');
    Route::get('/abandoned-carts/rules/{rule}/edit', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'editRule'])->name('abandoned-carts.rules.edit');
    Route::put('/abandoned-carts/rules/{rule}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'updateRule'])->name('abandoned-carts.rules.update');
    Route::delete('/abandoned-carts/rules/{rule}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'destroyRule'])->name('abandoned-carts.rules.destroy');
    Route::get('/abandoned-carts/templates', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'templates'])->name('abandoned-carts.templates');
    Route::get('/abandoned-carts/templates/create', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'createTemplate'])->name('abandoned-carts.templates.create');
    Route::post('/abandoned-carts/templates', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'storeTemplate'])->name('abandoned-carts.templates.store');
    Route::get('/abandoned-carts/templates/{emailTemplate}/edit', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'editTemplate'])->name('abandoned-carts.templates.edit');
    Route::put('/abandoned-carts/templates/{emailTemplate}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'updateTemplate'])->name('abandoned-carts.templates.update');
    Route::post('/abandoned-carts/templates/{emailTemplate}/toggle', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'toggleTemplate'])->name('abandoned-carts.templates.toggle');
    Route::post('/abandoned-carts/templates/{emailTemplate}/test', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'testTemplate'])->name('abandoned-carts.templates.test');
    Route::delete('/abandoned-carts/templates/{emailTemplate}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'destroyTemplate'])->name('abandoned-carts.templates.destroy');
    Route::get('/abandoned-carts/notifications', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'notifications'])->name('abandoned-carts.notifications');
    Route::get('/abandoned-carts/recovered', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'recovered'])->name('abandoned-carts.recovered');
    Route::get('/abandoned-carts/settings', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'settings'])->name('abandoned-carts.settings');
    Route::post('/abandoned-carts/settings', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'updateSettings'])->name('abandoned-carts.settings.update');
    Route::get('/abandoned-carts/{cart}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'show'])->name('abandoned-carts.show');
    Route::delete('/abandoned-carts/{cart}', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'destroy'])->name('abandoned-carts.destroy');
    Route::post('/abandoned-carts/mass-delete', [\App\Http\Controllers\Admin\AbandonedCartController::class, 'massDelete'])->name('abandoned-carts.mass-delete');

    // Stock Notifications
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

    // Coupons
    Route::resource('coupons', CouponController::class)->except(['show']);
    Route::get('/coupons/search/products', [CouponController::class, 'searchProducts'])->name('coupons.search-products');
    Route::get('/coupons/search/categories', [CouponController::class, 'searchCategories'])->name('coupons.search-categories');
    Route::get('/coupons/search/brands', [CouponController::class, 'searchBrands'])->name('coupons.search-brands');

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

    // SEO Extension
    Route::prefix('extensions/seo')->name('extensions.seo.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'dashboard'])->name('dashboard');
        Route::get('/search-console', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'searchConsole'])->name('search-console');
        Route::get('/strategy', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'strategy'])->name('strategy');
        Route::get('/review', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'review'])->name('review');
        Route::get('/slugs', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'slugs'])->name('slugs');
        Route::post('/slugs/update', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'updateSlug'])->name('update-slug');
        Route::post('/strategy', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'saveStrategy'])->name('save-strategy');
        Route::get('/settings', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'settings'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'saveSettings'])->name('save-settings');
        Route::post('/settings/upload-gsc', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'uploadGsc'])->name('upload-gsc');
        Route::post('/settings/test-claude', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'testClaude'])->name('test-claude');
        Route::post('/settings/test-gsc', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'testGsc'])->name('test-gsc');
        Route::post('/generate', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'generate'])->name('generate');
        Route::post('/optimize', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'optimize'])->name('optimize');
        Route::post('/suggestions/{suggestion}/accept', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'accept'])->name('accept');
        Route::post('/suggestions/{suggestion}/reject', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'reject'])->name('reject');
        Route::post('/suggestions/bulk-accept', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'bulkAccept'])->name('bulk-accept');

        // Redirect manager — admin-managed source→destination 301/302/410 rules
        Route::get('/redirects', [\App\Http\Controllers\Admin\SeoRedirectController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [\App\Http\Controllers\Admin\SeoRedirectController::class, 'store'])->name('redirects.store');
        Route::put('/redirects/{redirect}', [\App\Http\Controllers\Admin\SeoRedirectController::class, 'update'])->name('redirects.update');
        Route::delete('/redirects/{redirect}', [\App\Http\Controllers\Admin\SeoRedirectController::class, 'destroy'])->name('redirects.destroy');

        // 404 monitor — recently-seen missing URLs with quick-redirect action
        Route::get('/not-found', [\App\Http\Controllers\Admin\SeoNotFoundController::class, 'index'])->name('not-found.index');
        Route::delete('/not-found/{log}', [\App\Http\Controllers\Admin\SeoNotFoundController::class, 'destroy'])->name('not-found.destroy');
        Route::post('/not-found/clear', [\App\Http\Controllers\Admin\SeoNotFoundController::class, 'clear'])->name('not-found.clear');

        Route::get('/{type}', [\App\Http\Controllers\Admin\Extensions\SeoExtensionController::class, 'entities'])->name('entities')->where('type', 'products|categories|brands|blog|pages');
    });

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
    Route::put('weight-based-methods/bulk', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'bulkUpdate'])->name('weight-based-methods.bulk-update');
    Route::get('weight-based-methods/{weightBasedMethod}/edit', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'edit'])->name('weight-based-methods.edit');
    Route::put('weight-based-methods/{weightBasedMethod}', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'update'])->name('weight-based-methods.update');
    Route::post('weight-based-methods/{weightBasedMethod}/duplicate', [\App\Http\Controllers\Admin\WeightBasedMethodController::class, 'duplicate'])->name('weight-based-methods.duplicate');
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
    ->where('slug', '^(?!' . config('admin.prefix', 'admin') . ')[a-z0-9][a-z0-9-]*$');

// Final fallback — matches any request no other route claimed (including
// multi-segment paths the /{slug} rule rejects). Putting it inside the web
// group lets HandleRedirects look up the path *before* we 404, and lets
// LogNotFound record the miss *after*. Without this, multi-segment URLs
// (e.g. /brands/foo, /guitars/bar) would skip both middlewares entirely
// because Laravel throws NotFoundHttpException out of the router itself
// before any route's middleware can run.
Route::fallback(fn () => abort(404));

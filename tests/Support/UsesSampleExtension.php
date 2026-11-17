<?php

namespace Tests\Support;

use App\Services\ExtensionManager;

/**
 * The fixture extension under tests/fixtures/extensions/sample-extension,
 * installed and booted for the length of a test. It uses every storefront
 * hook, so a test can prove one end to end without a real extension.
 *
 * The kernel is pointed at the fixtures folder, so the store's own
 * extensions are not discovered while it is in use.
 */
trait UsesSampleExtension
{
    protected function installSampleExtension(): ExtensionManager
    {
        config(['extensions.path' => base_path('tests/fixtures/extensions')]);
        app()->forgetInstance(ExtensionManager::class);

        $manager = app(ExtensionManager::class);
        $manager->install('sample-extension');
        $manager->bootEnabled();

        return $manager;
    }

    /** Switch it off, as the merchant would. Its provider has booted already, so the hooks it registered are taken away by hand. */
    protected function switchOffSampleExtension(): void
    {
        app(ExtensionManager::class)->disable('sample-extension');
        \Tests\TestCase::forgetStorefrontHooks();
    }
}

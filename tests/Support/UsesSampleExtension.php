<?php

namespace Tests\Support;

use App\Services\ExtensionManager;

/**
 * The fixture extension under tests/fixtures/extensions/sample-extension,
 * installed and booted for the length of a test. It uses every storefront
 * hook, so a test can prove one end to end without a real extension.
 *
 * It is linked into the extensions folder beside the store's own, as a
 * merchant's upload would sit, and the link goes when the test ends.
 */
trait UsesSampleExtension
{
    protected function linkSampleExtension(): string
    {
        $link = base_path('extensions/sample-extension');
        if (! is_link($link) && ! file_exists($link)) {
            symlink(base_path('tests/fixtures/extensions/sample-extension'), $link);
        }
        $this->beforeApplicationDestroyed(function () use ($link) {
            if (is_link($link)) {
                unlink($link);
            }
        });
        app()->forgetInstance(ExtensionManager::class);

        return $link;
    }

    protected function installSampleExtension(): ExtensionManager
    {
        $this->linkSampleExtension();

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

    /** Switch it back on. Its provider is still booted, so it registers its hooks again by hand. */
    protected function switchOnSampleExtension(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->enable('sample-extension');
        (new \Tests\Fixtures\Extensions\SampleExtension\SampleExtensionServiceProvider(app()))->registerHooks();
    }

    /** Uninstall it and take its folder away, as removing the package does. */
    protected function removeSampleExtension(): void
    {
        $manager = app(ExtensionManager::class);
        $manager->uninstall('sample-extension');
        $link = base_path('extensions/sample-extension');
        if (is_link($link)) {
            unlink($link);
        }
        \Tests\TestCase::forgetStorefrontHooks();
        app()->forgetInstance(ExtensionManager::class);
    }
}

<?php

namespace Tests\Fixtures\Extensions\Demo;

use Illuminate\Support\ServiceProvider;

/**
 * Trivial provider used only by ExtensionLifecycleTest to prove
 * ExtensionManager::bootEnabled() actually registers + boots providers for
 * enabled extensions (and skips disabled ones). Binding a marker into the
 * container is the cheapest observable signal that boot() ran.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->instance('extensions.demo.booted', true);
    }
}

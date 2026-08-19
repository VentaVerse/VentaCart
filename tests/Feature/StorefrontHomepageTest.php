<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop's front door opens.
 *
 * This replaces the framework's stock ExampleTest, which asserted the same
 * thing but ran without a database. Venta's homepage reads settings, nav and
 * theme out of one, so that test could only ever fail with "no such table:
 * settings" and it did, on every run, teaching everybody to ignore a red line.
 *
 * Deliberately asserted against an EMPTY catalogue: a storefront with no
 * products yet is exactly the state a fresh install is in, and a homepage that
 * needs stock to render is a homepage that white-screens on day one.
 */
class StorefrontHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_for_a_visitor(): void
    {
        $this->get('/')->assertOk();
    }
}

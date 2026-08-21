<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stage keeps its column when the editor speaks.
 *
 * The live region was a direct child of the .v-lay grid carrying
 * grid-column: 1 / -1. The moment it appeared it claimed a whole row and the
 * auto-placed stage below it fell into the rail's 176px track, so the entire
 * editor collapsed to a narrow strip. It was invisible while the region only
 * spoke for moves and rescues, and fired on every add once the commonest
 * actions started announcing.
 *
 * A stylesheet cannot be asserted here, but the nesting that makes the
 * stylesheet unnecessary can be: the announcement lives inside the stage, so
 * it is not a grid item at all.
 */
class LayoutStageHoldsItsColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_live_region_is_inside_the_stage_not_beside_it(): void
    {
        $html = $this->get(route('admin.theme.layout', ['page' => 'home']))->assertOk()->getContent();

        $stage = strpos($html, 'class="v-lay__stage"');
        $said = strpos($html, 'class="v-lay__said"');

        $this->assertNotFalse($stage);
        $this->assertNotFalse($said);
        $this->assertGreaterThan($stage, $said, 'the announcement must render inside the stage, not as a sibling of it');
    }

    public function test_the_announcement_is_still_a_live_region(): void
    {
        $this->get(route('admin.theme.layout', ['page' => 'home']))
            ->assertOk()
            ->assertSee('role="status" aria-live="polite"', false);
    }
}

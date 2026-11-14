<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stage keeps its column when the editor speaks.
 *
 * A live region that appears as a grid item of its own claims a whole grid
 * row, and the auto-placed stage below it falls into the rail's narrow track.
 * The announcement therefore lives in the save bar, which is always present
 * and closes the grid, so nothing appears in front of the stage.
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

    public function test_the_live_region_sits_in_the_save_bar_after_the_stage(): void
    {
        $html = $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->getContent();

        $stage = strpos($html, 'class="v-lay__stage"');
        $bar = strpos($html, 'class="v-lay__savebar"');
        $said = strpos($html, 'class="v-lay__said"');

        $this->assertNotFalse($stage);
        $this->assertNotFalse($bar);
        $this->assertGreaterThan($stage, $bar, 'the save bar closes the grid, after the stage');
        $this->assertGreaterThan($bar, $said, 'the announcement is inside the save bar');
    }

    public function test_the_announcement_is_still_a_live_region(): void
    {
        $this->get(route('admin.layout', ['page' => 'home']))
            ->assertOk()
            ->assertSee('role="status" aria-live="polite"', false);
    }
}

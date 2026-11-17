<?php

namespace Tests\Feature\Extensions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;

/**
 * The fixture helper takes away only the link it made itself: a link made
 * by hand to look at the fixture outlives every test run.
 */
class UsesSampleExtensionTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;

    private string $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->link = base_path('extensions/sample-extension');
    }

    /** Run the teardown callbacks added since $from, as the end of the test would. */
    private function runTeardownFrom(int $from): void
    {
        foreach (array_slice($this->beforeApplicationDestroyedCallbacks, $from) as $callback) {
            $callback();
        }
        $this->beforeApplicationDestroyedCallbacks = array_slice($this->beforeApplicationDestroyedCallbacks, 0, $from);
    }

    public function test_a_link_it_made_goes_when_the_test_ends(): void
    {
        if (is_link($this->link) || file_exists($this->link)) {
            $this->markTestSkipped('The fixture is linked by hand here.');
        }
        $from = count($this->beforeApplicationDestroyedCallbacks);

        $this->linkSampleExtension();
        $this->assertTrue(is_link($this->link));

        $this->runTeardownFrom($from);
        $this->assertFalse(is_link($this->link));
    }

    public function test_a_link_made_by_hand_stays(): void
    {
        $byHand = ! is_link($this->link) && ! file_exists($this->link);
        if ($byHand) {
            symlink(base_path('tests/fixtures/extensions/sample-extension'), $this->link);
        }

        try {
            $from = count($this->beforeApplicationDestroyedCallbacks);
            $this->linkSampleExtension();
            $this->runTeardownFrom($from);
            $this->assertTrue(is_link($this->link), 'The link made by hand is still there.');

            $from = count($this->beforeApplicationDestroyedCallbacks);
            $this->installSampleExtension();
            $this->removeSampleExtension();
            $this->assertFalse(is_link($this->link), 'Removing the extension takes it out of sight for the test.');
            $this->runTeardownFrom($from);
            $this->assertTrue(is_link($this->link), 'And puts it back when the test ends.');
        } finally {
            if ($byHand && is_link($this->link)) {
                unlink($this->link);
            }
        }
    }
}

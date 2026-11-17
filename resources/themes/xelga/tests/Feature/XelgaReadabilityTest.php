<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Settings\ContrastCheck;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A color scheme whose text would be hard to read saves as a draft with a
 * warning, and Publish refuses it, naming the scheme and the pair.
 */
class XelgaReadabilityTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    private function faintSettings(): array
    {
        $settings = $this->defaults('settings');
        // Pale gray text on the pale "Gallery white" background.
        $settings['schemes'][1]['colors']['text'] = '#c8cccf';

        return $settings;
    }

    public function test_the_shipped_schemes_are_readable(): void
    {
        $this->assertSame([], ContrastCheck::failures($this->defaults('settings')['schemes']));
    }

    public function test_contrast_is_measured_the_wcag_way(): void
    {
        $this->assertSame(21.0, ContrastCheck::ratio('#000000', '#ffffff'));
        $this->assertSame(1.0, ContrastCheck::ratio('#777777', '#777777'));
        $this->assertEqualsWithDelta(4.48, ContrastCheck::ratio('#777777', '#ffffff'), 0.01);
    }

    public function test_a_faint_scheme_saves_with_a_warning(): void
    {
        $this->saveDraft($this->admin(), 'settings', $this->faintSettings(), 0)
            ->assertOk()
            ->assertJsonPath('data.warnings.0.scheme', 'scheme-2')
            ->assertJsonPath('data.warnings.0.label', 'Scheme 2')
            ->assertJsonPath('data.warnings.0.pair', 'text on background');
    }

    public function test_publish_refuses_a_faint_scheme_and_names_it(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'settings', $this->faintSettings(), 0)->assertOk();

        $response = $this->publish($admin, ['settings' => 1])
            ->assertStatus(422)
            ->assertJsonPath('code', 'readability')
            ->assertJsonPath('data.failures.0.scheme', 'scheme-2');

        $this->assertStringContainsString('Scheme 2', $response->json('message'));
        $this->assertStringContainsString('text on background', $response->json('message'));
        $this->assertNull(Document::where('handle', 'settings')->firstOrFail()->published);

        // Fixed, it publishes.
        $this->saveDraft($admin, 'settings', $this->defaults('settings'), 1)->assertOk()->assertJsonPath('data.warnings', []);
        $this->publish($admin, ['settings' => 2])->assertOk();
    }
}

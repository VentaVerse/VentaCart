<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Document;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * The cart icon, the number on it and the side the phone menu opens from are
 * edited with the header's own settings, but they are theme settings: stored,
 * saved and published with the settings document, never with the header.
 */
class XelgaHeaderSettingsTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const KEYS = ['cart_icon', 'show_cart_count', 'drawer_side'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    /** @return array<string, mixed> */
    private function boot(): array
    {
        $response = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->assertOk();
        preg_match('#<script type="application/json" id="xelga-boot">(.*?)</script>#s', $response->getContent(), $m);

        return json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true) ?? json_decode($m[1] ?? '', true);
    }

    public function test_the_boot_payload_places_the_header_settings_in_the_header_panel(): void
    {
        $boot = $this->boot();

        $groups = collect($boot['catalog']['theme_settings'])->keyBy('group');
        $group = $groups['header-behavior'];
        $this->assertSame(['section' => 'header', 'after' => 'show_wishlist'], $group['edited_in']);
        $this->assertSame(self::KEYS, array_column($group['settings'], 'key'));

        // Only that group is edited outside Theme settings.
        $this->assertSame(['header-behavior'], $groups->filter(fn ($g) => isset($g['edited_in']))->keys()->all());

        // The header keeps its own settings, and the field the group follows is one of them.
        $header = array_column($boot['catalog']['sections']['header']['settings'], 'key');
        $this->assertContains('show_wishlist', $header);
        $this->assertSame([], array_values(array_intersect(self::KEYS, $header)));

        // Still theme settings, so the settings document validates and publishes them.
        foreach (self::KEYS as $key) {
            $this->assertArrayHasKey($key, Catalog::themeSettings());
        }
    }

    public function test_the_header_settings_save_and_publish_with_the_settings_document_only(): void
    {
        $admin = $this->admin();
        $settings = $this->defaults('settings');
        $settings['values'] = array_merge($settings['values'] ?? [], [
            'cart_icon' => 'cart',
            'show_cart_count' => false,
            'drawer_side' => 'end',
        ]);

        $this->saveDraft($admin, 'settings', $settings, 0)
            ->assertOk()
            ->assertJsonPath('data.document.draft_version', 1);

        // The header document gets no draft of its own.
        $this->assertNull(Document::where('handle', 'header')->value('draft'));
        $row = Document::where('handle', 'settings')->firstOrFail();
        $this->assertSame('cart', $row->draft['values']['cart_icon']);
        $this->assertFalse($row->draft['values']['show_cart_count']);
        $this->assertSame('end', $row->draft['values']['drawer_side']);

        $this->publish($admin, ['settings' => 1])->assertOk();

        $this->asGuest();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<html[^>]*data-cart-icon="cart"#', $html);
        $this->assertMatchesRegularExpression('#<html[^>]*data-drawer-side="end"#', $html);
    }

    public function test_a_value_the_header_setting_does_not_offer_is_never_stored(): void
    {
        $settings = $this->defaults('settings');
        $settings['values'] = array_merge($settings['values'] ?? [], ['drawer_side' => 'top']);

        $this->saveDraft($this->admin(), 'settings', $settings, 0)->assertOk();

        // The sanitizer puts back the default, as for any theme setting.
        $this->assertSame('start', Document::where('handle', 'settings')->firstOrFail()->draft['values']['drawer_side']);
    }
}

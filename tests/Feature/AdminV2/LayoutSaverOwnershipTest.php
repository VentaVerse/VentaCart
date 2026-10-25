<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Services\LayoutSaver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A save names placements by id so the editor can keep them. Only an id
 * that already belongs to the theme and page being saved is reused; any
 * other id (another page's, another theme's, or one sent twice) is written
 * as a new placement, so a crafted save cannot move or overwrite work it
 * does not own.
 */
class LayoutSaverOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function placement(array $attributes): LayoutPlacement
    {
        return LayoutPlacement::create($attributes + [
            'theme' => 'venta', 'page_type' => 'home', 'region' => 'content_top',
            'widget' => 'content.text', 'title' => 'Original', 'config' => [], 'sort_order' => 0, 'is_enabled' => true,
        ]);
    }

    /** @param list<array<string, mixed>> $placements */
    private function save(string $pageType, array $placements): array
    {
        return app(LayoutSaver::class)->save($pageType, [
            'columns' => ['left' => 0, 'right' => 0],
            'rows' => [[
                'id' => null, 'region' => 'content_top', 'columns' => [8], 'enabled' => true,
                'placements' => array_map(fn ($p) => $p + ['widget' => 'content.text', 'column' => 0, 'config' => []], $placements),
            ]],
        ]);
    }

    public function test_a_placement_of_the_same_theme_and_page_keeps_its_id(): void
    {
        $own = $this->placement([]);

        $result = $this->save('home', [['id' => $own->id, 'title' => 'Renamed']]);

        $this->assertSame([$own->id], $result['kept']);
        $this->assertSame('Renamed', $own->fresh()->title);
    }

    public function test_another_pages_placement_is_not_moved_or_overwritten(): void
    {
        $cart = $this->placement(['page_type' => 'cart']);

        $result = $this->save('home', [['id' => $cart->id, 'title' => 'Hijacked']]);

        $cart->refresh();
        $this->assertSame('cart', $cart->page_type);
        $this->assertSame('Original', $cart->title);
        $this->assertNotContains($cart->id, $result['kept']);
        $this->assertSame('Hijacked', LayoutPlacement::find($result['kept'][0])->title);
        $this->assertSame('home', LayoutPlacement::find($result['kept'][0])->page_type);
    }

    public function test_another_themes_placement_is_not_moved_or_overwritten(): void
    {
        $foreign = $this->placement(['theme' => 'sample']);

        $result = $this->save('home', [['id' => $foreign->id, 'title' => 'Hijacked']]);

        $foreign->refresh();
        $this->assertSame('sample', $foreign->theme);
        $this->assertSame('Original', $foreign->title);
        $this->assertNotContains($foreign->id, $result['kept']);
        $this->assertSame('venta', LayoutPlacement::find($result['kept'][0])->theme);
    }

    public function test_an_id_sent_twice_is_reused_once(): void
    {
        $own = $this->placement([]);

        $result = $this->save('home', [['id' => $own->id, 'title' => 'First'], ['id' => $own->id, 'title' => 'Second']]);

        $this->assertCount(2, $result['kept']);
        $this->assertSame($own->id, $result['kept'][0]);
        $this->assertSame('First', $own->fresh()->title);
        $this->assertSame('Second', LayoutPlacement::find($result['kept'][1])->title);
        $this->assertSame(2, LayoutPlacement::where('theme', 'venta')->where('page_type', 'home')->count());
    }
}

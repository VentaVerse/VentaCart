<?php

namespace Tests\Feature\AdminV2;

use App\Models\LayoutPlacement;
use App\Models\LayoutRow;
use App\Models\LayoutTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Area templates: a position's rows kept under a name, loaded back as
 * editor rows, carried out as a file and brought back in. None of it writes
 * a page: everything is editor state until Save.
 */
class LayoutTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private array $rows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->rows = [[
            'columns' => [4, 4], 'enabled' => true, 'style' => ['pad' => 'md', 'bogus' => 1],
            'placements' => [
                ['column' => 0, 'widget' => 'catalog.brands', 'title' => 'Left brands', 'enabled' => true, 'config' => []],
                ['column' => 1, 'widget' => 'nobody.home', 'title' => 'Ghost', 'enabled' => true, 'config' => []],
                ['column' => 5, 'widget' => 'catalog.brands', 'title' => 'Clamped', 'enabled' => false, 'config' => ['x' => 1]],
            ],
        ]];
    }

    public function test_a_template_keeps_only_what_the_store_can_place(): void
    {
        $body = $this->postJson(route('admin.layout.templates.store'), ['name' => 'Two brands', 'region' => 'content_top', 'rows' => $this->rows])
            ->assertOk()->assertJsonPath('name', 'Two brands')->json();

        $stored = LayoutTemplate::sole();
        $this->assertSame('default', $stored->theme);
        $this->assertSame([4, 4], $stored->rows[0]['columns']);
        $this->assertSame('md', $stored->rows[0]['style']['pad']);
        $this->assertArrayNotHasKey('bogus', $stored->rows[0]['style']);
        $this->assertCount(2, $stored->rows[0]['placements'], 'A widget nothing registers is not kept.');
        $this->assertSame(1, $stored->rows[0]['placements'][1]['column'], 'A column past the last lands in the last.');

        // Handed back in the editor's shape: cards per column, no ids.
        $this->assertCount(1, $body['rows'][0]['cols'][0]);
        $this->assertCount(1, $body['rows'][0]['cols'][1]);
        $this->assertNull($body['rows'][0]['cols'][0][0]['id']);
        $this->assertSame('Brand strip', $body['rows'][0]['cols'][0][0]['label']);
    }

    public function test_a_template_with_nothing_placeable_is_refused(): void
    {
        $this->postJson(route('admin.layout.templates.store'), ['name' => 'Empty', 'rows' => [['columns' => [8], 'placements' => [['widget' => 'nobody.home']]]]])
            ->assertOk();
        $this->assertSame(1, LayoutTemplate::count(), 'An empty row is still a row the merchant may want.');

        $this->postJson(route('admin.layout.templates.store'), ['name' => 'Nothing', 'rows' => []])->assertStatus(422);
        $this->postJson(route('admin.layout.templates.store'), ['name' => '', 'rows' => $this->rows])->assertStatus(422);
    }

    public function test_the_editor_ships_this_themes_templates_and_can_drop_one(): void
    {
        LayoutTemplate::create(['theme' => 'default', 'name' => 'Mine', 'rows' => LayoutTemplate::cleanRows($this->rows)]);
        $foreign = LayoutTemplate::create(['theme' => 'other', 'name' => 'Theirs', 'rows' => []]);

        $html = $this->get(route('admin.layout', ['page' => 'home']))->assertOk()->getContent();
        $this->assertStringContainsString('Mine', $html);
        $this->assertStringNotContainsString('Theirs', $html);
        $this->assertStringContainsString('Save this position as a template', $html);

        $this->deleteJson(route('admin.layout.templates.destroy', $foreign))->assertNotFound();
        $mine = LayoutTemplate::where('name', 'Mine')->sole();
        $this->deleteJson(route('admin.layout.templates.destroy', $mine))->assertOk();
        $this->assertDatabaseMissing('layout_templates', ['id' => $mine->id]);
    }

    public function test_there_is_no_copy_to_another_page_endpoint(): void
    {
        // Templates carry a position to another page: save it as a template
        // there and insert it on the other page, where Save decides.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.layout.copy'));
        $this->postJson(route('admin.layout') . '/copy', ['region' => 'content_top', 'target_page' => 'cart', 'rows' => $this->rows])
            ->assertStatus(405);
        $this->assertSame(0, LayoutRow::count());
    }

    public function test_a_position_travels_as_a_file_and_comes_back_as_rows(): void
    {
        $file = $this->post(route('admin.layout.templates.export'), ['region' => 'content_top', 'rows' => json_encode($this->rows)])
            ->assertOk()->assertHeader('Content-Type', 'application/json')->getContent();

        $data = json_decode($file, true);
        $this->assertSame(1, $data['venta_layout']);
        $this->assertSame('content_top', $data['region']);
        $this->assertCount(2, $data['rows'][0]['placements']);

        $back = $this->post(route('admin.layout.templates.import'), ['file' => UploadedFile::fake()->createWithContent('layout.json', $file)])
            ->assertOk()->json();

        $this->assertSame('content_top', $back['region']);
        $this->assertSame([4, 4], $back['rows'][0]['columns']);
        $this->assertSame('Left brands', $back['rows'][0]['cols'][0][0]['title']);
        $this->assertSame(0, LayoutRow::count(), 'An import writes nothing; Save does.');

        $this->post(route('admin.layout.templates.import'), ['file' => UploadedFile::fake()->createWithContent('x.json', '{"hello":1}')])->assertStatus(422);
    }

    public function test_the_template_controls_are_behind_admin_auth(): void
    {
        auth()->logout();
        $this->postJson(route('admin.layout.templates.store'), ['name' => 'x', 'rows' => $this->rows])->assertUnauthorized();
    }
}

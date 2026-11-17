<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Publisher;
use Themes\Xelga\Documents\Revision;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Draft, publish, restore and discard: one shared draft per document,
 * guarded by its version; publish is all or nothing and keeps revisions.
 */
class XelgaDocumentsTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
    }

    public function test_a_draft_is_saved_and_shoppers_keep_seeing_the_published_page(): void
    {
        $admin = $this->admin();

        $this->saveDraft($admin, 'home', $this->homeWithHeading('Drafted banner heading'), 0)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.document.draft_version', 1)
            ->assertJsonPath('data.document.has_draft', true)
            ->assertJsonPath('data.document.is_default', true);

        $row = Document::where('handle', 'home')->firstOrFail();
        $this->assertSame('Drafted banner heading', $row->draft['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading']);
        $this->assertNull($row->published);

        // Reading it back shows the draft to the editor.
        $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'home']))
            ->assertOk()
            ->assertJsonPath('data.content.sections.hero-1.blocks.hero-1-b1.settings.heading', 'Drafted banner heading');

        $this->asGuest();
        $this->get('/')->assertOk()
            ->assertSee('Phones, laptops and tablets, paid for at your door')
            ->assertDontSee('Drafted banner heading');
    }

    public function test_publish_puts_the_draft_live_and_keeps_a_revision(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Published banner heading'), 0)->assertOk();

        $this->publish($admin, ['home' => 1])
            ->assertOk()
            ->assertJsonPath('data.documents.home.published_version', 1)
            ->assertJsonPath('data.documents.home.draft_version', 2);

        $row = Document::where('handle', 'home')->firstOrFail();
        $this->assertNull($row->draft);
        $this->assertSame(1, $row->published_version);
        $this->assertSame($admin->id, $row->published_by);
        $this->assertSame(1, Revision::where('document_id', $row->id)->count());

        $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'home']) . '/revisions')
            ->assertOk()
            ->assertJsonPath('data.revisions.0.version', 1)
            ->assertJsonPath('data.revisions.0.is_live', true)
            ->assertJsonPath('data.revisions.0.created_by.name', 'Juan Dela Cruz');

        $this->asGuest();
        $this->get('/')->assertOk()->assertSee('Published banner heading')->assertDontSee('Phones, laptops and tablets, paid for at your door');
    }

    public function test_restore_copies_a_revision_into_the_draft_only(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('First heading'), 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Second heading'), 2)->assertOk();
        $this->publish($admin, ['home' => 3])->assertOk();

        $first = Revision::where('version', 1)->firstOrFail();

        $this->actingAs($admin)->postJson(route('admin.xelga.revisions.restore', ['handle' => 'home', 'revision' => $first->id]), ['draft_version' => 4])
            ->assertOk()
            ->assertJsonPath('data.document.draft.sections.hero-1.blocks.hero-1-b1.settings.heading', 'First heading')
            ->assertJsonPath('data.document.published.sections.hero-1.blocks.hero-1-b1.settings.heading', 'Second heading');

        $this->asGuest();
        $this->get('/')->assertSee('Second heading')->assertDontSee('First heading');
    }

    public function test_discard_throws_every_draft_away(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Throwaway heading'), 0)->assertOk();
        $footer = $this->defaults('footer');
        $footer['sections']['footer-1']['settings']['about'] = 'Throwaway about text';
        $this->saveDraft($admin, 'footer', $footer, 0)->assertOk();

        $this->actingAs($admin)->deleteJson(route('admin.xelga.documents.discard'))
            ->assertOk()
            ->assertJsonPath('data.draft_versions.home', 2)
            ->assertJsonPath('data.draft_versions.footer', 2);

        $this->assertSame(0, Document::whereNotNull('draft')->count());
        $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'home']))
            ->assertJsonPath('data.has_draft', false)
            ->assertJsonPath('data.content.sections.hero-1.blocks.hero-1-b1.settings.heading', 'Phones, laptops and tablets, paid for at your door');
    }

    public function test_a_stale_draft_version_gets_a_conflict_not_an_overwrite(): void
    {
        $juan = $this->admin();
        $maria = $this->themeEditor();

        $this->saveDraft($juan, 'home', $this->homeWithHeading('Juan was here first'), 0)->assertOk();

        // Maria opened the editor before Juan saved, so she still holds version 0.
        $this->saveDraft($maria, 'home', $this->homeWithHeading('Maria overwrites'), 0)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'conflict')
            ->assertJsonPath('data.draft_version', 1)
            ->assertJsonPath('data.draft_updated_by.name', 'Juan Dela Cruz')
            ->assertJsonPath('data.draft.sections.hero-1.blocks.hero-1-b1.settings.heading', 'Juan was here first');

        $this->assertSame('Juan was here first', Document::where('handle', 'home')->value('draft') ? Document::where('handle', 'home')->first()->draft['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading'] : null);

        // A later save on the current version goes through.
        $this->saveDraft($maria, 'home', $this->homeWithHeading('Maria, after loading'), 1)->assertOk()->assertJsonPath('data.document.draft_version', 2);
    }

    public function test_publish_with_a_stale_version_is_refused_and_nothing_goes_live(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Version one'), 0)->assertOk();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Version two'), 1)->assertOk();

        $this->publish($admin, ['home' => 1])->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->assertNull(Document::where('handle', 'home')->first()->published);
    }

    public function test_publish_refuses_when_a_draft_it_was_not_told_about_exists(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Home change'), 0)->assertOk();
        $footer = $this->defaults('footer');
        $footer['sections']['footer-1']['settings']['about'] = 'Another person changed the footer';
        $this->saveDraft($this->themeEditor(), 'footer', $footer, 0)->assertOk();

        $this->publish($admin, ['home' => 1])->assertStatus(409)->assertJsonPath('data.handle', 'footer');
        $this->assertSame(0, Document::whereNotNull('published')->count());
    }

    public function test_publish_is_all_or_nothing(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Should not go live'), 0)->assertOk();
        $settings = $this->defaults('settings');
        $settings['schemes'][0]['colors']['text'] = '#e9ebeb';
        $this->saveDraft($admin, 'settings', $settings, 0)->assertOk();

        $this->publish($admin, ['home' => 1, 'settings' => 1])->assertStatus(422);

        $this->assertSame(0, Document::whereNotNull('published')->count());
        $this->assertSame(0, Revision::count());
    }

    public function test_nothing_to_publish_is_answered_plainly(): void
    {
        $this->publish($this->admin(), [])->assertStatus(422)->assertJsonPath('message', 'There are no changes to publish.');
    }

    public function test_revisions_are_pruned_to_the_newest_thirty(): void
    {
        $admin = $this->admin();
        $writer = app(\Themes\Xelga\Documents\DocumentWriter::class);
        $publisher = app(Publisher::class);
        $version = 0;

        for ($i = 1; $i <= 32; $i++) {
            $saved = $writer->saveDraft('home', $this->homeWithHeading("Heading {$i}"), $version, $admin);
            $result = $publisher->publish(['home' => $saved['document']->draft_version], $admin);
            $version = $result['home']['draft_version'];
        }

        $versions = Revision::orderBy('version')->pluck('version')->all();
        $this->assertCount(30, $versions);
        $this->assertSame(3, $versions[0]);
        $this->assertSame(32, end($versions));
    }

    public function test_structural_errors_answer_422_with_paths(): void
    {
        $home = $this->defaults('home');
        $missingAt = count($home['order']);
        $home['order'][] = 'missing-section';
        $home['sections']['hero-1']['blocks']['hero-1-b1']['type'] = 'not-a-block';

        $this->saveDraft($this->admin(), 'home', $home, 0)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid')
            ->assertJsonStructure(['errors' => ['content.order.' . $missingAt, 'content.sections.hero-1.blocks.hero-1-b1.type']]);

        $this->assertSame(0, Document::count());
    }

    public function test_a_section_cannot_be_added_where_it_does_not_belong(): void
    {
        $header = $this->defaults('header');
        $header['order'][] = 'hero-2';
        $header['sections']['hero-2'] = $this->defaults('home')['sections']['hero-1'];
        $header['sections']['hero-2']['block_order'] = ['hero-2-b1'];
        $header['sections']['hero-2']['blocks'] = ['hero-2-b1' => $header['sections']['hero-2']['blocks']['hero-1-b1']];

        $this->saveDraft($this->admin(), 'header', $header, 0)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.sections.hero-2.type']]);
    }

    public function test_an_unknown_section_type_is_kept_only_when_it_could_be_a_name(): void
    {
        $admin = $this->admin();
        $home = $this->defaults('home');
        $home['order'][] = 'old-1';
        $home['sections']['old-1'] = ['type' => 'retired-band', 'settings' => ['heading' => 'Kept?'], 'blocks' => [], 'block_order' => []];
        $this->saveDraft($admin, 'home', $home, 0)->assertOk();
        $kept = Document::where('handle', 'home')->first()->draft['sections']['old-1'];
        $this->assertSame('retired-band', $kept['type']);
        $this->assertTrue($kept['disabled']);
        $this->assertSame([], $kept['settings']);

        foreach ([str_repeat('a', 41), 'Hero', '"><img src=x onerror=alert(1)>', 'a b'] as $type) {
            $bad = $this->defaults('home');
            $bad['order'][] = 'zz-1';
            $bad['sections']['zz-1'] = ['type' => $type, 'settings' => [], 'blocks' => [], 'block_order' => []];
            $this->saveDraft($admin, 'home', $bad, 1)
                ->assertStatus(422)
                ->assertJsonPath('code', 'invalid')
                ->assertJsonStructure(['errors' => ['content.sections.zz-1.type']]);
        }
        $this->assertSame('retired-band', Document::where('handle', 'home')->first()->draft['sections']['old-1']['type']);
    }

    public function test_a_block_type_that_is_not_a_name_is_refused(): void
    {
        $home = $this->defaults('home');
        $home['sections']['hero-1']['blocks']['hero-1-b1']['type'] = str_repeat('A', 500) . '"><b>';

        $this->saveDraft($this->admin(), 'home', $home, 0)
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['content.sections.hero-1.blocks.hero-1-b1.type']]);
        $this->assertSame(0, Document::count());
    }

    public function test_an_unknown_document_is_not_found(): void
    {
        $this->saveDraft($this->admin(), 'checkout', $this->defaults('home'), 0)->assertNotFound();
        $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'nothing']))->assertNotFound();
    }

    public function test_a_body_over_256_kb_is_refused(): void
    {
        $home = $this->defaults('home');
        $home['padding'] = str_repeat('x', 270000);

        $this->saveDraft($this->admin(), 'home', $home, 0)->assertStatus(413)->assertJsonPath('code', 'too_large');
    }
}

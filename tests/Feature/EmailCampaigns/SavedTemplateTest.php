<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\EmailTemplate;
use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        $this->actingAs(User::factory()->create());
    }

    public function test_a_template_can_be_saved_and_listed(): void
    {
        $this->post(route('admin.email-campaigns.templates.store'), [
            'name' => 'Monthly reseller price list',
            'subject' => 'Price list for {first_name}',
            'body' => '<p>Attached</p>',
        ])->assertRedirect();

        $this->get(route('admin.email-campaigns.templates.index'))
            ->assertOk()
            ->assertSee('Monthly reseller price list');
    }

    public function test_composing_from_a_template_prefills_subject_and_body(): void
    {
        $template = EmailCampaignTemplate::create([
            'store_id' => 1,
            'name' => 'Reseller list',
            'subject' => 'Price list',
            'body' => '<p>Attached</p>',
        ]);

        $this->get(route('admin.email-campaigns.create', ['from_template' => $template->id]))
            ->assertOk()
            ->assertSee('Price list')
            ->assertSee('Attached', false);
    }

    /**
     * Campaign templates and core's transactional email_templates are separate
     * tables on purpose: merging them would put "Order Confirmation" in the
     * campaign picker and a half-written promo in the order pipeline.
     */
    public function test_campaign_templates_are_not_core_email_templates(): void
    {
        EmailCampaignTemplate::create(['store_id' => 1, 'name' => 'Promo', 'subject' => 'Hi', 'body' => '<p>Hi</p>']);

        $this->assertSame(0, EmailTemplate::where('name', 'Promo')->count());
        $this->assertSame(1, EmailCampaignTemplate::count());
    }

    public function test_a_template_needs_a_name(): void
    {
        $this->post(route('admin.email-campaigns.templates.store'), ['subject' => 'Hi'])
            ->assertSessionHasErrors('name');
    }
}

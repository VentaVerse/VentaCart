<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminScreensTest extends TestCase
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

    private function customer(string $email, bool $newsletter = true): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1, 'customer_group_id' => $group->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $email,
            'password' => bcrypt('secret'), 'newsletter' => $newsletter, 'is_active' => true,
        ]);
    }

    public function test_the_index_lists_campaigns(): void
    {
        EmailCampaign::create([
            'store_id' => 1, 'subject' => 'Reseller price list',
            'audience_type' => 'group', 'audience_params' => ['group_id' => 1], 'status' => 'sent',
        ]);

        $this->get(route('admin.email-campaigns.index'))
            ->assertOk()
            ->assertSee('Reseller price list');
    }

    public function test_the_compose_screen_renders(): void
    {
        $this->get(route('admin.email-campaigns.create'))
            ->assertOk()
            ->assertSee('All customers')
            ->assertSee('{unsubscribe_url}', false);
    }

    public function test_saving_a_draft_does_not_send(): void
    {
        Bus::fake();

        $this->post(route('admin.email-campaigns.store'), [
            'subject' => 'Draft one',
            'body' => '<p>Hi</p>',
            'audience_type' => 'all',
            'action' => 'draft',
        ])->assertRedirect();

        Bus::assertNotDispatched(SendCampaignJob::class);
        $this->assertSame('draft', EmailCampaign::first()->status);
    }

    public function test_sending_queues_the_job_and_marks_the_campaign_queued(): void
    {
        Bus::fake();

        $this->post(route('admin.email-campaigns.store'), [
            'subject' => 'Go out now',
            'body' => '<p>Hi</p>',
            'audience_type' => 'all',
            'action' => 'send',
        ])->assertRedirect();

        Bus::assertDispatched(SendCampaignJob::class);
        $this->assertSame('queued', EmailCampaign::first()->status);
    }

    public function test_the_recipient_count_endpoint_reports_both_figures(): void
    {
        $this->customer('in@example.com');
        $this->customer('out@example.com', false);

        $this->getJson(route('admin.email-campaigns.recipient-count', ['audience_type' => 'all']))
            ->assertOk()
            ->assertJson(['audience_total' => 2, 'will_receive' => 1]);
    }

    /** The test send goes to the admin, who is not a customer and has no flag to consult. */
    public function test_a_test_send_mails_the_logged_in_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($admin);

        $this->postJson(route('admin.email-campaigns.test'), [
            'subject' => 'Preview me',
            'body' => '<p>Hi {first_name}</p>',
        ])->assertOk()->assertJson(['ok' => true]);

        Mail::assertSent(\Extensions\EmailCampaigns\Mail\CampaignMail::class,
            fn ($m) => $m->hasTo('admin@example.com'));
    }

    /**
     * F1: a test send used to end() in back(), which wiped the draft the
     * admin was composing. It is a JSON endpoint now precisely so nothing
     * ever navigates away from an unsaved compose screen; a validation
     * failure must report as a normal 422, not a redirect either.
     */
    public function test_a_test_send_with_no_subject_reports_a_validation_error_without_redirecting(): void
    {
        $this->postJson(route('admin.email-campaigns.test'), ['body' => '<p>Hi</p>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject']);
    }

    public function test_a_campaign_requires_a_subject_and_an_audience(): void
    {
        $this->post(route('admin.email-campaigns.store'), ['body' => '<p>Hi</p>'])
            ->assertSessionHasErrors(['subject', 'audience_type']);
    }

    /**
     * F4: an unknown audience_type must fail validation, not reach
     * AudienceRegistry::for() and 500.
     */
    public function test_the_recipient_count_endpoint_rejects_an_unknown_audience_type(): void
    {
        $this->getJson(route('admin.email-campaigns.recipient-count', ['audience_type' => 'bogus']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['audience_type']);
    }

    public function test_the_recipient_count_endpoint_defaults_to_all_when_audience_type_is_omitted(): void
    {
        $this->customer('in@example.com');

        $this->getJson(route('admin.email-campaigns.recipient-count'))
            ->assertOk()
            ->assertJson(['audience_total' => 1, 'will_receive' => 1]);
    }
}

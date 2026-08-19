<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5: every campaign write in the original suite went through store();
 * update() and destroy() had no coverage at all, including the guard that
 * refuses to edit an already-sent campaign. F8 (duplicate) lives here too,
 * since it is the third write path on the same model.
 */
class CampaignLifecycleTest extends TestCase
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

    private function draft(array $overrides = []): EmailCampaign
    {
        return EmailCampaign::create(array_merge([
            'store_id' => 1,
            'subject' => 'Original subject',
            'body' => '<p>Original body</p>',
            'audience_type' => 'all',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_updating_a_draft_saves_the_new_fields(): void
    {
        $campaign = $this->draft();

        $this->put(route('admin.email-campaigns.update', $campaign), [
            'subject' => 'Updated subject',
            'body' => '<p>Updated body</p>',
            'audience_type' => 'all',
            'action' => 'draft',
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertSame('Updated subject', $campaign->subject);
        $this->assertSame('<p>Updated body</p>', $campaign->body);
        $this->assertSame('draft', $campaign->status);
    }

    /**
     * The guard standing between an audited send and a rewrite of history:
     * a sent campaign refuses the write and is left byte-for-byte alone.
     */
    public function test_updating_an_already_sent_campaign_is_refused_and_leaves_it_unchanged(): void
    {
        $campaign = $this->draft([
            'status' => 'sent',
            'recipients_total' => 5,
            'sent_at' => now(),
        ]);
        $originalSubject = $campaign->subject;
        $originalBody = $campaign->body;
        $originalSentAt = $campaign->sent_at;

        $response = $this->put(route('admin.email-campaigns.update', $campaign), [
            'subject' => 'A rewrite of history',
            'body' => '<p>Different</p>',
            'audience_type' => 'all',
            'action' => 'draft',
        ]);

        $response->assertRedirect();
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame($originalSubject, $campaign->subject);
        $this->assertSame($originalBody, $campaign->body);
        $this->assertSame(5, $campaign->recipients_total);
        $this->assertEquals($originalSentAt, $campaign->sent_at);
    }

    public function test_destroying_a_campaign_removes_it(): void
    {
        $campaign = $this->draft();

        $this->delete(route('admin.email-campaigns.destroy', $campaign))
            ->assertRedirect(route('admin.email-campaigns.index'));

        $this->assertDatabaseMissing('email_campaigns', ['id' => $campaign->id]);
    }

    /** F8: index lists New, view, duplicate. Duplicate must never copy status, recipients_total or sent_at. */
    public function test_duplicating_a_sent_campaign_creates_a_fresh_draft(): void
    {
        $campaign = $this->draft([
            'subject' => 'August newsletter',
            'audience_type' => 'all',
            'status' => 'sent',
            'recipients_total' => 42,
            'sent_at' => now(),
        ]);

        $this->post(route('admin.email-campaigns.duplicate', $campaign))->assertRedirect();

        $this->assertDatabaseCount('email_campaigns', 2);
        $copy = EmailCampaign::where('id', '!=', $campaign->id)->firstOrFail();

        $this->assertSame('draft', $copy->status);
        $this->assertSame(0, $copy->recipients_total);
        $this->assertNull($copy->sent_at);
        $this->assertSame('<p>Original body</p>', $copy->body);
        $this->assertSame('all', $copy->audience_type);
        $this->assertStringContainsString('August newsletter', $copy->subject);

        // The original record of what was sent is untouched.
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(42, $campaign->recipients_total);
    }

    public function test_duplicating_a_draft_also_creates_a_second_independent_draft(): void
    {
        $campaign = $this->draft();

        $this->post(route('admin.email-campaigns.duplicate', $campaign))->assertRedirect();

        $this->assertDatabaseCount('email_campaigns', 2);
        $copy = EmailCampaign::where('id', '!=', $campaign->id)->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($campaign->id, $copy->id);
    }
}

<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
    }

    private function customer(string $email, bool $newsletter = true): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $email,
            'password' => bcrypt('secret'),
            'newsletter' => $newsletter,
            'is_active' => true,
        ]);
    }

    private function campaign(): EmailCampaign
    {
        return EmailCampaign::create([
            'store_id' => 1,
            'subject' => 'Hello {first_name}',
            'body' => '<p>Hi {first_name}</p>',
            'audience_type' => 'all',
            'status' => 'queued',
        ]);
    }

    public function test_it_mails_exactly_the_consenting_recipients_once_each(): void
    {
        Mail::fake();
        $this->customer('in1@example.com');
        $this->customer('in2@example.com');
        $this->customer('out@example.com', false);

        (new SendCampaignJob($this->campaign()->id))->handle();

        Mail::assertSent(CampaignMail::class, 2);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('in1@example.com'));
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('in2@example.com'));
        Mail::assertNotSent(CampaignMail::class, fn ($m) => $m->hasTo('out@example.com'));
    }

    public function test_the_subject_is_rendered_per_recipient(): void
    {
        Mail::fake();
        $this->customer('juan@example.com');

        (new SendCampaignJob($this->campaign()->id))->handle();

        Mail::assertSent(CampaignMail::class, fn ($m) => $m->subject === 'Hello Juan');
    }

    public function test_it_records_what_was_sent(): void
    {
        Mail::fake();
        $this->customer('a@example.com');
        $this->customer('b@example.com');
        $campaign = $this->campaign();

        (new SendCampaignJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_total);
        $this->assertNotNull($campaign->sent_at);
    }

    /**
     * One bad address must never mark the whole send failed. `failed` is
     * reserved for the JOB failing, not a recipient bouncing.
     */
    public function test_one_failing_recipient_does_not_abort_the_run(): void
    {
        $this->customer('good1@example.com');
        $this->customer('bad@example.com');
        $this->customer('good2@example.com');
        $campaign = $this->campaign();

        Mail::shouldReceive('to')->andReturnUsing(function ($address) {
            if ($address === 'bad@example.com') {
                throw new \RuntimeException('mailbox unavailable');
            }

            return new class {
                public function send($mailable) {}
            };
        });

        (new SendCampaignJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_total, 'Only successful sends are counted.');
    }

    public function test_a_campaign_already_sent_is_not_sent_again(): void
    {
        Mail::fake();
        $this->customer('a@example.com');
        $campaign = $this->campaign();
        $campaign->update(['status' => 'sent']);

        (new SendCampaignJob($campaign->id))->handle();

        Mail::assertNothingSent();
    }
}

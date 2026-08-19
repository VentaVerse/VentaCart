<?php

namespace Extensions\EmailCampaigns\Jobs;

use App\Models\Setting;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Extensions\EmailCampaigns\Services\CampaignRecipients;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Queued so a send to two hundred people does not hold the browser open.
 *
 * Recipients are chunked rather than loaded at once, and each send is wrapped
 * on its own: one refused address is logged and skipped, never allowed to end
 * the run. `status = failed` is therefore reserved for the JOB failing, which
 * means the mailer itself, not a recipient bouncing.
 */
class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = EmailCampaign::find($this->campaignId);

        // A campaign that has already gone out must never go out twice, even
        // if the job is retried by the queue.
        if (! $campaign || $campaign->status === 'sent') {
            return;
        }

        $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name');
        $logo = Setting::where('group', 'identity')->where('key', 'logo')->value('value');
        $logoUrl = $logo ? storage_url($logo) : null;

        $sent = 0;

        CampaignRecipients::for($campaign)->chunkById(100, function ($customers) use ($campaign, $storeName, $logoUrl, &$sent) {
            foreach ($customers as $customer) {
                try {
                    Mail::to($customer->email)->send(new CampaignMail(
                        CampaignRenderer::render($campaign->subject, $customer),
                        CampaignRenderer::render($campaign->body ?? '', $customer),
                        $storeName,
                        $logoUrl,
                    ));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('Campaign send failed for a recipient', [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $campaign->update([
            'status' => 'sent',
            'recipients_total' => $sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        EmailCampaign::where('id', $this->campaignId)->update(['status' => 'failed']);
    }
}

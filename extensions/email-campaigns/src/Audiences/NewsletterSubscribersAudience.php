<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscribersAudience implements Audience
{
    public function query(array $params): Builder
    {
        // Consent is applied again by CampaignRecipients. Harmless, and it
        // keeps this class honest about what it selects on its own.
        return Customer::query()->where('newsletter', true);
    }

    public function label(): string
    {
        return 'All newsletter subscribers';
    }
}

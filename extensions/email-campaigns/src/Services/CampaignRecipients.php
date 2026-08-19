<?php

namespace Extensions\EmailCampaigns\Services;

use Extensions\EmailCampaigns\Audiences\AudienceRegistry;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single place consent is enforced.
 *
 * Opt-out is absolute (owner ruling 2026-08-19): a customer with
 * newsletter = false is skipped by EVERY audience, hand-picked included, so
 * "All customers" quietly means "all customers who have not opted out".
 * Applying the filter here rather than inside each resolver is what makes
 * that true of audiences nobody has written yet.
 *
 * is_active and a non-null email are design decisions rather than rulings: a
 * disabled account should not receive marketing, and a null address cannot be
 * mailed.
 */
class CampaignRecipients
{
    public static function for(EmailCampaign $campaign): Builder
    {
        return AudienceRegistry::for($campaign->audience_type)
            ->query($campaign->audience_params ?? [])
            ->where('newsletter', true)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('id');
    }

    /** Everyone the audience names, before consent. The bigger of the two figures. */
    public static function audienceTotal(EmailCampaign $campaign): int
    {
        return AudienceRegistry::for($campaign->audience_type)
            ->query($campaign->audience_params ?? [])
            ->count();
    }

    /** Everyone who will actually be mailed. */
    public static function willReceive(EmailCampaign $campaign): int
    {
        return self::for($campaign)->count();
    }
}

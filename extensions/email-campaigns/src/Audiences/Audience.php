<?php

namespace Extensions\EmailCampaigns\Audiences;

use Illuminate\Database\Eloquent\Builder;

/**
 * Who a campaign goes to, BEFORE consent is considered.
 *
 * A resolver returns a query and never a collection, so the caller can count
 * cheaply and append the consent filter. Consent is deliberately NOT applied
 * here: CampaignRecipients adds it to every audience, which is what stops a
 * future resolver from forgetting it.
 */
interface Audience
{
    /** @param array<string, mixed> $params */
    public function query(array $params): Builder;

    public function label(): string;
}

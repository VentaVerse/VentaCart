<?php

namespace Extensions\EmailCampaigns\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A starting point an admin saved, NOT one of core's transactional
 * email_templates. Those fire from code by slug and belong to the order
 * pipeline; merging the two would put "Order Confirmation" in the campaign
 * picker and a half-written promo in the order flow.
 */
class EmailCampaignTemplate extends Model
{
    protected $fillable = ['store_id', 'name', 'subject', 'body'];
}

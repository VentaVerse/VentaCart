<?php

namespace Extensions\EmailCampaigns\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'store_id', 'subject', 'body', 'audience_type', 'audience_params',
        'status', 'recipients_total', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_params' => 'array',
            'recipients_total' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}

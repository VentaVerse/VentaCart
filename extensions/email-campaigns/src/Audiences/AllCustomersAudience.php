<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class AllCustomersAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query();
    }

    public function label(): string
    {
        return 'All customers';
    }
}

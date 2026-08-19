<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class CustomerGroupAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query()->where('customer_group_id', $params['group_id'] ?? 0);
    }

    public function label(): string
    {
        return 'Customer group';
    }
}

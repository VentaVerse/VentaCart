<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class SelectedCustomersAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query()->whereIn('id', $params['customer_ids'] ?? []);
    }

    public function label(): string
    {
        return 'Selected customers';
    }
}

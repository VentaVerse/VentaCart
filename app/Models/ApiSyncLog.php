<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncLog extends Model
{
    protected $fillable = [
        'method', 'endpoint', 'sku', 'status_code',
        'response_time_ms', 'request_body', 'response_body', 'ip_address',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DphApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'method',
        'endpoint',
        'order_id',
        'status_code',
        'response_time_ms',
        'request_body',
        'response_body',
        'success',
        'error_message',
    ];

    protected $casts = [
        'request_body' => 'array',
        'response_body' => 'array',
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];
}

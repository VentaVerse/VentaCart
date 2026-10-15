<?php

namespace Extensions\SmsUnisms;

use Illuminate\Database\Eloquent\Model;

/** One attempt to send a text: what was asked of UniSMS and what it said. */
class SmsLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sms_logs';

    protected $fillable = [
        'source', 'order_id', 'order_status_id', 'sms_template_id', 'recipient', 'sender_id',
        'message', 'status', 'http_status', 'gateway_reference', 'response', 'error',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** The response as it came, pretty-printed when it is JSON. */
    public function responseForHumans(): ?string
    {
        if ($this->response === null || $this->response === '') {
            return null;
        }

        $decoded = json_decode($this->response, true);

        return is_array($decoded)
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $this->response;
    }
}

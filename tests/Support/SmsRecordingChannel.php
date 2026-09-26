<?php

namespace Tests\Support;

/** RecordingChannel under the key a text gateway registers, for the API's notify_sms. */
class SmsRecordingChannel extends RecordingChannel
{
    public function key(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return 'Send SMS';
    }
}

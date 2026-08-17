<?php

namespace Extensions\StockAlerts\Models;

use Illuminate\Database\Eloquent\Model;

class StockNotificationTemplate extends Model
{
    protected $fillable = ['store_id', 'subject', 'body'];

    public function renderSubject(array $data): string
    {
        return $this->replacePlaceholders($this->subject, $data);
    }

    public function renderBody(array $data): string
    {
        return $this->replacePlaceholders($this->body, $data);
    }

    private function replacePlaceholders(string $text, array $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }
}

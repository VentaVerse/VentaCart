<?php

namespace Extensions\StockAlerts\Models;

use Illuminate\Database\Eloquent\Model;

class StockNotificationTemplate extends Model
{
    protected $fillable = ['store_id', 'subject', 'body'];

    public const DEFAULT_SUBJECT = 'Good news! {product_name} is back in stock';

    public const DEFAULT_BODY = '<p>Hi {customer_name},</p>
<p>Great news! The <strong>{product_name}</strong>{variant_name} you were waiting for is now back in stock at {store_name}.</p>
<p><a href="{product_url}">View Product</a></p>
<p>Hurry, stock is limited and this item may sell out again!</p>
<p>Thank you,<br>{store_name}</p>';

    /**
     * The store's template, made from the default the first time it is
     * needed. A store that installs the extension from the admin never runs
     * the seeder, and without a template no back in stock email is ever
     * sent: the waitlist fills and nobody hears.
     */
    public static function forStore(int $storeId = 1): self
    {
        return static::firstOrCreate(['store_id' => $storeId], ['subject' => self::DEFAULT_SUBJECT, 'body' => self::DEFAULT_BODY]);
    }

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

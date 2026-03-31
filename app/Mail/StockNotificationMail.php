<?php

namespace App\Mail;

use App\Models\StockNotification;
use App\Models\StockNotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $renderedSubject;
    public string $renderedBody;

    public function __construct(
        public StockNotification $notification,
        StockNotificationTemplate $template
    ) {
        $product = $notification->product;
        $variant = $notification->variant;

        $data = [
            'customer_name' => $notification->name,
            'product_name' => $product->name,
            'product_url' => route('product.show', $product->slug),
            'variant_name' => $variant ? ' — ' . $variant->name : '',
            'product_image' => $product->images->first()
                ? asset('storage/' . $product->images->first()->path)
                : '',
            'store_name' => app(\App\Services\ThemeService::class)->get('identity.site_name', 'Store'),
        ];

        $this->renderedSubject = $template->renderSubject($data);
        $this->renderedBody = $template->renderBody($data);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.stock-notification',
            with: [
                'body' => $this->renderedBody,
                'productName' => $this->notification->product->name,
                'productUrl' => route('product.show', $this->notification->product->slug),
                'storeName' => config('app.name'),
            ],
        );
    }
}

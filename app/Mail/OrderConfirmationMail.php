<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmation #' . $this->order->id,
        );
    }

    public function content(): Content
    {
        $bankInstructions = null;
        if (str_starts_with($this->order->payment_method, 'bank_transfer')) {
            $bankInstructions = class_exists(\Extensions\PaymentBankTransfer\BankTransferPayment::class)
                ? \Extensions\PaymentBankTransfer\BankTransferPayment::getInstructionsForOrder($this->order)
                : null;
        }

        $this->order->loadMissing('products.product');

        return new Content(
            view: 'emails.order-confirmation',
            with: ['order' => $this->order, 'bankInstructions' => $bankInstructions],
        );
    }
}

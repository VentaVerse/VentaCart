<?php

namespace Extensions\EmailCampaigns\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The subject is passed to the parent's own subject() rather than promoted
     * into a property. Illuminate\Mail\Mailable already declares an UNTYPED
     * public $subject, and a promoted `public string $subject` here is a fatal
     * error at class load: "Type of CampaignMail::$subject must be omitted to
     * match the parent definition". Calling subject() also populates that
     * inherited property, which is what Mail::assertSent can read back.
     */
    public function __construct(
        string $subject,
        public string $body,
        public string $storeName,
        public ?string $logo = null,
    ) {
        $this->subject($subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'email-campaigns::emails.shell',
            with: [
                'body' => $this->body,
                'storeName' => $this->storeName,
                'logo' => $this->logo,
            ],
        );
    }
}

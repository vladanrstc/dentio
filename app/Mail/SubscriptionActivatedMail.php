<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Company $company,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vaša Dentio pretplata je aktivirana',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-activated',
            with: [
                'company' => $this->company,
            ],
        );
    }
}

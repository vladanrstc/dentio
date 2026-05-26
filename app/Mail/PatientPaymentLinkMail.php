<?php

namespace App\Mail;

use App\Models\PatientPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientPaymentLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PatientPayment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dentio link za placanje',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-payment-link',
            with: [
                'payment' => $this->payment,
            ],
        );
    }
}

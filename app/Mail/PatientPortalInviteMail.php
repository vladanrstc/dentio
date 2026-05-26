<?php

namespace App\Mail;

use App\Models\PatientPortalInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientPortalInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PatientPortalInvite $invite,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pozivnica za Dentio portal za pacijente',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-portal-invite',
            with: [
                'invite' => $this->invite,
                'token' => $this->token,
            ],
        );
    }
}

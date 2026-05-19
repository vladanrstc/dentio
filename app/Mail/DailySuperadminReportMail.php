<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailySuperadminReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $report
     */
    public function __construct(
        public readonly array $report,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dentio dnevni izvestaj za '.$this->report['date'],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: sprintf(
                '<p>Dnevni izvestaj za %s je u prilogu.</p><p>Termini: %d<br>Kontrolni pregledi: %d<br>Ukupan dug: %.2f</p>',
                e((string) $this->report['date']),
                (int) $this->report['appointments_count'],
                (int) $this->report['checkups_count'],
                (float) $this->report['total_outstanding_debt'],
            ),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => (string) $this->report['csv'],
                (string) $this->report['filename'],
            )->withMime('text/csv'),
        ];
    }
}

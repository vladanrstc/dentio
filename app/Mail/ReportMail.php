<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportName,
        public readonly string $filename,
        public readonly string $content,
        public readonly string $mime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dentio izvestaj: '.$this->reportName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
            with: [
                'reportName' => $this->reportName,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->content, $this->filename)
                ->withMime($this->mime),
        ];
    }
}

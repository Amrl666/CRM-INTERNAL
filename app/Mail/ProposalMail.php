<?php

namespace App\Mail;

use App\Domain\Communications\DTOs\ProposalEmailData;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

final class ProposalMail extends Mailable
{
    public function __construct(
        public readonly ProposalEmailData $proposal,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $replyToEmail,
        public readonly string $messageId,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->fromName !== '' ? $this->fromName : null;

        return new Envelope(
            from: new Address($this->fromEmail, $name),
            to: [new Address($this->proposal->to)],
            replyTo: [new Address($this->replyToEmail, $name)],
            subject: $this->proposal->subject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageId);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proposals.html',
            text: 'emails.proposals.text',
            with: [
                'htmlBody' => $this->proposal->htmlBody,
                'textBody' => $this->proposal->textBody,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->proposal->attachmentPath)
                ->as($this->proposal->attachmentFilename)
                ->withMime($this->proposal->attachmentMime),
        ];
    }
}

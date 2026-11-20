<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\DTOs\CommunicationResult;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Mail\ProposalMail;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\LogicException as MimeLogicException;
use Throwable;

final class BrevoProposalSender implements ProposalEmailSender
{
    public function __construct(
        private readonly MailFactory $mailer,
    ) {}

    public function sendProposal(ProposalEmailData $message): CommunicationResult
    {
        $fromEmail = $this->getSenderEmail();

        if ($fromEmail === null || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return CommunicationResult::fail('brevo_from_address_invalid');
        }

        if (
            blank(config('mail.mailers.brevo.host'))
            || blank(config('mail.mailers.brevo.username'))
            || blank(config('mail.mailers.brevo.password'))
        ) {
            return CommunicationResult::fail('brevo_not_configured');
        }

        if (! filter_var($message->to, FILTER_VALIDATE_EMAIL)) {
            return CommunicationResult::fail('proposal_recipient_invalid');
        }

        if (! is_file($message->attachmentPath) || ! is_readable($message->attachmentPath)) {
            return CommunicationResult::fail('proposal_attachment_missing');
        }

        $messageId = $this->generateMessageId($fromEmail);

        try {
            $this->mailer->mailer('brevo')->send(new ProposalMail(
                proposal: $message,
                fromEmail: $fromEmail,
                fromName: (string) ($this->getSenderName() ?? ''),
                replyToEmail: $this->replyToEmail($fromEmail),
                messageId: $messageId,
            ));
        } catch (TransportExceptionInterface $e) {
            return $this->classifyTransportError($e);
        } catch (MimeLogicException|\InvalidArgumentException $e) {
            report($e);

            return CommunicationResult::fail('proposal_message_invalid');
        } catch (Throwable $e) {
            report($e);

            // The SMTP conversation may have reached Brevo before the
            // connection broke. Never retry blindly.
            return CommunicationResult::unknown('brevo_outcome_unknown');
        }

        return CommunicationResult::ok($messageId, 'accepted');
    }

    public function getProviderName(): string
    {
        return 'brevo';
    }

    public function getSenderEmail(): ?string
    {
        $email = config('communications.proposal_email.from_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function getSenderName(): ?string
    {
        $name = config('communications.proposal_email.from_name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function replyToEmail(string $fromEmail): string
    {
        $replyTo = config('communications.proposal_email.reply_to');

        return is_string($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)
            ? $replyTo
            : $fromEmail;
    }

    private function generateMessageId(string $fromEmail): string
    {
        $domain = Str::afterLast($fromEmail, '@');

        return Str::uuid()->toString().'@'.($domain !== '' ? $domain : 'localhost');
    }

    private function classifyTransportError(TransportExceptionInterface $e): CommunicationResult
    {
        $smtpCode = $this->smtpCode($e->getMessage());

        Log::warning('Proposal Brevo SMTP send failed.', [
            'exception' => $e::class,
            'smtp_code' => $smtpCode,
        ]);

        if (in_array($smtpCode, [530, 534, 535, 538], true)) {
            return CommunicationResult::fail('brevo_authentication_failed');
        }

        if (in_array($smtpCode, [421, 450, 451, 452, 454], true)) {
            return CommunicationResult::fail('brevo_rate_limited');
        }

        if (in_array($smtpCode, [550, 551, 552, 553, 554], true)) {
            return CommunicationResult::fail('brevo_message_rejected');
        }

        // Without a definitive SMTP reply the message may already have been
        // accepted by Brevo. Retrying could duplicate the proposal.
        return CommunicationResult::unknown('brevo_outcome_unknown');
    }

    private function smtpCode(string $message): ?int
    {
        if (preg_match('/\b([45]\d{2})\b/', $message, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}

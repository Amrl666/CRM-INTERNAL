<?php

use App\Domain\Communications\Contracts\ProposalEmailSender;
use App\Domain\Communications\DTOs\ProposalEmailData;
use App\Domain\Communications\Providers\BrevoProposalSender;
use App\Mail\ProposalMail;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

function brevoProposalTestData(string $attachmentPath): ProposalEmailData
{
    return new ProposalEmailData(
        to: 'lead@example.test',
        subject: 'Proposal website sekolah',
        htmlBody: '<p>Halo lead, kami lampirkan proposal.</p>',
        textBody: 'Halo lead, kami lampirkan proposal.',
        attachmentPath: $attachmentPath,
        attachmentFilename: 'Proposal-Website.pdf',
    );
}

beforeEach(function () {
    config([
        'communications.proposal_email.transport' => 'brevo',
        'communications.proposal_email.from_email' => 'hello@enigmative.web.id',
        'communications.proposal_email.from_name' => 'Enigma Collective Studio',
        'communications.proposal_email.reply_to' => 'hello@enigmative.web.id',
        'mail.mailers.brevo.transport' => 'smtp',
        'mail.mailers.brevo.host' => 'smtp-relay.brevo.com',
        'mail.mailers.brevo.port' => 587,
        'mail.mailers.brevo.username' => 'smtp-user@example.test',
        'mail.mailers.brevo.password' => 'secret',
    ]);

    Mail::fake();

    $this->pdfPath = sys_get_temp_dir().'/brevo-proposal-'.bin2hex(random_bytes(8)).'.pdf';
    file_put_contents($this->pdfPath, "%PDF-1.4\n% Brevo proposal test attachment\n");
});

afterEach(function () {
    if (isset($this->pdfPath) && is_file($this->pdfPath)) {
        unlink($this->pdfPath);
    }
});

it('sends proposals through brevo with the hello alias as sender', function () {
    $result = (new BrevoProposalSender(Mail::getFacadeRoot()))->sendProposal(
        brevoProposalTestData($this->pdfPath),
    );

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe('accepted')
        ->and($result->providerMessageId)->toEndWith('@enigmative.web.id');

    $sent = Mail::sent(ProposalMail::class);

    expect($sent)->toHaveCount(1);

    $mail = $sent->first();
    $envelope = $mail->envelope();

    expect($envelope->from->address)->toBe('hello@enigmative.web.id')
        ->and($envelope->from->name)->toBe('Enigma Collective Studio')
        ->and($envelope->to[0]->address)->toBe('lead@example.test')
        ->and($envelope->replyTo[0]->address)->toBe('hello@enigmative.web.id')
        ->and($envelope->subject)->toBe('Proposal website sekolah')
        ->and($mail->headers()->messageId)->toBe($result->providerMessageId)
        ->and($mail->attachments())->toHaveCount(1)
        ->and($mail->attachments()[0]->as)->toBe('Proposal-Website.pdf')
        ->and($mail->attachments()[0]->mime)->toBe('application/pdf');

    expect($mail->render())->toContain('kami lampirkan proposal');
    expect(view('emails.proposals.text', [
        'textBody' => 'Halo lead, kami lampirkan proposal.',
    ])->render())->toContain('kami lampirkan proposal');
});

it('resolves the brevo sender when the proposal transport is brevo', function () {
    expect(app(ProposalEmailSender::class))->toBeInstanceOf(BrevoProposalSender::class);
});

it('refuses to send when brevo smtp credentials are missing', function () {
    config(['mail.mailers.brevo.password' => null]);

    $result = (new BrevoProposalSender(Mail::getFacadeRoot()))->sendProposal(
        brevoProposalTestData($this->pdfPath),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('brevo_not_configured');

    Mail::assertNothingSent();
});

it('refuses to send when the proposal pdf is missing', function () {
    $result = (new BrevoProposalSender(Mail::getFacadeRoot()))->sendProposal(
        brevoProposalTestData('/tmp/prospectra-missing-'.bin2hex(random_bytes(8)).'.pdf'),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('proposal_attachment_missing');

    Mail::assertNothingSent();
});

it('refuses to send to an invalid recipient address', function () {
    $data = brevoProposalTestData($this->pdfPath);

    $result = (new BrevoProposalSender(Mail::getFacadeRoot()))->sendProposal(
        new ProposalEmailData(
            to: "lead@example.test\r\nBcc: hidden@example.test",
            subject: $data->subject,
            htmlBody: $data->htmlBody,
            textBody: $data->textBody,
            attachmentPath: $data->attachmentPath,
            attachmentFilename: $data->attachmentFilename,
        ),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('proposal_recipient_invalid');

    Mail::assertNothingSent();
});

it('classifies smtp authentication failures as non-retryable', function () {
    $factory = Mockery::mock(MailFactory::class);
    $mailer = Mockery::mock(Mailer::class);

    $factory->shouldReceive('mailer')->once()->with('brevo')->andReturn($mailer);
    $mailer->shouldReceive('send')->once()->andThrow(new TransportException(
        'Expected response code "250" but got code "535", with message "535 5.7.8 Authentication failed".',
    ));

    $result = (new BrevoProposalSender($factory))->sendProposal(
        brevoProposalTestData($this->pdfPath),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('brevo_authentication_failed');
});

it('classifies temporary smtp throttling as retryable', function () {
    $factory = Mockery::mock(MailFactory::class);
    $mailer = Mockery::mock(Mailer::class);

    $factory->shouldReceive('mailer')->once()->with('brevo')->andReturn($mailer);
    $mailer->shouldReceive('send')->once()->andThrow(new TransportException(
        'Expected response code "250" but got code "450", with message "450 4.7.1 Too many messages".',
    ));

    $result = (new BrevoProposalSender($factory))->sendProposal(
        brevoProposalTestData($this->pdfPath),
    );

    expect($result->success)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->reason)->toBe('brevo_rate_limited');
});

it('builds an smtp message with alias headers and the pdf attachment', function () {
    config(['mail.mailers.brevo.transport' => 'array']);

    $realManager = Mail::getFacadeRoot()->manager;

    $result = (new BrevoProposalSender($realManager))->sendProposal(
        brevoProposalTestData($this->pdfPath),
    );

    expect($result->success)->toBeTrue();

    $messages = $realManager->mailer('brevo')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    $email = $messages->first()->getOriginalMessage();

    expect($email->getFrom()[0]->getAddress())->toBe('hello@enigmative.web.id')
        ->and($email->getTo()[0]->getAddress())->toBe('lead@example.test')
        ->and($email->getReplyTo()[0]->getAddress())->toBe('hello@enigmative.web.id')
        ->and($email->getSubject())->toBe('Proposal website sekolah')
        ->and($email->getHeaders()->get('Message-ID')->getBodyAsString())
        ->toBe('<'.$result->providerMessageId.'>')
        ->and($email->getAttachments())->toHaveCount(1)
        ->and($email->getAttachments()[0]->getFilename())->toBe('Proposal-Website.pdf')
        ->and($email->getAttachments()[0]->getContentType())->toBe('application/pdf')
        ->and($email->getHtmlBody())->toContain('kami lampirkan proposal')
        ->and($email->getTextBody())->toContain('kami lampirkan proposal');
});

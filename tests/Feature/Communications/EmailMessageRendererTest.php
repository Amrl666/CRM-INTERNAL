<?php

use App\Domain\Communications\DTOs\EmailMessageData;
use App\Domain\Communications\Services\EmailMessageRenderer;

it('renders the banner image without a separate html or text signature', function () {
    $message = new EmailMessageData(to: '', subject: 'Test', body: "Halo\nDunia");
    $renderer = app(EmailMessageRenderer::class);
    $image = 'data:image/jpeg;base64,AAA';

    $html = $renderer->html($message, $image);
    $preview = $renderer->preview($message, $image);

    expect($html)->toContain($image)
        ->and($html)->toContain('Halo<br />')
        ->and($html)->not->toContain('background-color:#111111')
        ->and($preview)->toContain($image)
        ->and($renderer->plainText($message))->toBe($message->body);
});

it('escapes plain text bodies', function () {
    $message = new EmailMessageData(to: '', subject: 'Test', body: 'alert(1) & selamat 5 < 10');

    $html = app(EmailMessageRenderer::class)->html($message, '');

    expect($html)->not->toContain('alert(1) & selamat')
        ->and($html)->toContain('alert(1) &amp; selamat 5 &lt; 10');
});

<?php

use App\Domain\Communications\Services\EmailBannerRenderer;

it('draws the sender name and position into the banner image', function () {
    $renderer = app(EmailBannerRenderer::class);
    $background = (string) file_get_contents(public_path('images/email-footer-banner.jpg'));
    $withText = $renderer->jpeg('Dimas', 'CEO & Founder');

    expect($withText)->not->toBe($background)
        ->and($withText)->toStartWith("\xFF\xD8")
        ->and($renderer->dataUri('Dimas', 'CEO & Founder'))->toStartWith('data:image/jpeg;base64,');
});

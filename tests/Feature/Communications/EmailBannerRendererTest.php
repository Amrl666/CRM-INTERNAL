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

it('stores a generated banner and returns a short preview url', function () {
    $renderer = app(EmailBannerRenderer::class);
    $name = 'Preview '.bin2hex(random_bytes(4));
    $url = $renderer->url($name, 'CEO & Founder');
    $file = storage_path('app/public/email-banners/'.basename((string) parse_url($url, PHP_URL_PATH)));

    expect($url)->toContain('/storage/email-banners/')
        ->and($url)->toContain('.jpg')
        ->and($url)->toContain('?v=')
        ->and(file_exists($file))->toBeTrue();

    @unlink($file);
});

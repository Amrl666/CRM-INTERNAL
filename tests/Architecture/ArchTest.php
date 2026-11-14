<?php

arch('domain layer does not depend on Livewire')
    ->expect('App\Domain')
    ->not->toUse('Livewire');

arch('communication contracts are interfaces')
    ->expect('App\Domain\Communications\Contracts')
    ->toBeInterfaces();

it('leaves no debugging functions in application code', function () {
    $root = dirname(__DIR__, 2);
    $files = glob($root.'/app/**/*.php', GLOB_BRACE) ?? [];
    foreach ($files as $path) {
        $contents = file_get_contents((string) $path);
        expect($contents)->not->toMatch('/\b(dd|dump|var_dump|print_r)\s*\(/');
    }
});

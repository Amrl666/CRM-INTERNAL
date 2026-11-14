<?php

use Illuminate\Support\Facades\File;

arch('domain layer does not depend on Livewire')
    ->expect('App\Domain')
    ->not->toUse('Livewire');

arch('communication contracts are interfaces')
    ->expect('App\Domain\Communications\Contracts')
    ->toBeInterfaces();

it('leaves no debugging functions in application code', function () {
    foreach (File::allFiles(app_path()) as $file) {
        expect($file->getContents())
            ->not->toMatch('/\b(dd|dump|var_dump|print_r)\s*\(/');
    }
});

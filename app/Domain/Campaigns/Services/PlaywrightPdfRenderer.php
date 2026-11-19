<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Contracts\ProposalPdfRenderer;
use Symfony\Component\Process\Process;

final class PlaywrightPdfRenderer implements ProposalPdfRenderer
{
    public function render(string $html, string $outputPath): void
    {
        $directory = storage_path('app/private/proposals/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori sementara PDF.');
        }

        $input = $directory.'/'.bin2hex(random_bytes(12)).'.html';
        file_put_contents($input, $html);

        try {
            $process = new Process([
                'node',
                base_path('resources/js/render-pdf.mjs'),
                $input,
                $outputPath,
            ], base_path(), [
                'PLAYWRIGHT_BROWSERS_PATH' => '/home/LAB-01/.cache/ms-playwright',
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new \RuntimeException('Render PDF gagal: '.trim($process->getErrorOutput()));
            }

            if (filesize($outputPath) < 100) {
                throw new \RuntimeException('Render PDF menghasilkan berkas kosong.');
            }
        } finally {
            @unlink($input);
        }
    }
}

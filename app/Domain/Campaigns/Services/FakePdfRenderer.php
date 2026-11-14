<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Contracts\ProposalPdfRenderer;

final class FakePdfRenderer implements ProposalPdfRenderer
{
    public function render(string $html, string $outputPath): void
    {
        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Tidak dapat membuat direktori PDF.');
        }

        $body = '%PDF-1.4'."\n".'% fake proposal PDF used by automated tests'."\n";
        $body .= 'Stream: '.strlen($html)." bytes\n%%EOF\n";
        file_put_contents($outputPath, $body);
    }
}

<?php

namespace App\Domain\Campaigns\Contracts;

interface ProposalPdfRenderer
{
    public function render(string $html, string $outputPath): void;
}

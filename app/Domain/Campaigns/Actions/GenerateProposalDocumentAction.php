<?php

namespace App\Domain\Campaigns\Actions;

use App\Domain\Audit\Actions\RecordAuditAction;
use App\Domain\Campaigns\Contracts\ProposalPdfRenderer;
use App\Domain\Campaigns\Enums\PdfStatus;
use App\Domain\Campaigns\Models\CampaignRecipient;
use App\Domain\Campaigns\Services\ProposalNumber;
use App\Domain\Campaigns\Services\ProposalTemplateRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class GenerateProposalDocumentAction
{
    public function __construct(
        private readonly ProposalTemplateRenderer $renderer,
        private readonly ProposalPdfRenderer $pdf,
        private readonly RecordAuditAction $audit,
    ) {}

    public function handle(CampaignRecipient $recipient, bool $force = false): CampaignRecipient
    {
        $recipient->loadMissing(['campaign.proposalTemplate', 'contact.company', 'lead.source']);

        if ($recipient->pdf_status === PdfStatus::Ready && ! $force) {
            $disk = config('proposals.storage_disk', 'local');
            if ($recipient->pdf_path !== null && Storage::disk($disk)->exists($recipient->pdf_path)) {
                return $recipient;
            }
        }

        $recipient->update([
            'pdf_status' => PdfStatus::Generating,
            'last_error' => null,
        ]);

        try {
            $campaign = $recipient->campaign;
            $variables = $this->renderer->variables($campaign, $recipient);

            if ($recipient->proposal_number === null) {
                $recipient->update([
                    'proposal_number' => ProposalNumber::next(),
                    'proposal_date' => now($campaign->timezone)->toDateString(),
                ]);
                $recipient->refresh();
                $variables = $this->renderer->variables($campaign, $recipient);
            }

            $html = $this->renderer->render($campaign, $variables);

            $companySlug = Str::slug((string) $recipient->contact->company->name) ?: 'company';
            $filename = sprintf(
                'Proposal-Website-%s-%s.pdf',
                $companySlug,
                preg_replace('/[^A-Za-z0-9-]/', '', $recipient->proposal_number) ?: 'proposal'
            );
            $relativePath = trim((string) config('proposals.document_path', 'proposals'), '/')
                .'/'.$campaign->id.'/'.$recipient->id.'/'.$filename;

            $temporary = tempnam(sys_get_temp_dir(), 'proposal_');
            if ($temporary === false) {
                throw new \RuntimeException('Tidak dapat membuat berkas sementara PDF.');
            }

            try {
                $this->pdf->render($html, $temporary);
                $contents = file_get_contents($temporary);

                if ($contents === false || $contents === '') {
                    throw new \RuntimeException('PDF kosong.');
                }

                Storage::disk((string) config('proposals.storage_disk', 'local'))->put($relativePath, $contents);
            } finally {
                @unlink($temporary);
            }

            $recipient->update([
                'pdf_status' => PdfStatus::Ready,
                'pdf_path' => $relativePath,
                'attachment_path' => $relativePath,
                'attachment_filename' => $filename,
                'snapshot' => array_merge($recipient->snapshot ?? [], [
                    'rendered_variables' => $variables,
                    'template_view' => $campaign->proposalTemplate?->view_path,
                    'template_version' => $campaign->proposal_template_version,
                    'generated_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->audit->handle(
                user: auth()->id(),
                action: 'proposal.pdf_generated',
                subject: $recipient,
                after: ['proposal_number' => $recipient->proposal_number, 'path' => $relativePath],
            );
        } catch (\Throwable $e) {
            $recipient->update([
                'pdf_status' => PdfStatus::Failed,
                'last_error' => Str::limit($e->getMessage(), 1000, ''),
            ]);

            throw $e;
        }

        return $recipient->refresh();
    }
}

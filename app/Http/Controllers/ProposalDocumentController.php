<?php

namespace App\Http\Controllers;

use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Campaigns\Models\CampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProposalDocumentController extends Controller
{
    public function preview(Request $request, Campaign $campaign, CampaignRecipient $recipient): Response
    {
        Gate::authorize('view', $campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        return $this->fileResponse($recipient, false);
    }

    public function download(Request $request, Campaign $campaign, CampaignRecipient $recipient): BinaryFileResponse
    {
        Gate::authorize('view', $campaign);
        abort_unless($recipient->campaign_id === $campaign->id, 404);

        return $this->fileResponse($recipient, true);
    }

    private function fileResponse(CampaignRecipient $recipient, bool $download): BinaryFileResponse
    {
        abort_unless($recipient->pdf_path !== null, 404);

        $disk = Storage::disk((string) config('proposals.storage_disk', 'local'));
        abort_unless($disk->exists($recipient->pdf_path), 404);

        $path = $disk->path($recipient->pdf_path);
        $filename = $recipient->attachment_filename ?: basename($path);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

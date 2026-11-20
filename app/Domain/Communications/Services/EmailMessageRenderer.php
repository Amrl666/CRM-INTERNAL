<?php

namespace App\Domain\Communications\Services;

use App\Domain\Communications\DTOs\EmailMessageData;

final class EmailMessageRenderer
{
    /**
     * Full HTML document used as the HTML part of the Gmail MIME message.
     * $imageSource is either a cid: reference or a data URI.
     */
    public function html(EmailMessageData $message, string $imageSource): string
    {
        return '<html><body style="margin:0;padding:0;background-color:#ffffff;">'
            .'<div style="margin:0 auto;max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111827;">'
            .$this->content($message, $imageSource)
            .'</div></body></html>';
    }

    /** Inline content used for previews inside the CRM UI. */
    public function preview(EmailMessageData $message, string $imageSource): string
    {
        return '<div style="max-width:600px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#111827;">'
            .$this->content($message, $imageSource)
            .'</div>';
    }

    public function plainText(EmailMessageData $message): string
    {
        return $message->body;
    }

    private function content(EmailMessageData $message, string $imageSource): string
    {
        $body = preg_match('/<[a-z][^>]*>/i', $message->body) === 1
            ? $message->body
            : nl2br(e($message->body));

        $parts = [$body];

        if ($imageSource !== '') {
            $parts[] = '<div style="margin-top:24px;">';
            $parts[] = '<img src="'.$imageSource.'" alt="Enigma Collective Team" width="600" '
                .'style="display:block;width:100%;max-width:600px;height:auto;border:0;">';
            $parts[] = '</div>';
        }

        return implode("\r\n", $parts);
    }
}

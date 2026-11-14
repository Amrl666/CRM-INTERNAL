<?php

namespace App\Domain\Campaigns\Services;

use App\Domain\Campaigns\Models\MessageTemplate;

final class MessagePersonalization
{
    /** @param array<string, string|null> $variables */
    public function subject(?string $template, array $variables): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $this->replace($template ?? '', $variables, false)) ?? '');
    }

    /** @param array<string, string|null> $variables */
    public function html(?string $template, array $variables): string
    {
        return $this->replace($template ?? '', $variables, true);
    }

    /** @param array<string, string|null> $variables */
    public function text(?string $template, array $variables): string
    {
        return $this->replace($template ?? '', $variables, false);
    }

    /** @return string[] */
    public function unknownVariables(string $template): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template, $matches);

        $found = $matches[1] ?? [];

        return array_values(array_diff(array_unique($found), MessageTemplate::VARIABLES));
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    private function replace(string $template, array $variables, bool $escapeForHtml): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            if (! in_array($key, MessageTemplate::VARIABLES, true) && $key !== 'proposal_date' && $key !== 'source') {
                continue;
            }

            $value = (string) ($value ?? '');
            $replacements['{{'.$key.'}}'] = $escapeForHtml ? e($value) : $value;
            $replacements['{{ '.$key.' }}'] = $escapeForHtml ? e($value) : $value;
        }

        return strtr($template, $replacements);
    }
}

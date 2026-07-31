<?php

declare(strict_types=1);

namespace App\Issues\Service;

/**
 * Strips common email quoted-reply / signature noise from plain-text bodies.
 */
final class InboundEmailQuoteStripper
{
    public function strip(string $body): string
    {
        $normalized = str_replace("\r\n", "\n", $body);
        $lines = explode("\n", $normalized);
        $kept = [];
        foreach ($lines as $line) {
            if (preg_match('/^On .+ wrote:\s*$/i', trim($line))) {
                break;
            }
            if (preg_match('/^-{2,}\s*$/', trim($line))) {
                break;
            }
            if (preg_match('/^_{2,}\s*$/', trim($line))) {
                break;
            }
            if (str_starts_with(ltrim($line), '>')) {
                break;
            }
            if (preg_match('/^From:\s+/i', $line)) {
                break;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Legal\Service;

use Nowo\Ckeditor5EditorBundle\Security\Ckeditor5HtmlSanitizerInterface;

/**
 * Published legal HTML. The editor kit (`html_sanitizer: strict`, 1.4.7) drops
 * scripts, event handlers, dangerous URLs, and every iframe. This pass runs the
 * kit again on save and on public render, then repeats the strip so a body that
 * bypassed the form transformer is still filtered.
 */
final readonly class LegalPublishedHtml
{
    public function __construct(
        private Ckeditor5HtmlSanitizerInterface $kitSanitizer,
    ) {
    }

    public function sanitize(string $html): string
    {
        return self::stripHazards($this->kitSanitizer->sanitize($html));
    }

    public static function stripHazards(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*\/?>/is', '', $html) ?? $html;
        $html = preg_replace('/\ssrcdoc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+)/i', '', $html) ?? $html;

        return preg_replace(
            '/\s(?:href|src)\s*=\s*(?:"\s*(?:javascript|data|vbscript):[^"]*"|\'\s*(?:javascript|data|vbscript):[^\']*\'|(?:javascript|data|vbscript):[^\s>]*)/i',
            '',
            $html,
        ) ?? $html;
    }
}

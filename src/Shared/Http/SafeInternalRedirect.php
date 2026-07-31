<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Rejects open redirects while allowing same-app relative paths.
 */
final class SafeInternalRedirect
{
    /**
     * Returns a safe relative path; otherwise $fallback.
     *
     * Absolute same-host URLs are reduced to their path+query. Protocol-relative
     * URLs, backslashes, encoded separators, and control characters are rejected.
     */
    public static function resolve(Request $request, string $target, string $fallback): string
    {
        $target = trim($target);
        if ('' === $target) {
            return $fallback;
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $target)) {
            return $fallback;
        }

        $host = $request->getSchemeAndHttpHost();
        if (str_starts_with($target, $host.'/')) {
            $target = substr($target, \strlen($host)) ?: '/';
        }

        if (!str_starts_with($target, '/')) {
            return $fallback;
        }

        if (str_starts_with($target, '//') || str_starts_with($target, '/\\')) {
            return $fallback;
        }

        if (str_contains($target, '\\') || str_contains(strtolower($target), '%5c')) {
            return $fallback;
        }

        // Reject "/\evil" and similar after decoding one level of common encodings.
        $decoded = rawurldecode($target);
        if ($decoded !== $target && (str_contains($decoded, '\\') || str_starts_with($decoded, '//'))) {
            return $fallback;
        }

        return $target;
    }
}

<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Rejects open redirects (protocol-relative //evil.com) while allowing same-app paths.
 */
final class SafeInternalRedirect
{
    /**
     * Returns a safe relative path or same-host absolute URL; otherwise $fallback.
     */
    public static function resolve(Request $request, string $target, string $fallback): string
    {
        $target = trim($target);
        if ('' === $target) {
            return $fallback;
        }

        if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            return $target;
        }

        $host = $request->getSchemeAndHttpHost();
        if (str_starts_with($target, $host.'/')) {
            return $target;
        }

        return $fallback;
    }
}

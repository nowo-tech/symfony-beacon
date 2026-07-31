<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use InvalidArgumentException;

/**
 * Restricts Web Push subscription endpoints to HTTPS push-service hosts (anti-SSRF).
 */
final class WebPushEndpointGuard
{
    /**
     * Host suffixes allowed for browser push endpoints (FCM, Mozilla Autopush, Apple).
     *
     * @var list<string>
     */
    private const array ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        'android.googleapis.com',
        'push.services.mozilla.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
        'push.apple.com',
    ];

    /**
     * @throws InvalidArgumentException when the endpoint is not an allowed push URL
     */
    public function assertSafeEndpoint(string $endpoint): void
    {
        $endpoint = trim($endpoint);
        $parts = parse_url($endpoint);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\is_string($parts['scheme']) || !\is_string($parts['host'])) {
            throw new InvalidArgumentException('Invalid push endpoint URL.');
        }

        if ('https' !== strtolower($parts['scheme'])) {
            throw new InvalidArgumentException('Push endpoint must use https.');
        }

        $host = strtolower($parts['host']);
        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Push endpoint must not use a literal IP address.');
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return;
            }
        }

        throw new InvalidArgumentException('Push endpoint host is not an allowed push service.');
    }
}

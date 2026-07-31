<?php

declare(strict_types=1);

namespace App\Notifications\Service;

/**
 * Verifies Slack request signing (v0 HMAC).
 *
 * @see https://api.slack.com/authentication/verifying-requests-from-slack
 */
final class SlackRequestSignatureVerifier
{
    public const int MAX_SKEW_SECONDS = 300;

    public function isValid(string $signingSecret, string $timestampHeader, string $signatureHeader, string $rawBody, ?int $now = null): bool
    {
        $signingSecret = trim($signingSecret);
        if (\in_array('', [$signingSecret, $timestampHeader, $signatureHeader], true)) {
            return false;
        }

        if (!ctype_digit($timestampHeader)) {
            return false;
        }

        $now ??= time();
        $ts = (int) $timestampHeader;
        if (abs($now - $ts) > self::MAX_SKEW_SECONDS) {
            return false;
        }

        $base = 'v0:'.$timestampHeader.':'.$rawBody;
        $expected = 'v0='.hash_hmac('sha256', $base, $signingSecret);

        return hash_equals($expected, $signatureHeader);
    }
}

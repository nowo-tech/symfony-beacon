<?php

declare(strict_types=1);

namespace App\Notifications\Service;

/**
 * HMAC action tokens for Teams (and similar) interactive HttpPOST callbacks.
 *
 * Claims are signed with the destination signing secret. Unlike Slack request
 * signing, the token travels in the card body Teams posts back to Beacon.
 */
final class InteractionActionToken
{
    public const int DEFAULT_TTL_SECONDS = 604_800; // 7 days

    /**
     * @return array{a: string, d: string, p: string, i: string, exp: int, sig: string}
     */
    public function issueResolveToken(
        string $signingSecret,
        string $destinationUuid,
        string $projectUuid,
        string $issueUuid,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        $now ??= time();
        $exp = $now + $ttlSeconds;
        $claims = [
            'a' => 'resolve',
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
            'exp' => $exp,
        ];
        $claims['sig'] = $this->signature($signingSecret, $claims);

        return $claims;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isValidResolveToken(string $signingSecret, array $payload, ?int $now = null): bool
    {
        $now ??= time();
        $action = $payload['a'] ?? null;
        $destinationUuid = $payload['d'] ?? null;
        $projectUuid = $payload['p'] ?? null;
        $issueUuid = $payload['i'] ?? null;
        $exp = $payload['exp'] ?? null;
        $sig = $payload['sig'] ?? null;

        if ('resolve' !== $action
            || !\is_string($destinationUuid) || '' === $destinationUuid
            || !\is_string($projectUuid) || '' === $projectUuid
            || !\is_string($issueUuid) || '' === $issueUuid
            || !\is_numeric($exp)
            || !\is_string($sig) || '' === $sig
        ) {
            return false;
        }

        $expInt = (int) $exp;
        if ($expInt < $now) {
            return false;
        }

        $expected = $this->signature($signingSecret, [
            'a' => 'resolve',
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
            'exp' => $expInt,
        ]);

        return hash_equals($expected, $sig);
    }

    /**
     * @param array{a: string, d: string, p: string, i: string, exp: int} $claims
     */
    private function signature(string $signingSecret, array $claims): string
    {
        $base = $claims['a']."\n".$claims['d']."\n".$claims['p']."\n".$claims['i']."\n".$claims['exp'];

        return hash_hmac('sha256', $base, $signingSecret);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use InvalidArgumentException;

/**
 * HMAC action tokens for Teams (and similar) interactive callbacks.
 *
 * Claims are signed with the destination signing secret. Resolve uses HttpPOST
 * (token in the card body); Assign uses OpenUri (token in the query string).
 * Each token includes a random nonce (`n`) so controllers can consume-once.
 */
final class InteractionActionToken
{
    public const int DEFAULT_TTL_SECONDS = 86_400; // 24 hours (Assign is OpenUri/query — keep short)

    public const string ACTION_RESOLVE = 'resolve';

    public const string ACTION_ASSIGN = 'assign';

    /**
     * @return array{a: string, d: string, p: string, i: string, n: string, exp: int, sig: string}
     */
    public function issueActionToken(
        string $action,
        string $signingSecret,
        string $destinationUuid,
        string $projectUuid,
        string $issueUuid,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        if (!\in_array($action, [self::ACTION_RESOLVE, self::ACTION_ASSIGN], true)) {
            throw new InvalidArgumentException('Unsupported interaction action: '.$action);
        }

        $now ??= time();
        $exp = $now + $ttlSeconds;
        $claims = [
            'a' => $action,
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
            'n' => bin2hex(random_bytes(16)),
            'exp' => $exp,
        ];
        $claims['sig'] = $this->signature($signingSecret, $claims);

        return $claims;
    }

    /**
     * @return array{a: string, d: string, p: string, i: string, n: string, exp: int, sig: string}
     */
    public function issueResolveToken(
        string $signingSecret,
        string $destinationUuid,
        string $projectUuid,
        string $issueUuid,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        return $this->issueActionToken(
            self::ACTION_RESOLVE,
            $signingSecret,
            $destinationUuid,
            $projectUuid,
            $issueUuid,
            $now,
            $ttlSeconds,
        );
    }

    /**
     * @return array{a: string, d: string, p: string, i: string, n: string, exp: int, sig: string}
     */
    public function issueAssignToken(
        string $signingSecret,
        string $destinationUuid,
        string $projectUuid,
        string $issueUuid,
        ?int $now = null,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): array {
        return $this->issueActionToken(
            self::ACTION_ASSIGN,
            $signingSecret,
            $destinationUuid,
            $projectUuid,
            $issueUuid,
            $now,
            $ttlSeconds,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isValidActionToken(string $action, string $signingSecret, array $payload, ?int $now = null): bool
    {
        if (!\in_array($action, [self::ACTION_RESOLVE, self::ACTION_ASSIGN], true)) {
            return false;
        }

        $now ??= time();
        $payloadAction = $payload['a'] ?? null;
        $destinationUuid = $payload['d'] ?? null;
        $projectUuid = $payload['p'] ?? null;
        $issueUuid = $payload['i'] ?? null;
        $nonce = $payload['n'] ?? null;
        $exp = $payload['exp'] ?? null;
        $sig = $payload['sig'] ?? null;

        if ($action !== $payloadAction
            || !\is_string($destinationUuid) || '' === $destinationUuid
            || !\is_string($projectUuid) || '' === $projectUuid
            || !\is_string($issueUuid) || '' === $issueUuid
            || !\is_string($nonce) || '' === $nonce
            || !is_numeric($exp)
            || !\is_string($sig) || '' === $sig
        ) {
            return false;
        }

        $expInt = (int) $exp;
        if ($expInt < $now) {
            return false;
        }

        $expected = $this->signature($signingSecret, [
            'a' => $action,
            'd' => $destinationUuid,
            'p' => $projectUuid,
            'i' => $issueUuid,
            'n' => $nonce,
            'exp' => $expInt,
        ]);

        return hash_equals($expected, $sig);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isValidResolveToken(string $signingSecret, array $payload, ?int $now = null): bool
    {
        return $this->isValidActionToken(self::ACTION_RESOLVE, $signingSecret, $payload, $now);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isValidAssignToken(string $signingSecret, array $payload, ?int $now = null): bool
    {
        return $this->isValidActionToken(self::ACTION_ASSIGN, $signingSecret, $payload, $now);
    }

    /**
     * Cache key for consume-once tracking (signature is unique per issued token).
     *
     * @param array<string, mixed> $payload
     */
    public function consumeCacheKey(array $payload): ?string
    {
        $sig = $payload['sig'] ?? null;
        if (!\is_string($sig) || '' === $sig) {
            return null;
        }

        return 'teams_action_'.hash('sha256', $sig);
    }

    /**
     * @param array{a: string, d: string, p: string, i: string, n: string, exp: int} $claims
     */
    private function signature(string $signingSecret, array $claims): string
    {
        $base = $claims['a']."\n".$claims['d']."\n".$claims['p']."\n".$claims['i']."\n".$claims['n']."\n".$claims['exp'];

        return hash_hmac('sha256', $base, $signingSecret);
    }
}

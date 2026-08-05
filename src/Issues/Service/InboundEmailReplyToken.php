<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Shared\Settings\Service\InstanceOpsDefaults;
use JsonException;

/**
 * HMAC reply tokens embedded in Reply-To local-parts (reply+{token}).
 *
 * Token payload is base64url(json) + "." + hex hmac.
 * Claims bind both the issue (`i`) and the intended recipient email (`u`).
 * Signing secret comes from Ops defaults (inbound webhook secret).
 */
final readonly class InboundEmailReplyToken
{
    public const int DEFAULT_TTL_SECONDS = 2_592_000; // 30 days

    public function __construct(
        private InstanceOpsDefaults $opsDefaults,
    ) {
    }

    public function issue(string $issueUuid, string $recipientEmail, ?int $now = null, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $now ??= time();
        $claims = [
            'i' => $issueUuid,
            'u' => strtolower(trim($recipientEmail)),
            'exp' => $now + $ttlSeconds,
        ];
        $payload = rtrim(strtr(base64_encode(json_encode($claims, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, $this->opsDefaults->inboundWebhookSecret());

        return $payload.'.'.$sig;
    }

    /**
     * @return array{issueUuid: string, recipientEmail: string}|null
     */
    public function parseValid(string $token, ?int $now = null): ?array
    {
        $now ??= time();
        $parts = explode('.', $token, 2);
        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }
        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->opsDefaults->inboundWebhookSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $pad = 4 - (\strlen($payload) % 4);
        if (4 !== $pad) {
            $payload .= str_repeat('=', $pad);
        }
        try {
            /** @var array<string, mixed>|null $claims */
            $claims = json_decode(base64_decode(strtr($payload, '-_', '+/'), true) ?: '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!\is_array($claims)) {
            return null;
        }
        $issueUuid = $claims['i'] ?? null;
        $recipientEmail = $claims['u'] ?? null;
        $exp = $claims['exp'] ?? null;
        if (!\is_string($issueUuid) || '' === $issueUuid
            || !\is_string($recipientEmail) || '' === $recipientEmail
            || !is_numeric($exp)
        ) {
            return null;
        }
        if ((int) $exp < $now) {
            return null;
        }

        return [
            'issueUuid' => $issueUuid,
            'recipientEmail' => strtolower(trim($recipientEmail)),
        ];
    }

    /**
     * @deprecated Use {@see parseValid()}; kept for callers that only need the issue UUID
     */
    public function isValid(string $token, ?int $now = null): ?string
    {
        $parsed = $this->parseValid($token, $now);

        return $parsed['issueUuid'] ?? null;
    }
}

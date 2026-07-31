<?php

declare(strict_types=1);

namespace App\Issues\Service;

use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * HMAC reply tokens embedded in Reply-To local-parts (reply+{token}).
 *
 * Token payload is base64url(json) + "." + hex hmac.
 */
final readonly class InboundEmailReplyToken
{
    public const int DEFAULT_TTL_SECONDS = 2_592_000; // 30 days

    public function __construct(
        #[Autowire('%beacon.inbound_email.signing_secret%')]
        private string $signingSecret,
    ) {
    }

    public function issue(string $issueUuid, ?int $now = null, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        $now ??= time();
        $claims = [
            'i' => $issueUuid,
            'exp' => $now + $ttlSeconds,
        ];
        $payload = rtrim(strtr(base64_encode(json_encode($claims, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, $this->signingSecret);

        return $payload.'.'.$sig;
    }

    public function isValid(string $token, ?int $now = null): ?string
    {
        $now ??= time();
        $parts = explode('.', $token, 2);
        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            return null;
        }
        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->signingSecret);
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
        $exp = $claims['exp'] ?? null;
        if (!\is_string($issueUuid) || '' === $issueUuid || !is_numeric($exp)) {
            return null;
        }
        if ((int) $exp < $now) {
            return null;
        }

        return $issueUuid;
    }
}

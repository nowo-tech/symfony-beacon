<?php

declare(strict_types=1);

namespace App\Shared\Mercure;

use App\Shared\Settings\Service\InstanceOpsDefaults;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard as KitOutboundUrlGuard;

/**
 * Validates Mercure hub / public HTTP URLs stored in Administration or env.
 *
 * Same policy as notification webhooks ({@see \App\Notifications\Service\OutboundUrlGuard}),
 * via nowo-tech/outbound-url-guard-bundle. Hostnames are not DNS-resolved, so Docker
 * Compose service names (`mercure`, `php`) stay valid without `allowPrivateUrls`.
 * Cloud metadata stays rejected even when private URLs are opted in.
 */
final readonly class MercureHubUrlGuard
{
    public const string RESULT_VALID = 'valid';
    public const string RESULT_INVALID = 'invalid';
    public const string RESULT_UNSAFE = 'unsafe';

    public function __construct(
        private ?InstanceOpsDefaults $opsDefaults = null,
    ) {
    }

    public function classifyHttpUrl(?string $value): string
    {
        $allowPrivate = null !== $this->opsDefaults && $this->opsDefaults->allowPrivateUrls();

        return (new KitOutboundUrlGuard(allowPrivate: $allowPrivate, resolveDns: false))
            ->inspect($value)
            ->result;
    }

    public function isSafeHttpUrl(?string $value): bool
    {
        return self::RESULT_VALID === $this->classifyHttpUrl($value);
    }
}

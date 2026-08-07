<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** IP rate limit for AuthKit magic-login request POSTs (skipped in the test environment). */
final readonly class MagicLoginRateLimitSubscriber extends AuthKitRouteRateLimitSubscriber
{
    public function __construct(
        #[Autowire(service: 'limiter.magic_login')]
        RateLimiterFactory $magicLoginLimiter,
        #[Autowire('%kernel.environment%')]
        string $environment = 'prod',
    ) {
        parent::__construct(
            $magicLoginLimiter,
            $environment,
            'nowo_auth_kit_magic_login_request',
            'Too many magic-link requests.',
        );
    }
}

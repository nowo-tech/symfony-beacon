<?php

declare(strict_types=1);

namespace App\Identity\AuthKit;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** IP rate limit for AuthKit password-reset request POSTs (skipped in the test environment). */
final readonly class PasswordResetRateLimitSubscriber extends AuthKitRouteRateLimitSubscriber
{
    public function __construct(
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactory $passwordResetLimiter,
        #[Autowire('%kernel.environment%')]
        string $environment = 'prod',
    ) {
        parent::__construct(
            $passwordResetLimiter,
            $environment,
            'nowo_auth_kit_reset_password_request',
            'Too many password-reset requests.',
        );
    }
}

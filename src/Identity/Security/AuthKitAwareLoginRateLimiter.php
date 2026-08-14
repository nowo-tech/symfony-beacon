<?php

declare(strict_types=1);

namespace App\Identity\Security;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\LoginThrottleBundle\Entity\LoginAttempt;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * AuthKit posts {@code login_form[_username]}; the LoginThrottleBundle database limiter only
 * reads flat {@code _username}/{@code username}/{@code email}. Without this decorator every
 * attempt is stored with a null username and counted per IP — five guest logins anywhere lock
 * the whole Compose/E2E runner for the throttle window.
 *
 * Also clears DB attempts on {@see reset()} (successful login); the decorated limiter's reset is a no-op.
 */
#[AsDecorator('nowo_login_throttle.database_rate_limiter')]
final class AuthKitAwareLoginRateLimiter implements RequestRateLimiterInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly RequestRateLimiterInterface $inner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function consume(Request $request): RateLimit
    {
        $this->exposeFlatUsername($request);

        return $this->inner->consume($request);
    }

    #[Override]
    public function reset(Request $request): void
    {
        $this->exposeFlatUsername($request);
        $this->inner->reset($request);

        $ipAddress = $request->getClientIp() ?? 'unknown';
        $username = $this->extractUsername($request);

        $qb = $this->entityManager->createQueryBuilder()
            ->delete(LoginAttempt::class, 'la')
            ->where('la.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress);

        if (null === $username || '' === $username) {
            $qb->andWhere('la.username IS NULL');
        } else {
            $qb->andWhere('la.username = :username')
                ->setParameter('username', $username);
        }

        $qb->getQuery()->execute();
    }

    /**
     * Copy AuthKit nested identifier onto flat keys the bundle limiter understands.
     */
    private function exposeFlatUsername(Request $request): void
    {
        $username = $this->extractUsername($request);
        if (null === $username || '' === $username) {
            return;
        }

        if (!$request->request->has('_username')) {
            $request->request->set('_username', $username);
        }
    }

    private function extractUsername(Request $request): ?string
    {
        $loginForm = $request->request->all('login_form');
        if (\is_array($loginForm)) {
            foreach (['_username', 'username', 'email'] as $key) {
                $value = $loginForm[$key] ?? null;
                if (\is_string($value) && '' !== $value) {
                    return $value;
                }
            }
        }

        foreach (['_username', 'username', 'email'] as $key) {
            $value = $request->request->get($key);
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }
}

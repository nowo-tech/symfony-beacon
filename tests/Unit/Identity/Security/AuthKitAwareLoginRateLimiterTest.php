<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security;

use App\Identity\Security\AuthKitAwareLoginRateLimiter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\LoginThrottleBundle\Entity\LoginAttempt;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

final class AuthKitAwareLoginRateLimiterTest extends TestCase
{
    public function testConsumeCopiesNestedUsernameToFlatRequestField(): void
    {
        $rateLimit = $this->createStub(RateLimit::class);
        $inner = $this->createMock(RequestRateLimiterInterface::class);
        $inner->expects(self::once())
            ->method('consume')
            ->willReturnCallback(static function (Request $request) use ($rateLimit): RateLimit {
                self::assertSame('nested@example.com', $request->request->get('_username'));

                return $rateLimit;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('createQueryBuilder');

        $request = Request::create('/', Request::METHOD_POST, [
            'login_form' => ['email' => 'nested@example.com'],
        ]);

        $limiter = new AuthKitAwareLoginRateLimiter($inner, $entityManager);
        self::assertSame($rateLimit, $limiter->consume($request));
    }

    public function testResetUsesFlatEmailAndDeletesOnlyMatchingUsernameAttempts(): void
    {
        $inner = $this->createMock(RequestRateLimiterInterface::class);
        $inner->expects(self::once())
            ->method('reset')
            ->with(self::callback(static function (Request $request): bool {
                self::assertSame('flat@example.com', $request->request->get('_username'));

                return true;
            }));

        $query = $this->createMock(Query::class);
        $query->expects(self::once())->method('execute');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('delete')->with(LoginAttempt::class, 'la')->willReturnSelf();
        $qb->expects(self::once())->method('where')->with('la.ipAddress = :ipAddress')->willReturnSelf();
        $qb->expects(self::exactly(2))->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use ($qb): QueryBuilder {
                self::assertContains([$name, $value], [
                    ['ipAddress', '127.0.0.1'],
                    ['username', 'flat@example.com'],
                ]);

                return $qb;
            },
        );
        $qb->expects(self::once())->method('andWhere')->with('la.username = :username')->willReturnSelf();
        $qb->expects(self::once())->method('getQuery')->willReturn($query);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/', Request::METHOD_POST, ['email' => 'flat@example.com'], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        new AuthKitAwareLoginRateLimiter($inner, $entityManager)->reset($request);
    }
}

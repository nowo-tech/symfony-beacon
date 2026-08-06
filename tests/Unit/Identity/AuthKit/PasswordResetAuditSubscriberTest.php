<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\PasswordResetAuditSubscriber;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\Enum\PasswordResetDeliveryMode;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetNotificationContext;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetRequestedEvent;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetTokenResult;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\RequestStack;

final class PasswordResetAuditSubscriberTest extends TestCase
{
    public function testRecordsPasswordResetRequestWithDeliveryMode(): void
    {
        $subject = new User();
        $subject->setEmail('reset@example.com');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn($subject);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static function (object $action) use ($subject): bool {
                self::assertSame(UserActionType::PasswordResetRequested, $action->getAction());
                self::assertSame($subject, $action->getSubjectUser());
                self::assertSame('  Reset@Example.COM ', $action->getContext()['email']);
                self::assertSame('r***', $action->getContext()['masked']);
                self::assertSame('link', $action->getContext()['delivery']);

                return true;
            },
        ));
        $em->expects(self::once())->method('flush');

        $subscriber = new PasswordResetAuditSubscriber(
            new UserActionRecorder($em, new RequestStack()),
            $users,
        );

        $token = new PasswordResetTokenResult(
            new stdClass(),
            'tok',
            new DateTimeImmutable('+1 hour'),
            PasswordResetDeliveryMode::Link,
        );
        $context = new PasswordResetNotificationContext(
            '  Reset@Example.COM ',
            'https://beacon.test/reset',
            PasswordResetDeliveryMode::Link,
            'r***',
        );

        $subscriber->onPasswordResetRequested(new PasswordResetRequestedEvent($token, $context));
    }
}

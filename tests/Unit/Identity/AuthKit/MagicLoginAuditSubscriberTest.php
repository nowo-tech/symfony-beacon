<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\MagicLoginAuditSubscriber;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginNotificationContext;
use Nowo\AuthKitBundle\MagicLogin\MagicLoginRequestedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

final class MagicLoginAuditSubscriberTest extends TestCase
{
    public function testRecordsWithSubjectWhenUserExists(): void
    {
        $subject = new User();
        $subject->setEmail('magic@example.com');

        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturnCallback(
            static function (string $email) use ($subject): User {
                self::assertSame('magic@example.com', $email);

                return $subject;
            },
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $subscriber = new MagicLoginAuditSubscriber(
            new UserActionRecorder($em, new RequestStack()),
            $users,
        );

        $subscriber->onMagicLoginRequested(new MagicLoginRequestedEvent(
            new MagicLoginNotificationContext(
                '  Magic@Example.COM ',
                'https://beacon.test/magic',
                new DateTimeImmutable('+10 minutes'),
                'm***@example.com',
            ),
        ));

        $this->addToAssertionCount(1);
    }

    public function testRecordsWithoutSubjectWhenUserMissing(): void
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static function (object $action): bool {
                self::assertSame(UserActionType::MagicLoginRequested, $action->getAction());
                self::assertNull($action->getSubjectUser());
                self::assertSame('unknown@example.com', $action->getContext()['email']);
                self::assertSame('u***', $action->getContext()['masked']);

                return true;
            },
        ));
        $em->expects(self::once())->method('flush');

        $subscriber = new MagicLoginAuditSubscriber(
            new UserActionRecorder($em, new RequestStack()),
            $users,
        );

        $subscriber->onMagicLoginRequested(new MagicLoginRequestedEvent(
            new MagicLoginNotificationContext(
                'unknown@example.com',
                'https://beacon.test/magic',
                new DateTimeImmutable('+10 minutes'),
                'u***',
            ),
        ));
    }
}

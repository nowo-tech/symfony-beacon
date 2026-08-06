<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class UserActionRecorderTest extends TestCase
{
    public function testRecordPersistsWithoutIpWhenNoRequest(): void
    {
        $actor = new User();
        $actor->setEmail('actor@example.com');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserAction::class));
        $em->expects(self::never())->method('flush');

        $recorder = new UserActionRecorder($em, new RequestStack());
        $entry = $recorder->record(UserActionType::UserCreated, $actor, null, ['email' => 'new@example.com']);

        self::assertSame(UserActionType::UserCreated, $entry->getAction());
        self::assertSame($actor, $entry->getActor());
        self::assertSame(['email' => 'new@example.com'], $entry->getContext());
        self::assertNull($entry->getIpAddress());
    }

    public function testRecordCapturesClientIpFromRequest(): void
    {
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $stack = new RequestStack();
        $stack->push($request);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserAction::class));

        $entry = (new UserActionRecorder($em, $stack))->record(UserActionType::UserEnabled, null);

        self::assertSame('203.0.113.10', $entry->getIpAddress());
    }

    public function testRecordAndFlushPersistsThenFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserAction::class));
        $em->expects(self::once())->method('flush');

        $entry = (new UserActionRecorder($em, new RequestStack()))->recordAndFlush(
            UserActionType::AccountExported,
            null,
        );

        self::assertSame(UserActionType::AccountExported, $entry->getAction());
    }
}

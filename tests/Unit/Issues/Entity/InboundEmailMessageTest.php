<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Issues\Entity\InboundEmailMessage;
use PHPUnit\Framework\TestCase;

final class InboundEmailMessageTest extends TestCase
{
    public function testAccessorsExposeMessageCommentAndCreationTimestamp(): void
    {
        $message = new InboundEmailMessage();
        self::assertNull($message->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $message->getCreatedAt());

        $message
            ->setMessageId('<msg@example.test>')
            ->setCommentUuid('comment-uuid');

        self::assertSame('<msg@example.test>', $message->getMessageId());
        self::assertSame('comment-uuid', $message->getCommentUuid());

        $message->setCommentUuid(null);
        self::assertNull($message->getCommentUuid());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Service\InboundEmailQuoteStripper;
use App\Issues\Service\InboundEmailReplyToken;
use PHPUnit\Framework\TestCase;

final class InboundEmailUnitTest extends TestCase
{
    public function testReplyTokenRoundTrip(): void
    {
        $svc = new InboundEmailReplyToken('secret');
        $token = $svc->issue('issue-uuid', 1_700_000_000, 3600);
        self::assertSame('issue-uuid', $svc->isValid($token, 1_700_000_100));
        self::assertNull($svc->isValid($token, 1_700_003_700));
        self::assertNull($svc->isValid($token.'x', 1_700_000_100));
    }

    public function testQuoteStripperRemovesQuotedReply(): void
    {
        $body = new InboundEmailQuoteStripper()->strip("Hello\n\nOn Mon, someone wrote:\n> prior");
        self::assertSame('Hello', $body);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues;

use App\Issues\Service\InboundEmailQuoteStripper;
use App\Issues\Service\InboundEmailReplyToken;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class InboundEmailUnitTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testReplyTokenRoundTrip(): void
    {
        $svc = new InboundEmailReplyToken($this->opsDefaultsWith(static function (InstanceSettings $settings): void {
            $settings->setInboundWebhookSecret('secret');
        }));
        $token = $svc->issue('issue-uuid', 'alice@example.com', 1_700_000_000, 3600);
        $parsed = $svc->parseValid($token, 1_700_000_100);
        self::assertSame(['issueUuid' => 'issue-uuid', 'recipientEmail' => 'alice@example.com'], $parsed);
        self::assertSame('issue-uuid', $svc->isValid($token, 1_700_000_100));
        self::assertNull($svc->parseValid($token, 1_700_003_700));
        self::assertNull($svc->parseValid($token.'x', 1_700_000_100));
    }

    public function testQuoteStripperRemovesQuotedReply(): void
    {
        $body = new InboundEmailQuoteStripper()->strip("Hello\n\nOn Mon, someone wrote:\n> prior");
        self::assertSame('Hello', $body);
    }

    public function testQuoteStripperHandlesCommonMarkersAndWhitespace(): void
    {
        $stripper = new InboundEmailQuoteStripper();

        self::assertSame('Top', $stripper->strip("Top\n\n---\nquoted"));
        self::assertSame('Top', $stripper->strip("Top\r\n\r\n___\r\nquoted"));
        self::assertSame('Top', $stripper->strip("Top\n\nFrom: someone@example.com\nBody"));
        self::assertSame('Only me', $stripper->strip("Only me\n> quote line"));
        self::assertSame('', $stripper->strip("   \n\t  "));
        self::assertSame('No quotes here', $stripper->strip('No quotes here'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\InboundEmailReplyToken;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use PHPUnit\Framework\TestCase;

final class InboundEmailReplyTokenTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    private InboundEmailReplyToken $tokens;

    protected function setUp(): void
    {
        $ops = $this->opsDefaultsWith(static function ($settings): void {
            $settings->setInboundWebhookSecret('test-inbound-secret');
        });
        $this->tokens = new InboundEmailReplyToken($ops);
    }

    public function testIssueAndParseRoundTrip(): void
    {
        $now = 1_700_000_000;
        $token = $this->tokens->issue('issue-uuid-1', ' Alice@Example.com ', $now, 3600);

        self::assertSame(
            [
                'issueUuid' => 'issue-uuid-1',
                'recipientEmail' => 'alice@example.com',
            ],
            $this->tokens->parseValid($token, $now + 10),
        );
        self::assertSame('issue-uuid-1', $this->tokens->isValid($token, $now + 10));
    }

    public function testRejectsTamperedExpiredAndMalformed(): void
    {
        $now = 1_700_000_000;
        $token = $this->tokens->issue('issue-uuid-1', 'a@example.com', $now, 60);

        self::assertNull($this->tokens->parseValid($token.'x', $now));
        self::assertNull($this->tokens->parseValid($token, $now + 120));
        self::assertNull($this->tokens->parseValid('not-a-token', $now));
        self::assertNull($this->tokens->parseValid('.', $now));
        self::assertNull($this->tokens->isValid('bad', $now));
    }
}

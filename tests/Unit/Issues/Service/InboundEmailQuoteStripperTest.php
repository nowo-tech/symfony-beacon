<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\InboundEmailQuoteStripper;
use PHPUnit\Framework\TestCase;

final class InboundEmailQuoteStripperTest extends TestCase
{
    private InboundEmailQuoteStripper $stripper;

    protected function setUp(): void
    {
        $this->stripper = new InboundEmailQuoteStripper();
    }

    public function testKeepsBodyBeforeQuotedReplyMarkers(): void
    {
        $body = "Thanks for the fix.\r\n\r\nOn Mon, Alice wrote:\r\n> old quote\r\n";

        self::assertSame('Thanks for the fix.', $this->stripper->strip($body));
    }

    public function testStopsAtSignatureAndQuotedLines(): void
    {
        self::assertSame('Hello', $this->stripper->strip("Hello\n--\nSignature"));
        self::assertSame('Hello', $this->stripper->strip("Hello\n___\nFooter"));
        self::assertSame('Hello', $this->stripper->strip("Hello\n> quoted"));
        self::assertSame('Hello', $this->stripper->strip("Hello\nFrom: bob@example.com"));
    }

    public function testReturnsTrimmedBodyWhenNoMarkers(): void
    {
        self::assertSame("Line one\nLine two", $this->stripper->strip("  Line one\nLine two  "));
    }
}

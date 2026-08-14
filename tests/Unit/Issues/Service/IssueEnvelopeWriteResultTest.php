<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Entity\Issue;
use App\Issues\Service\IssueEnvelopeWriteResult;
use PHPUnit\Framework\TestCase;

final class IssueEnvelopeWriteResultTest extends TestCase
{
    public function testSkippedFactoryAndSuccessPayload(): void
    {
        $skipped = IssueEnvelopeWriteResult::skipped();
        self::assertTrue($skipped->skipped);
        self::assertNull($skipped->issue);
        self::assertFalse($skipped->isNew);
        self::assertFalse($skipped->countsTowardVolumeThreshold);

        $issue = new Issue();
        $ok = new IssueEnvelopeWriteResult(
            skipped: false,
            issue: $issue,
            isNew: true,
            isRegression: true,
            environment: 'prod',
            release: '1.2.3',
            countsTowardVolumeThreshold: true,
        );
        self::assertFalse($ok->skipped);
        self::assertSame($issue, $ok->issue);
        self::assertTrue($ok->isNew);
        self::assertTrue($ok->isRegression);
        self::assertSame('prod', $ok->environment);
        self::assertSame('1.2.3', $ok->release);
        self::assertTrue($ok->countsTowardVolumeThreshold);
    }
}

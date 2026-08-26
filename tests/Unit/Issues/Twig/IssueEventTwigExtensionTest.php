<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Twig;

use App\Issues\Dto\QueryFacts;
use App\Issues\Service\IssueStackPresenter;
use App\Issues\Service\QueryFactsExtractor;
use App\Issues\Twig\IssueEventTwigExtension;
use PHPUnit\Framework\TestCase;

final class IssueEventTwigExtensionTest extends TestCase
{
    public function testQueryFactsAndStackHelpers(): void
    {
        $ext = new IssueEventTwigExtension(new QueryFactsExtractor(), new IssueStackPresenter());
        self::assertCount(2, $ext->getFunctions());
        self::assertNull($ext->queryFacts('nope'));
        self::assertSame([], $ext->stackFrames(null));

        $facts = $ext->queryFacts([
            'extra' => ['sql' => 'SELECT 1'],
        ]);
        self::assertInstanceOf(QueryFacts::class, $facts);
        self::assertSame('SELECT 1', $facts->sql);
        self::assertSame(QueryFacts::SOURCE_STRUCTURED, $facts->source);
        self::assertSame('SELECT 1', $facts->toArray()['sql']);
        self::assertFalse($facts->toArray()['sql_truncated']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Service\IssueMentionParser;
use App\Project\Repository\ProjectMembershipRepository;
use PHPUnit\Framework\TestCase;

final class IssueMentionParserExtractTest extends TestCase
{
    public function testExtractTokensIgnoresEmailLikeNoiseAndDedupes(): void
    {
        $parser = new IssueMentionParser($this->createStub(ProjectMembershipRepository::class));
        $tokens = $parser->extractTokens('Hi @Alice, see @bob and @Alice again. Contact support@example.com is not a mention.');

        self::assertSame(['alice', 'bob'], $tokens);
    }
}

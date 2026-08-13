<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Service\IssueEnvelopeWriteResult;
use App\Issues\Service\IssueMentionParser;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueMentionParserTest extends TestCase
{
    public function testExtractTokensAndResolveMentions(): void
    {
        $author = $this->user(1, 'author@example.com', 'Author');
        $alice = $this->user(2, 'alice@example.com', 'Alice Wonder');
        $bob = $this->user(3, 'bob@acme.test', 'Bob');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findUsersByProject')->willReturn([$author, $alice, $bob]);
        $parser = new IssueMentionParser($memberships);

        self::assertSame(['alice', 'bob'], $parser->extractTokens('Hi @alice and @Bob, ignore @@'));
        self::assertSame([], $parser->extractTokens('no mentions here'));

        $matched = $parser->resolveMentions(new Project(), 'Ping @alice.wonder and @author@example.com and @alice', $author);
        self::assertCount(1, $matched);
        self::assertSame($alice, $matched[0]);

        $byEmail = $parser->resolveMentions(new Project(), 'See @bob@acme.test', null);
        self::assertSame([$bob], $byEmail);
    }

    public function testEnvelopeWriteResultSkippedFactory(): void
    {
        $result = IssueEnvelopeWriteResult::skipped();
        self::assertTrue($result->skipped);
        self::assertNull($result->issue);
        self::assertFalse($result->isNew);
    }

    private function user(int $id, string $email, string $display): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($display);
        (new ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}

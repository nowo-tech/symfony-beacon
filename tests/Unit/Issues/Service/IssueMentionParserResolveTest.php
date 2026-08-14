<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Service\IssueMentionParser;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class IssueMentionParserResolveTest extends TestCase
{
    private ProjectMembershipRepository&Stub $membershipRepository;
    private IssueMentionParser $parser;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->parser = new IssueMentionParser($this->membershipRepository);
    }

    public function testEmptyTokensSkipRepository(): void
    {
        self::assertSame([], $this->parser->resolveMentions(new Project(), 'no mentions here'));
    }

    public function testMatchesLocalDisplayAndEmailAndDedupesExcludingAuthor(): void
    {
        $project = new Project();
        $author = $this->user(1, 'author@example.com', 'Author');
        $alice = $this->user(2, 'alice@example.com', 'Alice Wonder');
        $bob = $this->user(3, 'bob@example.com', 'Bob');
        $this->membershipRepository->method('findUsersByProject')->willReturn([$author, $alice, $bob]);

        $matched = $this->parser->resolveMentions(
            $project,
            'Hey @alice and @Alice.Wonder and @bob@example.com and @author',
            $author,
        );

        self::assertSame([$alice, $bob], $matched);
    }

    public function testUnknownTokenYieldsEmpty(): void
    {
        $this->membershipRepository->method('findUsersByProject')->willReturn([
            $this->user(9, 'known@example.com', 'Known'),
        ]);

        self::assertSame([], $this->parser->resolveMentions(new Project(), 'ping @nobody'));
    }

    private function user(int $id, string $email, string $displayName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}

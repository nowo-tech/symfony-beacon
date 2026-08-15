<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use App\Project\Service\ProjectReadTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectReadTokenManagerTest extends TestCase
{
    public function testCreateHashesTokenUsesDefaultLabelAndPersists(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $actor = new User();
        $actor->setEmail('owner@example.com');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::once())->method('flush');

        $created = $this->manager($em)->create($project, $actor, '   ');
        self::assertSame('Read token', $created['token']->getLabel());
        self::assertSame(12, \strlen($created['token']->getPrefix()));
        self::assertStringStartsWith('brt_', $created['rawToken']);
        self::assertSame(hash('sha256', $created['rawToken']), $created['token']->getTokenHash());
    }

    public function testRevokeIsNoOpWhenAlreadyInactive(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $actor = new User();
        $actor->setEmail('owner@example.com');

        $token = new ProjectReadToken();
        $token->setProject($project);
        $token->setLabel('x');
        $token->setPrefix('brt_deadbeef');
        $token->setTokenHash(hash('sha256', 'brt_deadbeef00'));
        $token->revoke();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $this->manager($em)->revoke($token, $actor);
    }

    public function testRevokeWithoutProjectThrows(): void
    {
        $token = new ProjectReadToken();
        $this->expectException(RuntimeException::class);
        $this->manager($this->createStub(EntityManagerInterface::class))->revoke($token, new User());
    }

    public function testAuthenticateRejectsInvalidAndMarksUsedOnHit(): void
    {
        $manager = $this->manager($this->createStub(EntityManagerInterface::class));
        self::assertNull($manager->authenticate(''));
        self::assertNull($manager->authenticate('not-brt'));

        $token = new ProjectReadToken();
        $raw = 'brt_'.str_repeat('ab', 24);
        $token->setLabel('bot');
        $token->setPrefix(substr($raw, 0, 12));
        $token->setTokenHash(hash('sha256', $raw));
        $token->setProject(new Project()->setSlug('p')->setName('P'));

        $repo = $this->createStub(ProjectReadTokenRepository::class);
        $repo->method('findActiveByTokenHash')->willReturn($token);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $found = $this->manager($em, $repo)->authenticate($raw);
        self::assertSame($token, $found);
        self::assertNotNull($token->getLastUsedAt());
    }

    private function manager(
        EntityManagerInterface $em,
        ?ProjectReadTokenRepository $repo = null,
    ): ProjectReadTokenManager {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        return new ProjectReadTokenManager(
            $em,
            $repo ?? $this->createStub(ProjectReadTokenRepository::class),
            ProjectAccessServiceFactory::create(
                $this->createStub(ProjectMembershipRepository::class),
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            new UserActionRecorder($em, new RequestStack()),
        );
    }
}

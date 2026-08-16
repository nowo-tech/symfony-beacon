<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectShareLinkManager;
use App\Tests\Support\ProjectAccessServiceFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectShareLinkManagerTest extends TestCase
{
    public function testCreateRejectsValidationErrors(): void
    {
        $project = $this->project(1);
        $other = $this->project(2);
        $actor = new User();
        $actor->setEmail('owner@example.com');
        $issue = new Issue();
        $issue->setProject($other);
        $issue->setFingerprint('fp');
        $issue->setTitle('x');

        $manager = $this->manager($this->createStub(EntityManagerInterface::class));

        try {
            $manager->create($project, $actor, $issue, new DateTimeImmutable('+1 day'));
            self::fail('expected issue_wrong_project');
        } catch (InvalidArgumentException $e) {
            self::assertSame('issue_wrong_project', $e->getMessage());
        }

        try {
            $manager->create($project, $actor, null, new DateTimeImmutable('-1 minute'));
            self::fail('expected expires_in_past');
        } catch (InvalidArgumentException $e) {
            self::assertSame('expires_in_past', $e->getMessage());
        }

        try {
            $manager->create($project, $actor, null, new DateTimeImmutable('+40 days'));
            self::fail('expected expires_too_far');
        } catch (InvalidArgumentException $e) {
            self::assertSame('expires_too_far', $e->getMessage());
        }

        try {
            $manager->create($project, $actor, null, new DateTimeImmutable('+1 day'), 0);
            self::fail('expected max_uses_invalid');
        } catch (InvalidArgumentException $e) {
            self::assertSame('max_uses_invalid', $e->getMessage());
        }
    }

    public function testCreatePersistsHashedToken(): void
    {
        $project = $this->project(1);
        $actor = new User();
        $actor->setEmail('owner@example.com');
        $expires = new DateTimeImmutable('+2 days');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::once())->method('flush');

        $created = $this->manager($em)->create($project, $actor, null, $expires, 3);
        self::assertSame(hash('sha256', $created['rawToken']), $created['link']->getTokenHash());
        self::assertSame(3, $created['link']->getMaxUses());
    }

    public function testRevokeMissingProjectThrows(): void
    {
        $manager = $this->manager($this->createStub(EntityManagerInterface::class));
        $this->expectException(RuntimeException::class);
        $manager->revoke(new ProjectShareLink(), new User());
    }

    public function testFindUsableByRawTokenAndConsume(): void
    {
        $project = $this->project(1);
        $user = new User();
        $user->setEmail('viewer@example.com');
        $raw = bin2hex(random_bytes(16));
        $link = new ProjectShareLink();
        $link->setProject($project);
        $link->setTokenHash(hash('sha256', $raw));
        $link->setExpiresAt(new DateTimeImmutable('+1 day'));
        $link->setMaxUses(5);

        $repo = $this->createMock(ProjectShareLinkRepository::class);
        $repo->method('findOneByTokenHash')->willReturnCallback(
            static fn (string $hash): ?ProjectShareLink => $hash === hash('sha256', $raw) ? $link : null,
        );
        $repo->expects(self::once())->method('tryClaimUse')->with($link)->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('refresh')->with($link);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::once())->method('flush');

        $manager = $this->manager($em, $repo);
        self::assertSame($link, $manager->findUsableByRawToken($raw));
        self::assertNull($manager->findUsableByRawToken('missing'));

        $manager->consume($link, $user);
    }

    public function testRevokeReturnsEarlyWhenAlreadyRevokedAndConsumeRejectsWrongIssueProject(): void
    {
        $project = $this->project(1);
        $actor = new User();
        $actor->setEmail('owner@example.com');

        $revoked = new ProjectShareLink();
        $revoked->setProject($project)->setCreatedBy($actor)->setExpiresAt(new DateTimeImmutable('+1 day'))->setTokenHash('hash');
        $revoked->revoke();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');
        $this->manager($em)->revoke($revoked, $actor);

        $wrongIssue = new Issue();
        $wrongIssue->setProject($this->project(2));
        $wrongIssue->setFingerprint('wrong');
        $wrongIssue->setTitle('Wrong project');

        $link = new ProjectShareLink();
        $link->setProject($project)->setIssue($wrongIssue)->setExpiresAt(new DateTimeImmutable('+1 day'))->setTokenHash('hash');

        try {
            $this->manager($this->createStub(EntityManagerInterface::class))->consume($link, $actor);
            self::fail('expected issue_wrong_project');
        } catch (RuntimeException $e) {
            self::assertSame('issue_wrong_project', $e->getMessage());
        }
    }

    private function manager(
        EntityManagerInterface $em,
        ?ProjectShareLinkRepository $repo = null,
    ): ProjectShareLinkManager {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        return new ProjectShareLinkManager(
            $em,
            $repo ?? $this->createStub(ProjectShareLinkRepository::class),
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

    private function project(int $id): Project
    {
        $project = new Project();
        $project->setSlug('p'.$id);
        $project->setName('P'.$id);
        $ref = new ReflectionProperty(Project::class, 'id');
        $ref->setValue($project, $id);

        return $project;
    }
}

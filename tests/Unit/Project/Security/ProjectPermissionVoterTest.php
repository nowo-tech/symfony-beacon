<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Security;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Security\ProjectPermissionVoter;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ProjectPermissionVoterTest extends TestCase
{
    private ProjectMembershipRepository&Stub $membershipRepository;
    private AuthorizationCheckerInterface&Stub $authorizationChecker;
    private ProjectPermissionVoter $voter;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->method('isGranted')->willReturn(false);

        $accessService = ProjectAccessServiceFactory::create(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $this->authorizationChecker,
            new RequestStack(),
        );
        $this->voter = new ProjectPermissionVoter($accessService);
    }

    public function testAbstainsWhenSubjectIsNotProject(): void
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, null, [ProjectPermission::VIEW]),
        );
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, new Project(), ['not.a.permission']),
        );
    }

    public function testDeniesWhenTokenUserIsNotAppUser(): void
    {
        $token = new UsernamePasswordToken(new InMemoryUser('anon', null), 'main', ['ROLE_USER']);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, new Project(), [ProjectPermission::VIEW]),
        );
    }

    public function testDeniesWhenAccessIsNull(): void
    {
        $user = new User();
        $project = new Project();
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $project, [ProjectPermission::VIEW]),
        );
    }

    public function testGrantsWhenMembershipAllowsPermission(): void
    {
        $user = new User();
        $project = new Project();
        $membership = new ProjectMembership()
            ->setProject($project)
            ->setUser($user)
            ->setRole(ProjectRole::Member);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($membership);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $project, [ProjectPermission::ISSUES_TRIAGE]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $project, [ProjectPermission::DELETE]),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserGroupRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Notifications\Repository\NotificationDeliveryAttemptRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\AdminProjectShowPageBuilder;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipFormSupport;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use App\Project\Service\ProjectOpsStatsService;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use App\Shared\Health\MessengerQueueHealth;
use App\Tests\Support\ProjectAccessServiceFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AdminProjectShowPageBuilderTest extends TestCase
{
    public function testBuildAssemblesAdminProjectPage(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);
        $actor = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);
        $memberUser = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($memberUser, 2);
        $membership = new ProjectMembership()->setProject($project)->setUser($memberUser)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 11);
        $project->addMembership($membership);

        $formView = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $formFactory->method('createNamed')->willReturn($form);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/projects/x');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('hydrateAccessGraph');
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);
        $memberships->method('countOwnersByProjectIds')->willReturn([9 => 1]);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $auth,
        );
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));
        $recorder = new UserActionRecorder($em, new RequestStack());

        $userGroups = $this->createStub(UserGroupRepository::class);
        $userGroups->method('findAllOrdered')->willReturn([]);
        $groupMemberships = $this->createStub(UserGroupMembershipRepository::class);
        $groupMemberships->method('countByGroupIds')->willReturn([]);
        $attempts = $this->createStub(NotificationDeliveryAttemptRepository::class);
        $attempts->method('findRecentByDestinations')->willReturn([]);
        $actions = $this->createStub(UserActionRepository::class);
        $actions->method('findForProject')->willReturn([]);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByProjectAndStatus')->willReturn(0);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProject')->willReturn(0);
        $events->method('findLastReceivedAtForProject')->willReturn(null);

        $builder = new AdminProjectShowPageBuilder(
            $formFactory,
            $urls,
            new CsrfOnlyFormFactory($formFactory),
            new GetFilterFormFactory($formFactory),
            $projects,
            new ProjectMembershipManager(
                $this->createStub(UserRepository::class),
                $memberships,
                $policy,
                $recorder,
                $em,
            ),
            new ProjectGroupAccessManager($groups, $policy, $recorder, $em),
            new ProjectMembershipFormSupport($projects, $userGroups),
            $memberships,
            $groupMemberships,
            $userGroups,
            $attempts,
            new ProjectOpsStatsService($issues, $events),
            new MessengerQueueHealth($em),
            $actions,
        );

        $page = $builder->build($project, $actor, Request::create('/admin/projects/'.$project->getUuid()));
        self::assertSame($project, $page['project']);
        self::assertSame(1, $page['ownerCount']);
        self::assertArrayHasKey(11, $page['removeMemberForms']);
        self::assertArrayHasKey(11, $page['memberRoleForms']);
        self::assertArrayHasKey('ingestToggleForm', $page);
        self::assertArrayHasKey('deleteProjectForm', $page);
        self::assertSame(0, $page['opsStats']['open_issues']);
        self::assertNotEmpty($page['projectAuditActions']);
    }
}

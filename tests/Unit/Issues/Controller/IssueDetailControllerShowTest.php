<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Controller\IssueDetailController;
use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueShowTab;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueCommentRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueDuplicateMarker;
use App\Issues\Service\IssueShowPageBuilder;
use App\Issues\Service\IssueStatusChanger;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Tests\Support\ProjectAccessServiceFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class IssueDetailControllerShowTest extends TestCase
{
    public function testShowRendersIssuePageAndRecordsOpen(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);
        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('Boom')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $events = $this->createStub(EventRepository::class);
        $events->method('findLatestForIssue')->willReturn([]);
        $events->method('occurrenceStatsForIssue')->willReturn(new IssueOccurrenceStats(0, 0, 0, 0));
        $comments = $this->createStub(IssueCommentRepository::class);
        $comments->method('findLatestForIssue')->willReturn([]);
        $history = $this->createStub(IssueHistoryEntryRepository::class);
        $history->method('findLatestForIssue')->willReturn([]);
        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findSimilarIssues')->willReturn([]);
        $issues->method('findDuplicateCandidates')->willReturn([]);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/x');

        $pageBuilder = new IssueShowPageBuilder($events, $comments, $history, $issues, $formFactory, $urls);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $controller = new IssueDetailController(
            $em,
            new ReflectionClass(IssueAssigneeChanger::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueCommentCreator::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(IssueDuplicateMarker::class)->newInstanceWithoutConstructor(),
            $pageBuilder,
            new ReflectionClass(IssueStatusChanger::class)->newInstanceWithoutConstructor(),
            ProjectAccessServiceFactory::create(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $this->createStub(AuthorizationCheckerInterface::class),
                new RequestStack(),
            ),
            new UserActionRecorder($em, new RequestStack()),
        );

        $seen = [];
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->show($project, $issue)->getContent());
        self::assertSame($issue, $seen['issue/show.html.twig']['issue']);
        self::assertSame($project, $seen['issue/show.html.twig']['project']);
        self::assertSame(IssueShowTab::Main, $seen['issue/show.html.twig']['issueTab']);
    }
}

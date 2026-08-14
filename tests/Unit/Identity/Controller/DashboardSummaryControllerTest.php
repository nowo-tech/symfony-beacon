<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Controller;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Controller\DashboardSummaryController;
use App\Identity\Entity\User;
use App\Issues\Repository\IssueMentionRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectsProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class DashboardSummaryControllerTest extends TestCase
{
    public function testIndexAggregatesSummaryCards(): void
    {
        $user = (new User())->setEmail('dev@example.com');
        $project = (new Project())->setName('Acme')->setSlug('acme');

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAccessibleByUser')->willReturn([$project]);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countAssignments')->willReturnOnConsecutiveCalls(3, 1);
        $mentions = $this->createStub(IssueMentionRepository::class);
        $mentions->method('countInboxForUser')->willReturn(2);
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('countWithFailedLastDeliveryInProjects')->willReturn(4);

        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->incrementErrorCount(7);
        $daily = $this->createStub(DailyProjectStatRepository::class);
        $daily->method('findLastDaysForProjects')->willReturn([[$stat]]);

        $controller = new DashboardSummaryController(
            new AccessibleProjectsProvider($projects, new RequestStack()),
            $issues,
            $mentions,
            $destinations,
            $daily,
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context): string {
            self::assertSame('dashboard/summary.html.twig', $name);
            self::assertSame([
                'mine_open' => 3,
                'unassigned_open' => 1,
                'unread_mentions' => 2,
                'failed_deliveries' => 4,
                'errors_today' => 7,
                'project_count' => 1,
            ], $context['summary']);

            return 'summary';
        });
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('summary', $controller->index()->getContent());
    }
}

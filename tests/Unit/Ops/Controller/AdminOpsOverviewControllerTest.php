<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ops\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Ops\Controller\AdminOpsOverviewController;
use App\Ops\Messenger\MessengerQueueHealth;
use App\Ops\Service\OpsOverviewService;
use App\Ops\Service\SecurityPosture;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectOpsStatsService;
use App\Tests\Support\InstanceOpsDefaultsTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class AdminOpsOverviewControllerTest extends TestCase
{
    use InstanceOpsDefaultsTestTrait;

    public function testRendersOverviewAndIgnoresInvalidProjectFilter(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findAllOrdered')->willReturn([$project]);
        $projects->method('findOneBy')->willReturn(null);

        $issues = $this->createStub(IssueSearchRepository::class);
        $issues->method('countByStatusForProjectIds')->willReturn([1 => 0]);
        $events = $this->createStub(EventRepository::class);
        $events->method('countReceivedSinceForProjectIds')->willReturn([1 => 0]);
        $events->method('findLastReceivedAtForProjectIds')->willReturn([]);
        $daily = $this->createStub(DailyProjectStatRepository::class);
        $daily->method('findLastDaysForProjects')->willReturn([]);
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findWithFailedLastDelivery')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willThrowException(new RuntimeException('offline'));

        $formView = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);
        $formFactory->method('createNamed')->willReturn($form);

        $rendered = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$rendered): string {
            $rendered = [$name, $context];

            return 'ops';
        });
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/ops');

        $controller = new AdminOpsOverviewController(
            new OpsOverviewService(
                new MessengerQueueHealth($em),
                $projects,
                new ProjectOpsStatsService($issues, $events),
                $daily,
                $destinations,
            ),
            $projects,
            new GetFilterFormFactory($formFactory),
            new SecurityPosture($this->opsDefaultsWith(static function ($settings): void {
                $settings->setMetricsRequireToken(true);
            })),
        );
        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $urls);
        $controller->setContainer($container);

        $response = $controller(Request::create('/admin/ops', parameters: ['project' => 'not-a-uuid']));
        self::assertSame('ops', $response->getContent());
        self::assertIsArray($rendered);
        self::assertSame('admin/ops/overview.html.twig', $rendered[0]);
        self::assertArrayHasKey('overview', $rendered[1]);
        self::assertSame([$project], $rendered[1]['overview']['projects']);
        self::assertSame([], $rendered[1]['securityPostureItems']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Notifications\Controller\ProjectNotificationController;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Project\Entity\Project;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class ProjectNotificationControllerTest extends TestCase
{
    public function testAssertDestinationBelongsToProject(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(ProjectNotificationController::class, 'assertDestinationBelongsToProject');

        $project = (new Project())->setName('Acme')->setSlug('acme');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 5);
        $other = (new Project())->setName('Other')->setSlug('other');
        (new ReflectionProperty(Project::class, 'id'))->setValue($other, 6);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.com/hook');
        $destination->setLabel('Hook');
        $destination->setCategories(['error']);

        $method->invoke($controller, $project, $destination);

        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, $other, $destination);
    }

    public function testHelpRendersTemplate(): void
    {
        $controller = $this->controller();
        $project = (new Project())->setName('Acme')->setSlug('acme');
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use ($project): string {
            self::assertSame('notifications/help.html.twig', $name);
            self::assertSame($project, $context['project']);

            return 'help';
        });
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('help', $controller->help($project)->getContent());
    }

    private function controller(): ProjectNotificationController
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $settings = $this->createStub(InstanceSettingsRepository::class);
        $settings->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settings);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/i');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);

        return new ProjectNotificationController(
            new NotificationDispatcher(
                $destinations,
                $this->createStub(NotificationDigestBufferRepository::class),
                new NotificationPayloadBuilder($urls),
                new QuietHoursEvaluator(),
                new NotificationCircuitBreaker($ops),
                $this->createStub(MessageBusInterface::class),
                $em,
                $this->createStub(MemberIssueRealtimeNotifierInterface::class),
            ),
            $em,
        );
    }
}

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
use App\Notifications\Service\NotificationDestinationWriter;
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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

final class ProjectNotificationControllerTest extends TestCase
{
    public function testAssertDestinationBelongsToProject(): void
    {
        $controller = $this->controller();
        $method = new ReflectionMethod(ProjectNotificationController::class, 'assertDestinationBelongsToProject');

        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $other = new Project()->setName('Other')->setSlug('other');
        new ReflectionProperty(Project::class, 'id')->setValue($other, 6);

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
        $project = new Project()->setName('Acme')->setSlug('acme');
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

    public function testNewGetRendersDestinationForm(): void
    {
        $controller = $this->controller();
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $seen = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->new($project, Request::create('/new'))->getContent());
        self::assertFalse($seen['notifications/form.html.twig']['is_edit']);
        self::assertSame('Alerts', $seen['notifications/form.html.twig']['destination']->getLabel());
    }

    public function testToggleFlipsEnabledAndDeleteRejectsInvalidCsrf(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.com/hook');
        $destination->setLabel('Hook');
        $destination->setCategories(['error']);
        $destination->setEnabled(true);
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($destination, 9);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $valid = $this->createStub(FormInterface::class);
        $valid->method('submit');
        $valid->method('isSubmitted')->willReturn(true);
        $valid->method('isValid')->willReturn(true);

        $controller = $this->controller($em);
        $session = new Session(
            new MockArraySessionStorage(),
        );
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($valid);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/settings/alerts');
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        $response = $controller->toggle($project, $destination, Request::create('/toggle', Request::METHOD_POST));
        self::assertFalse($destination->isEnabled());
        self::assertSame(['notifications.flash.disabled'], $session->getFlashBag()->peek('success'));
        self::assertSame('/settings/alerts', $response->headers->get('Location'));

        $invalid = $this->createStub(FormInterface::class);
        $invalid->method('submit');
        $invalid->method('isSubmitted')->willReturn(false);
        $invalid->method('isValid')->willReturn(false);
        $formFactory2 = $this->createStub(FormFactoryInterface::class);
        $formFactory2->method('create')->willReturn($invalid);
        $container2 = new Container();
        $container2->set('form.factory', $formFactory2);
        $controller->setContainer($container2);

        $this->expectException(AccessDeniedException::class);
        $controller->delete($project, $destination, Request::create('/delete', Request::METHOD_POST));
    }

    public function testResumeClearsCircuitAndTestRequiresPersistedId(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.com/hook');
        $destination->setLabel('Hook');
        $destination->setCategories(['error']);
        $destination->openCircuit();
        new ReflectionProperty(NotificationDestination::class, 'id')->setValue($destination, 9);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $valid = $this->createStub(FormInterface::class);
        $valid->method('submit');
        $valid->method('isSubmitted')->willReturn(true);
        $valid->method('isValid')->willReturn(true);

        $controller = $this->controller($em);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($valid);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/settings/alerts');
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $controller->setContainer($container);

        $response = $controller->resume($project, $destination, Request::create('/resume', Request::METHOD_POST));
        self::assertSame('/settings/alerts', $response->headers->get('Location'));
        self::assertSame(['notifications.flash.circuit_resumed'], $session->getFlashBag()->peek('success'));
        self::assertNull($destination->getCircuitOpenedAt());

        $unsaved = new NotificationDestination();
        $unsaved->setProject($project);
        $unsaved->setType(NotificationDestinationType::Http);
        $unsaved->setEndpointUrl('https://example.com/hook');
        $unsaved->setLabel('Hook');
        $unsaved->setCategories(['error']);

        $this->expectException(NotFoundHttpException::class);
        $controller->test($project, $unsaved, Request::create('/test', Request::METHOD_POST));
    }

    private function controller(?EntityManagerInterface $em = null): ProjectNotificationController
    {
        $em ??= $this->createStub(EntityManagerInterface::class);
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
            new NotificationDestinationWriter($em),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Notifications\Controller\ProjectThresholdRuleController;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Service\ThresholdRuleWriter;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

final class ProjectThresholdRuleControllerTest extends TestCase
{
    public function testAssertRuleBelongsToProject(): void
    {
        $controller = $this->controller(new ThresholdRuleWriter($this->createStub(EntityManagerInterface::class)));
        $method = new ReflectionMethod(ProjectThresholdRuleController::class, 'assertRuleBelongsToProject');

        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $other = new Project()->setName('Other')->setSlug('other');
        new ReflectionProperty(Project::class, 'id')->setValue($other, 6);

        $rule = new ProjectThresholdRule();
        $rule->setProject($project);
        $method->invoke($controller, $project, $rule);

        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, $other, $rule);
    }

    public function testNewGetRedirectsToAlertsWithOpenFlag(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $form = $this->createStub(FormInterface::class);
        $controller = $this->controller(new ThresholdRuleWriter($this->createStub(EntityManagerInterface::class)));
        $seen = [];
        $this->boot($controller, $form, $seen);

        $response = $controller->new($project, Request::create('/new'));
        self::assertTrue($response->isRedirection());
        self::assertSame('/settings/alerts?new_threshold=1', $response->headers->get('Location'));
    }

    public function testToggleFlipsEnabledAndRedirects(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');

        $rule = new ProjectThresholdRule()->setProject($project)->setEnabled(true);
        new ReflectionProperty(ProjectThresholdRule::class, 'id')->setValue($rule, 9);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $controller = $this->controller(new ThresholdRuleWriter($em));
        $seen = [];
        $session = $this->boot($controller, $form, $seen, flash: true);

        $response = $controller->toggle($project, $rule, Request::create('/toggle', Request::METHOD_POST));
        self::assertTrue($response->isRedirection());
        self::assertFalse($rule->isEnabled());
        self::assertSame(['thresholds.flash.disabled'], $session->getFlashBag()->peek('success'));
    }

    public function testDeleteRejectsInvalidCsrf(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $rule = new ProjectThresholdRule()->setProject($project);
        new ReflectionProperty(ProjectThresholdRule::class, 'id')->setValue($rule, 9);

        $form = $this->createStub(FormInterface::class);
        $form->method('submit');
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);

        $controller = $this->controller(new ThresholdRuleWriter($this->createStub(EntityManagerInterface::class)));
        $seen = [];
        $this->boot($controller, $form, $seen);

        $this->expectException(AccessDeniedException::class);
        $controller->delete($project, $rule, Request::create('/delete', Request::METHOD_POST));
    }

    private function controller(ThresholdRuleWriter $writer): ProjectThresholdRuleController
    {
        $ref = new ReflectionClass(ProjectThresholdRuleController::class);
        /** @var ProjectThresholdRuleController $controller */
        $controller = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('thresholdRuleWriter')->setValue($controller, $writer);

        return $controller;
    }

    /**
     * @param FormInterface<mixed>                $form
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(object $controller, FormInterface $form, array &$seen, bool $flash = false): Session
    {
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static function (string $name, array $params = []): string {
                if ('project_settings_section' === $name) {
                    $query = [];
                    if (!empty($params['new_threshold'])) {
                        $query['new_threshold'] = $params['new_threshold'];
                    }

                    return '/settings/alerts'.([] === $query ? '' : '?'.http_build_query($query));
                }

                return '/settings/alerts';
            },
        );

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('router', $router);
        $container->set('twig', $twig);
        if ($flash) {
            $container->set('request_stack', $stack);
        }
        $controller->setContainer($container);

        return $session;
    }
}

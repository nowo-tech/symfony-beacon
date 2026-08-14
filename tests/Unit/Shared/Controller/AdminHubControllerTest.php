<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Controller\AdminHubController;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class AdminHubControllerTest extends TestCase
{
    public function testIndexRendersAdminHubWithTourVars(): void
    {
        $user = new User()->setEmail('admin@example.com');
        $settings = InstanceSettings::defaults();
        $settings->markSetupCompleted();
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $builder = new ProductTourStepsBuilder(
            $translator,
            $security,
            new ProjectAccessService(
                $this->createStub(ProjectMembershipRepository::class),
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            $settingsRepo,
        );

        $rendered = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context) use (&$rendered): string {
            $rendered = [$name, $context];

            return 'hub';
        });

        $controller = new AdminHubController($builder);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $response = $controller->index(Request::create('/admin'));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('hub', $response->getContent());
        self::assertIsArray($rendered);
        self::assertSame('admin/hub.html.twig', $rendered[0]);
        self::assertArrayHasKey('productTourPage', $rendered[1]);
        self::assertSame('admin', $rendered[1]['productTourPage']);
    }
}

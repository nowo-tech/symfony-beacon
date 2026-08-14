<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Controller\MemberRealtimeController;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Notifications\Service\WebPushEndpointGuard;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class MemberRealtimeControllerTest extends TestCase
{
    public function testConfigWhenMercureDisabled(): void
    {
        $user = new User()->setEmail('rt@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 2);
        $controller = $this->controller($user, mercureEnabled: false, csrfValid: true);

        $response = $controller->config(Request::create('/account/realtime/config'));
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertFalse($payload['mercure']['enabled']);
        self::assertNull($payload['mercure']['token']);
        self::assertSame([], $payload['mercure']['topics']);
        self::assertFalse($payload['push']['configured']);
    }

    public function testSubscribeRejectsInvalidCsrf(): void
    {
        $user = new User()->setEmail('rt@example.com');
        $user->setPushNotificationsEnabled(true);
        $controller = $this->controller($user, mercureEnabled: false, csrfValid: false);

        $response = $controller->subscribe(Request::create('/account/push/subscribe', Request::METHOD_POST, content: '{}'));
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testBrowserMercureHubUrlRewritesWellKnownPath(): void
    {
        $user = new User()->setEmail('rt@example.com');
        $controller = $this->controller($user, mercureEnabled: false, csrfValid: true);
        $method = new ReflectionMethod(MemberRealtimeController::class, 'browserMercureHubUrl');

        $request = Request::create('https://beacon.test/account/realtime/config');
        self::assertSame(
            'https://beacon.test/.well-known/mercure',
            $method->invoke($controller, $request, 'https://mercure.internal/.well-known/mercure'),
        );
        self::assertSame(
            'https://external.example/hub',
            $method->invoke($controller, $request, 'https://external.example/hub'),
        );
        self::assertNull($method->invoke($controller, $request, null));
    }

    private function controller(User $user, bool $mercureEnabled, bool $csrfValid): MemberRealtimeController
    {
        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled($mercureEnabled);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);
        $mercure = new ConfiguredMercure(
            $repo,
            'http://mercure/.well-known/mercure',
            'https://localhost/.well-known/mercure',
            '!ChangeThisMercureHubJWTSecretKey!',
            new MercureHubUrlGuard(),
        );

        $controller = new MemberRealtimeController(
            $mercure,
            new WebPushClientFactory('', '', 'mailto:ops@example.com'),
            new WebPushEndpointGuard(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $csrfValid && '' !== $token->getValue(),
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $csrf);
        $controller->setContainer($container);

        return $controller;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Controller\MemberRealtimeController;
use App\Notifications\Entity\PushSubscription;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Notifications\Service\WebPushEndpointGuard;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class MemberRealtimeControllerGuardsTest extends TestCase
{
    public function testConfigDisablesMercureWhenAlertsOff(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $user->setMemberAlertsEnabled(false);
        $user->setPushNotificationsEnabled(false);

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->boot($controller, $user);

        $payload = json_decode($controller->config(Request::create('/account/realtime/config'))->getContent(), true);
        self::assertFalse($payload['mercure']['enabled']);
        self::assertSame([], $payload['mercure']['topics']);
        self::assertNull($payload['push']['vapidPublicKey']);
        self::assertFalse($payload['push']['configured']);
    }

    public function testSubscribeRejectsInvalidCsrf(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $user->setPushNotificationsEnabled(true);

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->boot($controller, $user, csrfValid: false);

        $response = $controller->subscribe(Request::create('/account/push/subscribe', Request::METHOD_POST));
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'invalid_csrf'], json_decode($response->getContent(), true));
    }

    public function testSubscribeRejectsWhenPreferenceDisabled(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $user->setPushNotificationsEnabled(false);

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->boot($controller, $user, csrfValid: true);

        $response = $controller->subscribe(Request::create('/account/push/subscribe', Request::METHOD_POST));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'preference_disabled'], json_decode($response->getContent(), true));
    }

    public function testSubscribeRejectsWhenPushNotConfigured(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $user->setPushNotificationsEnabled(true);

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->boot($controller, $user, csrfValid: true);

        $response = $controller->subscribe(Request::create('/account/push/subscribe', Request::METHOD_POST));
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'push_not_configured'], json_decode($response->getContent(), true));
    }

    public function testSubscribeRejectsInvalidJson(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $user->setPushNotificationsEnabled(true);

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->configuredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $this->boot($controller, $user, csrfValid: true);

        $response = $controller->subscribe(Request::create(
            '/account/push/subscribe',
            Request::METHOD_POST,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{not-json',
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'invalid_json'], json_decode($response->getContent(), true));
    }

    private function disabledMercure(): ConfiguredMercure
    {
        $settings = new InstanceSettings()->setMercureEnabled(false);
        $repo = $this->createStub(InstanceSettingsRepository::class);
        $repo->method('getOrCreate')->willReturn($settings);

        $mercure = new ReflectionClass(ConfiguredMercure::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(ConfiguredMercure::class, 'settingsRepository')->setValue($mercure, $repo);
        new ReflectionProperty(ConfiguredMercure::class, 'envUrl')->setValue($mercure, '');
        new ReflectionProperty(ConfiguredMercure::class, 'envPublicUrl')->setValue($mercure, '');
        new ReflectionProperty(ConfiguredMercure::class, 'envJwtSecret')->setValue($mercure, '');

        return $mercure;
    }

    private function unconfiguredPush(): WebPushClientFactory
    {
        $push = new ReflectionClass(WebPushClientFactory::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(WebPushClientFactory::class, 'vapidPublicKey')->setValue($push, '');
        new ReflectionProperty(WebPushClientFactory::class, 'vapidPrivateKey')->setValue($push, '');
        new ReflectionProperty(WebPushClientFactory::class, 'vapidSubject')->setValue($push, '');

        return $push;
    }

    private function configuredPush(): WebPushClientFactory
    {
        $push = new ReflectionClass(WebPushClientFactory::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(WebPushClientFactory::class, 'vapidPublicKey')->setValue($push, 'public');
        new ReflectionProperty(WebPushClientFactory::class, 'vapidPrivateKey')->setValue($push, 'private');
        new ReflectionProperty(WebPushClientFactory::class, 'vapidSubject')->setValue($push, 'mailto:ops@example.com');

        return $push;
    }

    public function testUnsubscribeRejectsInvalidCsrfAndInvalidJson(): void
    {
        $user = new User()->setEmail('dev@example.com');

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $this->createStub(PushSubscriptionRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->boot($controller, $user, csrfValid: false);
        $forbidden = $controller->unsubscribe(Request::create('/account/push/unsubscribe', Request::METHOD_POST));
        self::assertSame(Response::HTTP_FORBIDDEN, $forbidden->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'invalid_csrf'], json_decode($forbidden->getContent(), true));

        $this->boot($controller, $user, csrfValid: true);
        $badJson = $controller->unsubscribe(Request::create(
            '/account/push/unsubscribe',
            Request::METHOD_POST,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{bad',
        ));
        self::assertSame(Response::HTTP_BAD_REQUEST, $badJson->getStatusCode());
        self::assertSame(['ok' => false, 'error' => 'invalid_json'], json_decode($badJson->getContent(), true));
    }

    public function testUnsubscribeClearsAllSubscriptionsWithoutEndpoint(): void
    {
        $user = new User()->setEmail('dev@example.com');
        $row = new ReflectionClass(PushSubscription::class)->newInstanceWithoutConstructor();

        $subscriptions = $this->createStub(PushSubscriptionRepository::class);
        $subscriptions->method('findByUser')->willReturn([$row]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($row);
        $em->expects(self::once())->method('flush');

        $controller = new MemberRealtimeController(
            $this->disabledMercure(),
            $this->unconfiguredPush(),
            new ReflectionClass(WebPushEndpointGuard::class)->newInstanceWithoutConstructor(),
            $subscriptions,
            $em,
        );
        $this->boot($controller, $user, csrfValid: true);

        $response = $controller->unsubscribe(Request::create(
            '/account/push/unsubscribe',
            Request::METHOD_POST,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{}',
        ));
        self::assertSame(['ok' => true], json_decode($response->getContent(), true));
    }

    private function boot(MemberRealtimeController $controller, User $user, bool $csrfValid = false): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $csrfValid && 'account_push' === $token->getId(),
        );

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $csrf);
        $controller->setContainer($container);
    }
}

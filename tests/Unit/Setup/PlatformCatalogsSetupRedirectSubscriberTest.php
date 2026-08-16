<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\PlatformCatalogsSetupRedirectSubscriber;
use App\Shared\Settings\Service\PlatformBootstrapState;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\SiteBackupBundle\Routing\SetupPathPrefixResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PlatformCatalogsSetupRedirectSubscriberTest extends TestCase
{
    public function testSubscribedEventsAndDisabledSkip(): void
    {
        self::assertArrayHasKey(KernelEvents::REQUEST, PlatformCatalogsSetupRedirectSubscriber::getSubscribedEvents());

        $subscriber = $this->subscriber(enabled: false);
        $event = $this->event('/dashboard');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testRedirectsGuestHtmlWhenCatalogsMissing(): void
    {
        $subscriber = $this->subscriber();
        $event = $this->event('/dashboard');
        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame('/setup', $event->getResponse()->headers->get('Location'));
    }

    public function testSkipsExcludedPathsNonHtmlAndAuthenticatedNonAdmin(): void
    {
        $subscriber = $this->subscriber();
        $api = $this->event('/api/projects/x');
        $subscriber->onKernelRequest($api);
        self::assertNull($api->getResponse());

        $json = $this->event('/dashboard', accept: 'application/json');
        $subscriber->onKernelRequest($json);
        self::assertNull($json->getResponse());

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (string $a): bool => 'IS_AUTHENTICATED_REMEMBERED' === $a,
        );
        $subscriber = $this->subscriber(auth: $auth);
        $member = $this->event('/dashboard');
        $subscriber->onKernelRequest($member);
        self::assertNull($member->getResponse());
    }

    public function testSkipsExcludedAuthKitRoute(): void
    {
        $subscriber = $this->subscriber();
        $event = $this->event('/account/profile', route: 'nowo_auth_kit_account');
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testSwallowsCatalogStateFailures(): void
    {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willThrowException(new RuntimeException('db offline'));
        $state = new PlatformBootstrapState(
            $menus,
            $this->createStub(BreadcrumbCollectionRepository::class),
            $this->createStub(CookieConsentConfigRepository::class),
        );

        $subscriber = new PlatformCatalogsSetupRedirectSubscriber(
            $state,
            $this->createStub(AuthorizationCheckerInterface::class),
            new SetupPathPrefixResolver(new RequestStack(), '/setup', 'never', 'en', ['en']),
            '/setup',
            true,
        );
        $event = $this->event('/dashboard');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function subscriber(
        bool $enabled = true,
        ?AuthorizationCheckerInterface $auth = null,
    ): PlatformCatalogsSetupRedirectSubscriber {
        $menus = $this->createStub(MenuRepository::class);
        $menus->method('findOneByCodeAndContext')->willReturn(null);
        $state = new PlatformBootstrapState(
            $menus,
            $this->createStub(BreadcrumbCollectionRepository::class),
            $this->createStub(CookieConsentConfigRepository::class),
        );

        if (null === $auth) {
            $auth = $this->createStub(AuthorizationCheckerInterface::class);
            $auth->method('isGranted')->willReturn(false);
        }

        return new PlatformCatalogsSetupRedirectSubscriber(
            $state,
            $auth,
            new SetupPathPrefixResolver(new RequestStack(), '/setup', 'never', 'en', ['en']),
            '/setup',
            $enabled,
        );
    }

    private function event(string $path, string $accept = 'text/html', ?string $route = null): RequestEvent
    {
        $request = Request::create($path);
        $request->headers->set('Accept', $accept);
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}

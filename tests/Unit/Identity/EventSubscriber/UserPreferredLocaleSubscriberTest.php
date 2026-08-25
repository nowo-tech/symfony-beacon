<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\EventSubscriber;

use App\Identity\Entity\User;
use App\Identity\EventSubscriber\UserPreferredLocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserPreferredLocaleSubscriberTest extends TestCase
{
    public function testSubscribesAfterFirewall(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => [['onKernelRequest', 7]]],
            UserPreferredLocaleSubscriber::getSubscribedEvents(),
        );
    }

    public function testSkipsGuests(): void
    {
        $translator = new RecordingTranslator();
        $request = Request::create('/dashboard');
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber(new TokenStorage(), $translator, 'en')
            ->onKernelRequest($event);

        self::assertSame('en', $translator->locale);
        self::assertNull($event->getResponse());
    }

    public function testSkipsAuthKitRoutes(): void
    {
        $user = $this->userWithLocale('fr');
        $translator = new RecordingTranslator();
        $request = Request::create('/login');
        $request->attributes->set('_route', 'nowo_auth_kit_login');
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber($this->tokens($user), $translator, 'en')
            ->onKernelRequest($event);

        self::assertSame('en', $translator->locale);
    }

    public function testRedirectsWhenLocaleQueryPresent(): void
    {
        $user = $this->userWithLocale('fr');
        $translator = new RecordingTranslator();
        $request = Request::create('/projects', Request::METHOD_GET, ['_locale' => 'es', 'page' => '2']);
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber($this->tokens($user), $translator, 'en')
            ->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertTrue($response->isRedirection());
        self::assertSame('/projects?page=2', $response->headers->get('Location'));
    }

    public function testAppliesPreferredLocaleAndSession(): void
    {
        $user = $this->userWithLocale('fr');
        $translator = new RecordingTranslator();
        $request = Request::create('/projects');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber($this->tokens($user), $translator, 'en')
            ->onKernelRequest($event);

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $session->get('_locale'));
        self::assertSame('fr', $translator->locale);
    }

    public function testFallsBackToDefaultLocaleWhenPreferenceUnset(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $translator = new RecordingTranslator();
        $request = Request::create('/projects');
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber($this->tokens($user), $translator, 'de')
            ->onKernelRequest($event);

        self::assertSame('de', $request->getLocale());
        self::assertSame('de', $translator->locale);
    }

    public function testSkipsWhenPreferenceAndDefaultLocaleAreBlank(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $translator = new RecordingTranslator();
        $request = Request::create('/projects');
        $event = $this->mainEvent($request);

        new UserPreferredLocaleSubscriber($this->tokens($user), $translator, '   ')
            ->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $translator->locale);
        self::assertNull($event->getResponse());
    }

    private function userWithLocale(string $locale): User
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPreferredLocale($locale);

        return $user;
    }

    private function tokens(User $user): TokenStorage
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));

        return $storage;
    }

    private function mainEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\EventSubscriber;

use App\Identity\Entity\User;
use App\Identity\EventSubscriber\GuestSessionLocaleSubscriber;
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
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GuestSessionLocaleSubscriberTest extends TestCase
{
    public function testSubscribesAfterLocaleListeners(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => [['onKernelRequest', 8]]],
            GuestSessionLocaleSubscriber::getSubscribedEvents(),
        );
    }

    public function testSkipsAuthenticatedBeaconUsers(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));

        $translator = new RecordingTranslator();
        $request = $this->requestWithSession('fr');
        $event = $this->mainEvent($request);

        (new GuestSessionLocaleSubscriber(
            $tokenStorage,
            $translator,
            ['en', 'fr'],
            'en',
        ))->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $translator->locale);
    }

    public function testSkipsWhenPathLocalePresent(): void
    {
        $translator = new RecordingTranslator();
        $request = $this->requestWithSession('fr');
        $request->attributes->set('_locale', 'es');
        $event = $this->mainEvent($request);

        (new GuestSessionLocaleSubscriber(
            new TokenStorage(),
            $translator,
            ['en', 'fr', 'es'],
            'en',
        ))->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $translator->locale);
    }

    public function testAppliesSessionLocaleForGuests(): void
    {
        $translator = new RecordingTranslator();
        $request = $this->requestWithSession('fr');
        $event = $this->mainEvent($request);

        (new GuestSessionLocaleSubscriber(
            new TokenStorage(),
            $translator,
            ['en', 'fr'],
            'en',
        ))->onKernelRequest($event);

        self::assertSame('fr', $request->getLocale());
        self::assertSame('fr', $translator->locale);
    }

    public function testFallsBackToDefaultWhenSessionLocaleDisabled(): void
    {
        $translator = new RecordingTranslator();
        $request = $this->requestWithSession('xx');
        $event = $this->mainEvent($request);

        (new GuestSessionLocaleSubscriber(
            new TokenStorage(),
            $translator,
            ['en', 'fr'],
            'en',
        ))->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
        self::assertSame('en', $translator->locale);
    }

    private function requestWithSession(string $sessionLocale): Request
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->set('_locale', $sessionLocale);
        $request->setSession($session);

        return $request;
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

/**
 * @internal
 */
final class RecordingTranslator implements TranslatorInterface, LocaleAwareInterface
{
    public string $locale = 'en';

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}

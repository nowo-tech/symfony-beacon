<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\AuthKit;

use App\Identity\AuthKit\MailerGatedAuthKitRouteSubscriber;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MailerGatedAuthKitRouteSubscriberTest extends TestCase
{
    public function testSubscribesToRequestAtPrioritySix(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 6]],
            MailerGatedAuthKitRouteSubscriber::getSubscribedEvents(),
        );
    }

    public function testIgnoresSubRequests(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::never())->method('generate');

        $event = $this->event(
            'nowo_auth_kit_magic_login_request',
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->subscriber(mailerAvailable: false, urls: $urls)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresUngatedRoutes(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::never())->method('generate');

        $event = $this->event('nowo_auth_kit_login');
        $this->subscriber(mailerAvailable: false, urls: $urls)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsGatedRouteWhenMailerAvailable(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::never())->method('generate');

        $event = $this->event('nowo_auth_kit_magic_login_request');
        $this->subscriber(mailerAvailable: true, urls: $urls)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsGatedRouteWhenMailerUnavailable(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static function (string $name, array $params): string {
                self::assertSame('nowo_auth_kit_login', $name);
                self::assertSame('es', $params['_locale']);

                return '/es/login';
            },
        );

        $event = $this->event('nowo_auth_kit_reset_password_request_unlocalized', locale: 'es');
        $this->subscriber(mailerAvailable: false, urls: $urls)->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertTrue($response->isRedirection());
        self::assertSame('/es/login', $response->headers->get('Location'));
    }

    public function testUsesDefaultLocaleWhenPathLocaleMissing(): void
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static function (string $name, array $params): string {
                self::assertSame('en', $params['_locale']);

                return '/login';
            },
        );

        $event = $this->event('nowo_auth_kit_reset_password');
        $this->subscriber(mailerAvailable: false, urls: $urls, defaultLocale: 'en')->onKernelRequest($event);

        self::assertTrue($event->getResponse()->isRedirection());
    }

    private function subscriber(
        bool $mailerAvailable,
        UrlGeneratorInterface $urls,
        string $defaultLocale = 'en',
    ): MailerGatedAuthKitRouteSubscriber {
        return new MailerGatedAuthKitRouteSubscriber(
            $this->mailer($mailerAvailable),
            $urls,
            $defaultLocale,
        );
    }

    private function mailer(bool $available): ConfiguredMailer
    {
        $settings = InstanceSettings::defaults();
        if ($available) {
            $settings->setMailerDsn('smtp://user:pass@127.0.0.1:2525');
        }

        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new ConfiguredMailer($repository, new MailerDsnValidator(), 'null://null', 'test');
    }

    private function event(
        string $route,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
        ?string $locale = null,
    ): RequestEvent {
        $request = Request::create('/auth');
        $request->attributes->set('_route', $route);
        if (null !== $locale) {
            $request->attributes->set('_locale', $locale);
        }

        return new RequestEvent(
            $this->createStub(KernelInterface::class),
            $request,
            $requestType,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use Symfony\Component\HttpFoundation\Cookie;
use App\Shared\Http\PwaStatelessCookieSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PwaStatelessCookieSubscriberTest extends TestCase
{
    public function testRemovesSetCookieOnManifest(): void
    {
        $response = new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/manifest+json']);
        $response->headers->setCookie(new Cookie('SYMFONY_BEACON_SESSID', 'guest'));

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/manifest.webmanifest'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        new PwaStatelessCookieSubscriber()->onKernelResponse($event);

        self::assertSame([], $response->headers->getCookies());
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    public function testLeavesSetCookieOnNormalPages(): void
    {
        $response = new Response('ok');
        $response->headers->setCookie(new Cookie('SYMFONY_BEACON_SESSID', 'auth'));

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/dashboard'),
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        new PwaStatelessCookieSubscriber()->onKernelResponse($event);

        self::assertNotSame([], $response->headers->getCookies());
    }
}

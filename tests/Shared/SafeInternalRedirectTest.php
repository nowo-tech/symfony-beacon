<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Http\SafeInternalRedirect;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SafeInternalRedirectTest extends TestCase
{
    public function testAllowsRelativePath(): void
    {
        $request = Request::create('https://beacon.example/dashboard');
        self::assertSame('/account', SafeInternalRedirect::resolve($request, '/account', '/fallback'));
    }

    public function testRejectsProtocolRelative(): void
    {
        $request = Request::create('https://beacon.example/dashboard');
        self::assertSame('/fallback', SafeInternalRedirect::resolve($request, '//evil.example/phish', '/fallback'));
    }

    public function testRejectsBackslashHost(): void
    {
        $request = Request::create('https://beacon.example/dashboard');
        self::assertSame('/fallback', SafeInternalRedirect::resolve($request, '/\\evil.example', '/fallback'));
        self::assertSame('/fallback', SafeInternalRedirect::resolve($request, '/%5cevil.example', '/fallback'));
    }

    public function testReducesSameHostAbsoluteToPath(): void
    {
        $request = Request::create('https://beacon.example/dashboard');
        self::assertSame('/legal/privacy', SafeInternalRedirect::resolve(
            $request,
            'https://beacon.example/legal/privacy',
            '/fallback',
        ));
    }

    public function testRejectsExternalAbsolute(): void
    {
        $request = Request::create('https://beacon.example/dashboard');
        self::assertSame('/fallback', SafeInternalRedirect::resolve(
            $request,
            'https://evil.example/phish',
            '/fallback',
        ));
    }
}

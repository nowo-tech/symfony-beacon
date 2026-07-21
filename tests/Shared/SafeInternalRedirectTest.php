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
        $request = Request::create('https://beacon.example/admin');
        self::assertSame(
            '/admin/projects',
            SafeInternalRedirect::resolve($request, '/admin/projects', '/fallback'),
        );
    }

    public function testRejectsProtocolRelativeOpenRedirect(): void
    {
        $request = Request::create('https://beacon.example/admin');
        self::assertSame(
            '/fallback',
            SafeInternalRedirect::resolve($request, '//evil.example/phish', '/fallback'),
        );
    }

    public function testAllowsSameHostAbsoluteUrl(): void
    {
        $request = Request::create('https://beacon.example/admin');
        self::assertSame(
            'https://beacon.example/admin/projects',
            SafeInternalRedirect::resolve($request, 'https://beacon.example/admin/projects', '/fallback'),
        );
    }

    public function testRejectsExternalAbsoluteUrl(): void
    {
        $request = Request::create('https://beacon.example/admin');
        self::assertSame(
            '/fallback',
            SafeInternalRedirect::resolve($request, 'https://evil.example/', '/fallback'),
        );
    }
}

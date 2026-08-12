<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Twig;

use App\Shared\Http\ContentSecurityPolicySubscriber;
use App\Shared\Twig\CspNonceTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CspNonceTwigExtensionTest extends TestCase
{
    public function testNonceReturnsEmptyWithoutRequest(): void
    {
        $ext = new CspNonceTwigExtension(new RequestStack());
        self::assertSame('', $ext->nonce());
    }

    public function testNonceReadsRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set(ContentSecurityPolicySubscriber::REQUEST_ATTR_NONCE, 'abc123');
        $stack = new RequestStack();
        $stack->push($request);

        $ext = new CspNonceTwigExtension($stack);
        self::assertSame('abc123', $ext->nonce());
    }
}

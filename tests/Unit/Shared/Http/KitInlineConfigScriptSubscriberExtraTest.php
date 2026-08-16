<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\KitInlineConfigScriptSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KitInlineConfigScriptSubscriberExtraTest extends TestCase
{
    public function testDecodeJsValueCoversBoolNumericAndFallbackBranches(): void
    {
        $method = new ReflectionMethod(KitInlineConfigScriptSubscriber::class, 'decodeJsValue');

        self::assertTrue($method->invoke(null, 'true'));
        self::assertFalse($method->invoke(null, 'false'));
        self::assertSame(42, $method->invoke(null, '42'));
        self::assertSame(3.14, $method->invoke(null, '3.14'));
        self::assertSame('plain-value', $method->invoke(null, 'plain-value'));
    }

    public function testRewriteBreadcrumbKitLeavesOriginalScriptWhenJsonEncodingFails(): void
    {
        $subscriber = new KitInlineConfigScriptSubscriber();
        $html = "<html><body><script>\n"
            ."window.__breadcrumbKitDashboard = window.__breadcrumbKitDashboard || {};\n"
            ."window.__breadcrumbKitDashboard.dashboardBase = '\xB1';\n"
            ."</script></body></html>";

        $method = new ReflectionMethod(KitInlineConfigScriptSubscriber::class, 'rewriteBreadcrumbKit');
        self::assertSame($html, $method->invoke($subscriber, $html));
    }
}

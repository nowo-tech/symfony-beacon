<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Http;

use App\Shared\Http\KitInlineConfigScriptSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class KitInlineConfigScriptSubscriberTest extends TestCase
{
    public function testRewritesDashboardMenuConfigScript(): void
    {
        $html = <<<'HTML'
<html><body>
<script>
window.__nowoDashboardMenuConfig = Object.assign(window.__nowoDashboardMenuConfig || {}, {
        dashboardBase: "/menus",
        menuId: 3,
        debug: true
    });
</script>
</body></html>
HTML;

        $response = $this->dispatch($html);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('window.__nowoDashboardMenuConfig = Object.assign', $content);
        self::assertStringContainsString('class="beacon-kit-page-config" data-kit="dashboard-menu"', $content);
        self::assertStringContainsString('"dashboardBase":"/menus"', $content);
        self::assertStringContainsString('"menuId":3', $content);
        self::assertStringContainsString('"debug":true', $content);
    }

    public function testRewritesBreadcrumbPageFlag(): void
    {
        $html = <<<'HTML'
<html><body>
<script>
window.__breadcrumbKitDashboard = Object.assign({}, window.__breadcrumbKitDashboard || {}, { page: 'items_index' });
</script>
</body></html>
HTML;

        $content = (string) $this->dispatch($html)->getContent();
        self::assertStringContainsString('data-kit="breadcrumb-kit"', $content);
        self::assertStringContainsString('"page":"items_index"', $content);
        self::assertStringNotContainsString('window.__breadcrumbKitDashboard = Object.assign', $content);
    }

    public function testStripsBreadcrumbInlineEditIife(): void
    {
        $html = <<<'HTML'
<html><body>
<script>
    (function () {
      var wrap = document.querySelector('[data-breadcrumb-kit-inline-wrap="1"]');
      if (!wrap) return;
      var openBtn = wrap.querySelector('[data-bk-inline-open]');
      var dlg = wrap.querySelector('[data-bk-inline-dialog]');
      var closeBtn = wrap.querySelector('[data-bk-inline-close]');
      if (!openBtn || !dlg) return;
      openBtn.addEventListener('click', function () { if (dlg.showModal) dlg.showModal(); });
      closeBtn && closeBtn.addEventListener('click', function () { dlg.close(); });
    })();
  </script>
</body></html>
HTML;

        $content = (string) $this->dispatch($html)->getContent();
        self::assertStringNotContainsString('data-breadcrumb-kit-inline-wrap', $content);
        self::assertStringNotContainsString('<script>', $content);
    }

    #[DataProvider('untouchedProvider')]
    public function testLeavesUnrelatedHtmlAlone(string $html): void
    {
        self::assertSame($html, $this->dispatch($html)->getContent());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function untouchedProvider(): iterable
    {
        yield 'no kit markers' => ['<html><body><script src="/app.js"></script></body></html>'];
        yield 'json island already' => ['<html><body><script type="application/json" id="x">{}</script></body></html>'];
    }

    private function dispatch(string $html): Response
    {
        $kernel = $this->createStub(KernelInterface::class);
        $request = Request::create('/');
        $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        (new KitInlineConfigScriptSubscriber())($event);

        return $response;
    }
}

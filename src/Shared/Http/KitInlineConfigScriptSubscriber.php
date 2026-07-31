<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rewrites kit dashboard inline config scripts into JSON data islands (CSP-safe).
 *
 * Vendor Dashboard Menu / Breadcrumb Kit pages still emit:
 *   <script>window.__nowoDashboardMenuConfig = Object.assign(..., {…});</script>
 * Beacon kit-admin.ts merges the JSON islands before dashboard.js runs.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -50)]
final class KitInlineConfigScriptSubscriber
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || '' === $content) {
            return;
        }

        $needsRewrite = str_contains($content, '__nowoDashboardMenuConfig')
            || str_contains($content, '__breadcrumbKitDashboard')
            || str_contains($content, 'data-breadcrumb-kit-inline-wrap');
        if (!$needsRewrite) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ('' !== $contentType && !str_contains($contentType, 'text/html')) {
            return;
        }

        $rewritten = $this->rewriteDashboardMenu($content);
        $rewritten = $this->rewriteBreadcrumbKit($rewritten);
        $rewritten = $this->stripBreadcrumbInlineEditScript($rewritten);

        if ($rewritten !== $content) {
            $response->setContent($rewritten);
        }
    }

    /**
     * Vendor breadcrumb inline-edit uses an IIFE; app.ts boots the same dialog via data-* hooks.
     */
    private function stripBreadcrumbInlineEditScript(string $html): string
    {
        return (string) preg_replace(
            '#<script>\s*\(function\s*\(\)\s*\{[^<]*data-breadcrumb-kit-inline-wrap[^<]*\}\)\(\);\s*</script>#s',
            '',
            $html,
        );
    }

    private function rewriteDashboardMenu(string $html): string
    {
        return (string) preg_replace_callback(
            '#<script>\s*window\.__nowoDashboardMenuConfig\s*=\s*Object\.assign\(\s*window\.__nowoDashboardMenuConfig\s*\|\|\s*\{\}\s*,\s*(\{.*?\})\s*\);\s*</script>#s',
            static function (array $matches): string {
                $json = self::normalizeObjectLiteral($matches[1]);

                return '<script type="application/json" class="beacon-kit-page-config" data-kit="dashboard-menu">'.$json.'</script>';
            },
            $html,
        );
    }

    private function rewriteBreadcrumbKit(string $html): string
    {
        // Page flags: { page: 'items_index' }
        $html = (string) preg_replace_callback(
            '#<script>\s*window\.__breadcrumbKitDashboard\s*=\s*Object\.assign\(\s*\{\}\s*,\s*window\.__breadcrumbKitDashboard\s*\|\|\s*\{\}\s*,\s*(\{.*?\})\s*\);\s*</script>#s',
            static function (array $matches): string {
                $json = self::normalizeObjectLiteral($matches[1]);

                return '<script type="application/json" class="beacon-kit-page-config" data-kit="breadcrumb-kit">'.$json.'</script>';
            },
            $html,
        );

        // Layout-style property assignments inside a single script block (vendor layout only).
        return (string) preg_replace_callback(
            '#<script>\s*window\.__breadcrumbKitDashboard\s*=\s*window\.__breadcrumbKitDashboard\s*\|\|\s*\{\};(.*?</script>)#s',
            static function (array $matches): string {
                $body = $matches[1];
                $config = [];
                if (preg_match('/cssFramework\s*=\s*(.+?);/s', $body, $m)) {
                    $config['cssFramework'] = self::decodeJsValue(trim($m[1]));
                }
                if (preg_match('/importPartialUrl\s*=\s*(.+?);/s', $body, $m)) {
                    $config['importPartialUrl'] = self::decodeJsValue(trim($m[1]));
                }
                if (preg_match('/dashboardBase\s*=\s*(.+?);/s', $body, $m)) {
                    $config['dashboardBase'] = self::decodeJsValue(trim($m[1]));
                }
                $json = json_encode($config, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);
                if (false === $json) {
                    return $matches[0];
                }

                return '<script type="application/json" class="beacon-kit-page-config" data-kit="breadcrumb-kit">'.$json.'</script>';
            },
            $html,
        );
    }

    /**
     * Vendor templates use json_encode for strings but raw true/false for booleans
     * and sometimes single-quoted JS strings for page flags.
     *
     * @return array<string, mixed>|string|bool|int|float|null
     */
    private static function decodeJsValue(string $value): mixed
    {
        if ('true' === $value) {
            return true;
        }
        if ('false' === $value) {
            return false;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        $decoded = json_decode($value, true);
        if (\JSON_ERROR_NONE === json_last_error()) {
            return $decoded;
        }

        if (preg_match("/^'(.*)'$/s", $value, $m)) {
            return stripcslashes($m[1]);
        }

        return $value;
    }

    private static function normalizeObjectLiteral(string $literal): string
    {
        // Single-quoted JS strings → JSON double-quoted.
        $jsonish = preg_replace_callback(
            "/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'/",
            static fn (array $m): string => json_encode(stripcslashes($m[1]), \JSON_UNESCAPED_UNICODE) ?: '""',
            $literal,
        ) ?? $literal;

        // Unquoted keys → quoted keys.
        $jsonish = preg_replace('/([{\s,])([a-zA-Z_]\w*)\s*:/', '$1"$2":', $jsonish) ?? $jsonish;
        $decoded = json_decode($jsonish, true);
        if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($decoded)) {
            return '{}';
        }

        $json = json_encode($decoded, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);

        return false === $json ? '{}' : $json;
    }
}

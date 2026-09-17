<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Tests\Support\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Environment;

/**
 * Error preview HTTP routes (/_error/{code}) exist only in APP_ENV=dev.
 * Tests render the Twig overrides directly so CI (test env) still covers them.
 */
final class HttpErrorPagesTest extends DatabaseWebTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: int, 2: string}>
     */
    public static function errorTemplateProvider(): iterable
    {
        foreach ([400, 401, 403, 404, 408, 429, 500, 502, 503] as $code) {
            yield (string) $code => [
                sprintf('@Twig/Exception/error%d.html.twig', $code),
                $code,
                sprintf('illustrations/error-%d.png', $code),
            ];
        }
    }

    #[DataProvider('errorTemplateProvider')]
    public function testErrorTemplatesIncludeIllustration(string $template, int $statusCode, string $illustration): void
    {
        self::createClient();
        $html = self::getContainer()->get(Environment::class)->render($template, [
            'status_code' => $statusCode,
            'status_text' => 'Test',
        ]);

        self::assertStringContainsString('error-page', $html);
        self::assertStringContainsString($illustration, $html);
        self::assertStringContainsString('error-page__hint', $html);
    }

    public function testMascotAndErrorAssetsArePublished(): void
    {
        $root = \dirname(__DIR__, 3);
        self::assertRuntimePngHasTransparentCanvas($root.'/public/brand/mascot.png');
        self::assertFileExists($root.'/public/brand/beacon-mark.png');
        foreach ([400, 401, 403, 404, 408, 429, 500, 502, 503] as $code) {
            self::assertRuntimePngHasTransparentCanvas($root.'/public/illustrations/error-'.$code.'.png');
        }
    }

    /**
     * REQ-ERROR-001 item 9: real PNG (not JPEG named .png), IHDR color type 6, punched canvas.
     */
    private static function assertRuntimePngHasTransparentCanvas(string $path): void
    {
        self::assertFileExists($path);
        $bytes = file_get_contents($path);
        self::assertNotFalse($bytes);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes, $path.' must be a PNG (JPEG/JFIF payloads named .png fail REQ-ERROR-001 item 9)');
        self::assertSame(6, ord($bytes[25]), $path.' must be PNG color type 6 (RGBA)');
        self::assertTrue(
            self::pngFirstRowHasFullyTransparentPixel($bytes),
            $path.' must have a transparent canvas (alpha=0 on the first scanline)',
        );
    }

    private static function pngFirstRowHasFullyTransparentPixel(string $png): bool
    {
        $width = unpack('N', substr($png, 16, 4));
        if (!is_array($width) || !isset($width[1]) || $width[1] < 1) {
            return false;
        }
        $idat = '';
        $offset = 8;
        $length = strlen($png);
        while ($offset + 8 <= $length) {
            $chunkLenParts = unpack('N', substr($png, $offset, 4));
            if (!is_array($chunkLenParts) || !isset($chunkLenParts[1])) {
                return false;
            }
            $chunkLen = $chunkLenParts[1];
            $type = substr($png, $offset + 4, 4);
            $idat .= $type === 'IDAT' ? substr($png, $offset + 8, $chunkLen) : '';
            if ($type === 'IEND') {
                break;
            }
            $offset += 12 + $chunkLen;
        }
        $raw = zlib_decode($idat);
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $bpp = 4;
        $stride = $width[1] * $bpp;
        if (strlen($raw) < 1 + $stride) {
            return false;
        }
        $row = substr($raw, 1, $stride);
        $filter = ord($raw[0]);
        $out = [];
        for ($i = 0; $i < $stride; ++$i) {
            $x = ord($row[$i]);
            $left = $i >= $bpp ? $out[$i - $bpp] : 0;
            $out[$i] = match ($filter) {
                0, 2 => $x,
                1 => ($x + $left) & 255,
                3 => ($x + intdiv($left, 2)) & 255,
                4 => ($x + $left) & 255,
                default => $x,
            };
        }
        for ($i = 3; $i < $stride; $i += $bpp) {
            if ($out[$i] === 0) {
                return true;
            }
        }

        return false;
    }
}

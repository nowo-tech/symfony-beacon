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
        yield '404' => ['@Twig/Exception/error404.html.twig', 404, 'illustrations/error-404.png'];
        yield '403' => ['@Twig/Exception/error403.html.twig', 403, 'illustrations/error-403.png'];
        yield '500' => ['@Twig/Exception/error500.html.twig', 500, 'illustrations/error-500.png'];
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
        self::assertFileExists($root.'/public/brand/mascot.png');
        self::assertFileExists($root.'/public/brand/beacon-mark.png');
        self::assertFileExists($root.'/public/illustrations/error-404.png');
        self::assertFileExists($root.'/public/illustrations/error-403.png');
        self::assertFileExists($root.'/public/illustrations/error-500.png');
    }
}

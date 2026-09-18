<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Legal\Controller;

use App\Shared\Legal\Controller\LegalController;
use App\Shared\Legal\Entity\LegalDocument;
use App\Shared\Legal\Repository\LegalDocumentRepository;
use App\Shared\Legal\Service\LegalPublishedHtml;
use Nowo\Ckeditor5EditorBundle\Security\Ckeditor5HtmlSanitizerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class LegalControllerTest extends TestCase
{
    public function testRendersBuiltinSeedUnlessALocaleHasStoredHtml(): void
    {
        $stored = new LegalDocument('notice', 'en');
        $stored->setTitle('Custom notice');
        $stored->setBody('<p>Custom</p><script>alert(1)</script><img src=x onerror=alert(2)>');

        $blank = new LegalDocument('privacy', 'en');
        $blank->setBody('   ');

        $documents = $this->createStub(LegalDocumentRepository::class);
        $documents->method('findOneBySlugAndLocale')->willReturnCallback(
            static fn (string $slug): ?LegalDocument => match ($slug) {
                'notice' => $stored,
                'privacy' => $blank,
                default => null,
            },
        );

        $sanitizer = $this->createStub(Ckeditor5HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturn('<p>Custom</p>');

        $rendered = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context = []) use (&$rendered): string {
            $rendered[] = [$name, $context];

            return 'ok:'.$name;
        });

        $controller = new LegalController($documents, new LegalPublishedHtml($sanitizer));
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $request = Request::create('/en/legal/notice');
        $request->setLocale('en');

        self::assertSame('ok:legal/stored.html.twig', $controller->notice($request)->getContent());
        self::assertSame('ok:legal/privacy.html.twig', $controller->privacy($request)->getContent());
        self::assertSame('ok:legal/terms.html.twig', $controller->terms($request)->getContent());
        self::assertSame('ok:legal/cookies.html.twig', $controller->cookies($request)->getContent());

        self::assertSame('legal/stored.html.twig', $rendered[0][0]);
        self::assertSame('Custom notice', $rendered[0][1]['title']);
        self::assertSame('notice', $stored->getSlug());
        self::assertSame('en', $stored->getLocale());
        self::assertSame('<p>Custom</p>', $rendered[0][1]['body']);
        self::assertSame('notice', $rendered[0][1]['current']);
        self::assertSame([
            'legal/privacy.html.twig',
            'legal/terms.html.twig',
            'legal/cookies.html.twig',
        ], array_column(\array_slice($rendered, 1), 0));
    }
}

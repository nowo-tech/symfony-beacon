<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Legal\Controller;

use App\Shared\Legal\Controller\LegalController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Twig\Environment;

final class LegalControllerTest extends TestCase
{
    public function testRendersAllLegalPages(): void
    {
        $rendered = [];
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name) use (&$rendered): string {
            $rendered[] = $name;

            return 'ok:'.$name;
        });

        $controller = new LegalController();
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok:legal/notice.html.twig', $controller->notice()->getContent());
        self::assertSame('ok:legal/privacy.html.twig', $controller->privacy()->getContent());
        self::assertSame('ok:legal/terms.html.twig', $controller->terms()->getContent());
        self::assertSame('ok:legal/cookies.html.twig', $controller->cookies()->getContent());
        self::assertSame([
            'legal/notice.html.twig',
            'legal/privacy.html.twig',
            'legal/terms.html.twig',
            'legal/cookies.html.twig',
        ], $rendered);
    }
}

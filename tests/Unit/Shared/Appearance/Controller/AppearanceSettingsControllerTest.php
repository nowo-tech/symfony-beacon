<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Appearance\Controller;

use App\Shared\Appearance\AppearanceSettingsSection;
use App\Shared\Appearance\Controller\AppearanceSettingsController;
use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppearanceSettingsControllerTest extends TestCase
{
    public function testIndexRedirectsToThemesSection(): void
    {
        $repo = $this->createStub(SiteAppearanceRepository::class);
        $repo->method('getOrCreate')->willReturn(SiteAppearance::defaults());
        $controller = new AppearanceSettingsController(
            $repo,
            new SiteAppearanceProvider($repo),
        );
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name, array $params = []): string {
            self::assertSame('admin_appearance_section', $name);
            self::assertSame(AppearanceSettingsSection::Themes->value, $params['section']);

            return '/admin/appearance/themes';
        });
        $container = new Container();
        $container->set('router', $urls);
        $controller->setContainer($container);

        $response = $controller->index();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/appearance/themes', $response->getTargetUrl());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Shared\Appearance\Entity\SiteAppearance;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Appearance\SiteAppearanceProvider;
use App\Shared\Settings\Controller\InstanceConfigController;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceConfigPortability;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

final class InstanceConfigControllerTest extends TestCase
{
    public function testExportDownloadsJsonAndRecordsAction(): void
    {
        $user = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 2);

        $appearance = SiteAppearance::defaults();
        $appearance->setBrandName('Beacon');
        $settings = InstanceSettings::defaults();
        $appearanceRepo = $this->createStub(SiteAppearanceRepository::class);
        $appearanceRepo->method('getOrCreate')->willReturn($appearance);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $portability = new InstanceConfigPortability(
            $appearanceRepo,
            $settingsRepo,
            new SiteAppearanceProvider($appearanceRepo),
        );

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $controller = new InstanceConfigController(
            $portability,
            new UserActionRecorder($em, new RequestStack()),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $response = $controller->export();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('beacon-instance-config.json', (string) $response->headers->get('Content-Disposition'));
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(InstanceConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame('Beacon', $payload['appearance']['brand_name']);
        self::assertCount(1, $persisted);
        self::assertInstanceOf(UserAction::class, $persisted[0]);
        self::assertSame(UserActionType::InstanceConfigExported, $persisted[0]->getAction());
    }

    public function testIndexRendersImportForm(): void
    {
        $formView = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $appearanceRepo = $this->createStub(SiteAppearanceRepository::class);
        $appearanceRepo->method('getOrCreate')->willReturn(SiteAppearance::defaults());
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());

        $controller = new InstanceConfigController(
            new InstanceConfigPortability(
                $appearanceRepo,
                $settingsRepo,
                new SiteAppearanceProvider($appearanceRepo),
            ),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/instance-config/import');
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $name, array $context): string {
            self::assertSame('settings/instance_config.html.twig', $name);
            self::assertArrayHasKey('importForm', $context);

            return 'ok';
        });
        $container = new Container();
        $container->set('router', $urls);
        $container->set('form.factory', $formFactory);
        $container->set('twig', $twig);
        $controller->setContainer($container);

        self::assertSame('ok', $controller->index()->getContent());
    }
}

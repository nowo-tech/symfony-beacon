<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Settings\Controller;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Mailer\MailerDsnValidator;
use App\Shared\Settings\Controller\MailerSettingsController;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MailerSettingsControllerTest extends TestCase
{
    public function testRecordMailerAuditSkipsWhenUnchangedAndRecordsWhenDsnChanges(): void
    {
        $user = (new User())->setEmail('admin@example.com');
        (new ReflectionProperty(User::class, 'id'))->setValue($user, 1);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->method('flush');

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $controller = new MailerSettingsController(
            $settingsRepo,
            new ConfiguredMailer($settingsRepo, new MailerDsnValidator(), 'null://null', 'test'),
            $this->createStub(TranslatorInterface::class),
            new NullLogger(),
            new UserActionRecorder($em, new RequestStack()),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_ADMIN', 'ROLE_USER']));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $method = new ReflectionMethod(MailerSettingsController::class, 'recordMailerAudit');
        $method->invoke($controller, 'smtp://a@example:25', 'from@example.com', 'smtp://a@example:25', 'from@example.com');
        self::assertSame([], $persisted);

        $method->invoke($controller, 'smtp://a@example:25', 'from@example.com', 'smtp://b@example:25', 'from@example.com');
        self::assertCount(1, $persisted);
        self::assertInstanceOf(UserAction::class, $persisted[0]);
        self::assertSame(UserActionType::InstanceMailerUpdated, $persisted[0]->getAction());
        self::assertTrue($persisted[0]->getContext()['dsn_changed'] ?? false);
        self::assertFalse($persisted[0]->getContext()['from_changed'] ?? true);
        self::assertSame('smtp', $persisted[0]->getContext()['scheme'] ?? null);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OpsDefaultsSettingsTest extends DatabaseWebTestCase
{
    public function testAdminCanSaveOpsDefaults(): void
    {
        [$client, $admin] = $this->bootAdmin('ops-defaults@example.com');
        $this->login($client, $admin);

        $crawler = $client->request(Request::METHOD_GET, '/settings/ops-defaults');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="ops-defaults-form"]');

        $form = $crawler->filter('[data-testid="ops-defaults-form"]')->form([
            'instance_ops_defaults[retentionDays]' => '30',
            'instance_ops_defaults[retentionMaxEvents]' => '10000',
            'instance_ops_defaults[ingestRateLimit]' => '200',
            'instance_ops_defaults[eventQuotaDaily]' => '5000',
            'instance_ops_defaults[eventQuotaMonthly]' => '100000',
            'instance_ops_defaults[notificationDeliveryHistoryLimit]' => '25',
            'instance_ops_defaults[notificationCircuitBreakerThreshold]' => '4',
            'instance_ops_defaults[notificationCircuitBreakerCooldownMinutes]' => '15',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/ops-defaults');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertSame(30, $settings->getRetentionDays());
        self::assertSame(10000, $settings->getRetentionMaxEvents());
        self::assertSame(200, $settings->getIngestRateLimit());
        self::assertSame(5000, $settings->getEventQuotaDaily());
        self::assertSame(100000, $settings->getEventQuotaMonthly());
        self::assertSame(25, $settings->getNotificationDeliveryHistoryLimit());
        self::assertSame(4, $settings->getNotificationCircuitBreakerThreshold());
        self::assertSame(15, $settings->getNotificationCircuitBreakerCooldownMinutes());
    }

    public function testNonAdminDenied(): void
    {
        [$client] = $this->bootAdmin('ops-admin-seed@example.com');
        $user = $this->makeUser('ops-member@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/settings/ops-defaults');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{0: KernelBrowser, 1: User}
     */
    private function bootAdmin(string $email): array
    {
        $client = self::createClient();
        $this->seedPlatformCatalogs();
        $admin = $this->makeUser($email, ['ROLE_ADMIN']);

        return [$client, $admin];
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(string $email, array $roles = []): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('Ops Tester');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}

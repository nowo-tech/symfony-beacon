<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Tests\Support\DatabaseWebTestCase;
use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OpsDefaultsSettingsTest extends DatabaseWebTestCase
{
    public function testIndexRedirectsToGovernanceTab(): void
    {
        [$client, $admin] = $this->bootAdmin('ops-defaults-redirect@example.com');
        $this->login($client, $admin);

        $client->request(Request::METHOD_GET, '/admin/ops-defaults');
        self::assertResponseRedirects('/admin/ops-defaults/governance');
    }

    public function testAdminCanSaveOpsDefaultsPerSection(): void
    {
        [$client, $admin] = $this->bootAdmin('ops-defaults@example.com');
        $this->login($client, $admin);

        $this->submitSection($client, 'governance', [
            'instance_ops_defaults[retentionDays]' => '30',
            'instance_ops_defaults[retentionMaxEvents]' => '10000',
            'instance_ops_defaults[ingestRateLimit]' => '200',
            'instance_ops_defaults[eventQuotaDaily]' => '5000',
            'instance_ops_defaults[eventQuotaMonthly]' => '100000',
        ]);
        $this->submitSection($client, 'ingest', [
            'instance_ops_defaults[envelopeMaxBytes]' => '1048576',
            'instance_ops_defaults[ingestRejectQueryAuth]' => '1',
        ]);
        $this->submitSection($client, 'metrics', [
            'instance_ops_defaults[metricsRequireToken]' => '1',
            'instance_ops_defaults[plainMetricsToken]' => 'ops-metrics-token',
        ]);
        $this->submitSection($client, 'inbound', [
            'instance_ops_defaults[inboundEmailEnabled]' => '1',
            'instance_ops_defaults[inboundMailDomain]' => 'mail.example.test',
            'instance_ops_defaults[plainInboundWebhookSecret]' => 'ops-inbound-secret',
        ]);
        $this->submitSection($client, 'notifications', [
            'instance_ops_defaults[allowPrivateUrls]' => '1',
            'instance_ops_defaults[allowAnonymousResolve]' => '1',
            'instance_ops_defaults[notificationDeliveryHistoryLimit]' => '25',
            'instance_ops_defaults[notificationCircuitBreakerThreshold]' => '4',
            'instance_ops_defaults[notificationCircuitBreakerCooldownMinutes]' => '15',
        ]);

        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertSame(30, $settings->getRetentionDays());
        self::assertSame(10000, $settings->getRetentionMaxEvents());
        self::assertSame(200, $settings->getIngestRateLimit());
        self::assertSame(5000, $settings->getEventQuotaDaily());
        self::assertSame(100000, $settings->getEventQuotaMonthly());
        self::assertSame(1048576, $settings->getEnvelopeMaxBytes());
        self::assertTrue($settings->isIngestRejectQueryAuth());
        self::assertTrue($settings->isMetricsRequireToken());
        self::assertSame('ops-metrics-token', $settings->getMetricsToken());
        self::assertTrue($settings->isInboundEmailEnabled());
        self::assertSame('mail.example.test', $settings->getInboundMailDomain());
        self::assertSame('ops-inbound-secret', $settings->getInboundWebhookSecret());
        self::assertTrue($settings->isAllowPrivateUrls());
        self::assertTrue($settings->isAllowAnonymousResolve());
        self::assertSame(25, $settings->getNotificationDeliveryHistoryLimit());
        self::assertSame(4, $settings->getNotificationCircuitBreakerThreshold());
        self::assertSame(15, $settings->getNotificationCircuitBreakerCooldownMinutes());
    }

    public function testNonAdminDenied(): void
    {
        [$client] = $this->bootAdmin('ops-admin-seed@example.com');
        $user = $this->makeUser('ops-member@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/ops-defaults/governance');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, string> $values
     */
    private function submitSection(KernelBrowser $client, string $section, array $values): void
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/ops-defaults/'.$section);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="ops-defaults-tabs"]');
        self::assertSelectorExists('[data-testid="ops-defaults-tab-'.$section.'"]');
        self::assertSelectorExists('[data-testid="ops-defaults-form"][data-ops-section="'.$section.'"]');

        $form = $crawler->filter('[data-testid="ops-defaults-form"]')->form($values);
        $client->submit($form);
        self::assertResponseRedirects('/admin/ops-defaults/'.$section);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
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

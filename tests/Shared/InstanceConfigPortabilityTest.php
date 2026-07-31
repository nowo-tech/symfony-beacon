<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use App\Shared\Appearance\Repository\SiteAppearanceRepository;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceConfigPortability;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InstanceConfigPortabilityTest extends DatabaseWebTestCase
{
    public function testExportOmitsSecretsAndRoundTripsAppearance(): void
    {
        [$client, $admin] = $this->bootAdmin('config-export@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $appearance = self::getContainer()->get(SiteAppearanceRepository::class)->getOrCreate();
        $appearance->setBrandName('Export Brand');
        $appearance->setAccentColor('#abcdef');
        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setMailerDsn('smtp://user:super-secret@mail.example:587');
        $settings->setMailerFrom('ops@example.com');
        $settings->setMercureJwtSecret('jwt-super-secret');
        $settings->setMercureEnabled(true);
        $settings->setRetentionDays(30);
        $settings->setIngestRateLimit(240);
        $settings->setNotificationCircuitBreakerThreshold(7);
        $em->flush();

        $portability = self::getContainer()->get(InstanceConfigPortability::class);
        $payload = $portability->export();
        $json = json_encode($payload, \JSON_THROW_ON_ERROR);

        self::assertSame(InstanceConfigPortability::SCHEMA, $payload['schema']);
        self::assertSame('Export Brand', $payload['appearance']['brand_name']);
        self::assertTrue($payload['instance']['mercure_enabled']);
        self::assertTrue($payload['instance']['mailer_configured']);
        self::assertSame(30, $payload['instance']['retention_days']);
        self::assertSame(240, $payload['instance']['ingest_rate_limit']);
        self::assertSame(7, $payload['instance']['notification_circuit_breaker_threshold']);
        self::assertStringNotContainsString('super-secret', $json);
        self::assertStringNotContainsString('smtp://', $json);
        self::assertStringNotContainsString('ops@example.com', $json);
        self::assertArrayNotHasKey('mailer_dsn', $payload['instance']);

        $appearance->setBrandName('Changed');
        $appearance->setAccentColor('#000000');
        $settings->setMercureEnabled(false);
        $settings->setRetentionDays(0);
        $settings->setIngestRateLimit(1);
        $settings->setNotificationCircuitBreakerThreshold(1);
        $em->flush();

        $portability->import($payload);
        $em->clear();

        $reloaded = self::getContainer()->get(SiteAppearanceRepository::class)->getOrCreate();
        $reloadedSettings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertSame('Export Brand', $reloaded->getBrandName());
        self::assertSame('#abcdef', $reloaded->getAccentColor());
        self::assertTrue($reloadedSettings->isMercureEnabled());
        self::assertSame(30, $reloadedSettings->getRetentionDays());
        self::assertSame(240, $reloadedSettings->getIngestRateLimit());
        self::assertSame(7, $reloadedSettings->getNotificationCircuitBreakerThreshold());
        self::assertSame('smtp://user:super-secret@mail.example:587', $reloadedSettings->getMailerDsn());
        self::assertSame('jwt-super-secret', $reloadedSettings->getMercureJwtSecret());

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/settings/instance-config/export');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Export Brand', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('super-secret', (string) $client->getResponse()->getContent());
    }

    public function testImportRejectsSecretKeys(): void
    {
        $this->bootAdmin('config-secret@example.com');
        $portability = self::getContainer()->get(InstanceConfigPortability::class);

        $this->expectException(InvalidArgumentException::class);
        $portability->import([
            'schema' => InstanceConfigPortability::SCHEMA,
            'version' => InstanceConfigPortability::VERSION,
            'instance' => ['mailer_dsn' => 'smtp://leak'],
        ]);
    }

    public function testImportAcceptsVersionOnePayload(): void
    {
        $this->bootAdmin('config-v1@example.com');
        $portability = self::getContainer()->get(InstanceConfigPortability::class);

        self::assertSame(['instance'], $portability->import([
            'schema' => InstanceConfigPortability::SCHEMA,
            'version' => 1,
            'instance' => ['mercure_enabled' => true],
        ]));
    }

    public function testNonAdminDeniedAndImportViaUi(): void
    {
        [$client, $admin] = $this->bootAdmin('config-ui@example.com');
        $user = $this->makeUser('config-member@example.com');
        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/settings/instance-config');
        self::assertResponseStatusCodeSame(403);

        $payload = self::getContainer()->get(InstanceConfigPortability::class)->export();
        $payload['appearance']['brand_name'] = 'Imported Via UI';
        $tmp = tempnam(sys_get_temp_dir(), 'beacon-cfg-');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, json_encode($payload, \JSON_THROW_ON_ERROR));

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/settings/instance-config');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Import JSON')->form();
        $configField = $form->get('config');
        self::assertInstanceOf(FileFormField::class, $configField);
        $configField->upload($tmp);
        $client->submit($form);
        self::assertResponseRedirects('/settings/instance-config');
        $client->followRedirect();
        self::assertSame(
            'Imported Via UI',
            self::getContainer()->get(SiteAppearanceRepository::class)->getOrCreate()->getBrandName(),
        );
        @unlink($tmp);
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
        $user->setDisplayName('Config Tester');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}

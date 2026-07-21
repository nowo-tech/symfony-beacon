<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\ProjectApiKey;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verifies nowo-tech/doctrine-encrypt-bundle encrypts sensitive columns at rest.
 */
final class DoctrineEncryptTest extends DatabaseWebTestCase
{
    public function testApiKeySecretIsEncryptedInDatabase(): void
    {
        [, , $project] = $this->bootWithDemoProject('encrypt-key@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $apiKey = ProjectApiKey::generate($project, 'Encrypt test');
        $plainSecret = $apiKey->getSecretKey();
        self::assertNotNull($plainSecret);
        $em->persist($apiKey);
        $em->flush();
        $id = $apiKey->getId();
        self::assertNotNull($id);

        $conn = $em->getConnection();
        $raw = $conn->fetchOne('SELECT secret_key FROM project_api_key WHERE id = ?', [$id]);
        self::assertIsString($raw);
        self::assertNotSame($plainSecret, $raw);
        self::assertStringEndsWith('<ENC>', $raw);

        $em->clear();
        $reloaded = $em->find(ProjectApiKey::class, $id);
        self::assertNotNull($reloaded);
        self::assertSame($plainSecret, $reloaded->getSecretKey());
    }

    public function testNotificationEndpointUrlIsEncryptedInDatabase(): void
    {
        [, , $project] = $this->bootWithDemoProject('encrypt-hook@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Slack');
        $destination->setType(NotificationDestinationType::Slack);
        $destination->setEndpointUrl('https://hooks.example.com/services/SECRET_TOKEN');
        $em->persist($destination);
        $em->flush();
        $id = $destination->getId();
        self::assertNotNull($id);

        $raw = $em->getConnection()->fetchOne(
            'SELECT endpoint_url FROM notification_destination WHERE id = ?',
            [$id]
        );
        self::assertIsString($raw);
        self::assertStringNotContainsString('SECRET_TOKEN', $raw);
        self::assertStringEndsWith('<ENC>', $raw);

        $em->clear();
        $reloaded = $em->find(NotificationDestination::class, $id);
        self::assertNotNull($reloaded);
        self::assertSame('https://hooks.example.com/services/SECRET_TOKEN', $reloaded->getEndpointUrl());
    }

    public function testInstanceMailerAndMercureSettingsAreEncryptedInDatabase(): void
    {
        $this->bootWithDemoProject('encrypt-instance@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = self::getContainer()->get(InstanceSettingsRepository::class);

        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('smtp://user:s3cret@mail.example:587');
        $settings->setMailerFrom('ops@example.com');
        $settings->setMercureEnabled(true);
        $settings->setMercureUrl('http://mercure/.well-known/mercure');
        $settings->setMercurePublicUrl('https://beacon.example/.well-known/mercure');
        $settings->setMercureJwtSecret('!ChangeThisMercureHubJWTSecretKey!');
        $repo->save($settings);

        $conn = $em->getConnection();
        $columns = [
            'mailer_dsn' => 's3cret',
            'mailer_from' => 'ops@example.com',
            'mercure_url' => 'http://mercure/.well-known/mercure',
            'mercure_public_url' => 'https://beacon.example/.well-known/mercure',
            'mercure_jwt_secret' => '!ChangeThisMercureHubJWTSecretKey!',
        ];
        foreach ($columns as $column => $plain) {
            $raw = $conn->fetchOne(\sprintf('SELECT %s FROM instance_settings WHERE id = 1', $column));
            self::assertIsString($raw, $column);
            self::assertNotSame($plain, $raw, $column);
            self::assertStringEndsWith('<ENC>', $raw, $column);
            self::assertStringNotContainsString($plain, $raw, $column);
        }

        $em->clear();
        $reloaded = $em->find(InstanceSettings::class, 1);
        self::assertNotNull($reloaded);
        self::assertSame('smtp://user:s3cret@mail.example:587', $reloaded->getMailerDsn());
        self::assertSame('ops@example.com', $reloaded->getMailerFrom());
        self::assertSame('http://mercure/.well-known/mercure', $reloaded->getMercureUrl());
        self::assertSame('https://beacon.example/.well-known/mercure', $reloaded->getMercurePublicUrl());
        self::assertSame('!ChangeThisMercureHubJWTSecretKey!', $reloaded->getMercureJwtSecret());
    }
}

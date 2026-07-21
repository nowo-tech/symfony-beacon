<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\ProjectApiKey;
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
}

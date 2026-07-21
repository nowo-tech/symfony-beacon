<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

final class InstanceMailerSettingsTest extends DatabaseWebTestCase
{
    public function testMailerSettingsRequireAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/settings/mailer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanStoreEncryptedMailerDsnAndFromAddress(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-admin@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/settings/mailer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'falls back to MAILER_DSN');

        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'smtp://user:s3cret@mail.example:587',
            'instance_mailer_settings[mailerFrom]' => 'alerts@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/mailer');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'uses the DSN saved in instance settings');
        self::assertSelectorTextContains('body', 'alerts@example.com');

        $conn = $em->getConnection();
        $raw = $conn->fetchOne('SELECT mailer_dsn FROM instance_settings WHERE id = 1');
        self::assertIsString($raw);
        self::assertNotSame('smtp://user:s3cret@mail.example:587', $raw);
        self::assertStringEndsWith('<ENC>', $raw);
        self::assertStringNotContainsString('s3cret', $raw);

        $em->clear();
        $settings = $em->find(InstanceSettings::class, 1);
        self::assertNotNull($settings);
        self::assertSame('smtp://user:s3cret@mail.example:587', $settings->getMailerDsn());
        self::assertSame('alerts@example.com', $settings->getMailerFrom());

        $mailer = self::getContainer()->get(ConfiguredMailer::class);
        self::assertTrue($mailer->isConfiguredFromDatabase());
        self::assertSame('smtp://user:s3cret@mail.example:587', $mailer->getEffectiveDsn());
        self::assertSame('alerts@example.com', $mailer->getFromAddress());
        self::assertInstanceOf(MailerInterface::class, self::getContainer()->get(MailerInterface::class));
    }

    public function testClearStoredDsnFallsBackToEnvironment(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-clear@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $repo = self::getContainer()->get(InstanceSettingsRepository::class);
        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('null://null');
        $settings->setMailerFrom('ops@example.com');
        $repo->save($settings);

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/settings/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[clearMailerDsn]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/settings/mailer');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'falls back to MAILER_DSN');

        $em->clear();
        $reloaded = $em->find(InstanceSettings::class, 1);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getMailerDsn());
        self::assertFalse(self::getContainer()->get(ConfiguredMailer::class)->isConfiguredFromDatabase());
    }
}

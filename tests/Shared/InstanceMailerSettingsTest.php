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

        $rawFrom = $conn->fetchOne('SELECT mailer_from FROM instance_settings WHERE id = 1');
        self::assertIsString($rawFrom);
        self::assertNotSame('alerts@example.com', $rawFrom);
        self::assertStringEndsWith('<ENC>', $rawFrom);
        self::assertStringNotContainsString('alerts@example.com', $rawFrom);

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

    public function testRejectsInvalidAndNullMailerDsnOnSave(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-invalid@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/settings/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'not-a-valid-dsn',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'valid Symfony Mailer DSN');

        $crawler = $client->request(Request::METHOD_GET, '/settings/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'null://null',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'null:// cannot deliver mail');
    }

    public function testAdminCanSendSampleEmailWhenMagicLoginReady(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-sample@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $repo = self::getContainer()->get(InstanceSettingsRepository::class);
        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:1025');
        $settings->setMailerFrom('beacon@example.com');
        $repo->save($settings);
        self::getContainer()->get(ConfiguredMailer::class)->reset();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/settings/mailer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Magic-link email credentials are ready');
        self::assertSelectorExists('form[action$="/settings/mailer/test"]');

        $token = $crawler->filter('form[action$="/settings/mailer/test"] input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        $client->request(Request::METHOD_POST, '/settings/mailer/test', [
            '_token' => $token,
            'to' => 'mailer-sample@example.com',
        ]);
        self::assertResponseRedirects('/settings/mailer');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Sample email sent');
    }

    public function testSampleSendBlockedWithoutDeliverableDsn(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-sample-blocked@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/settings/mailer');
        self::assertSelectorTextContains('body', 'Magic-link email is unavailable');
        self::assertSelectorNotExists('form[action$="/settings/mailer/test"]');

        $client->request(Request::METHOD_POST, '/settings/mailer/test', [
            '_token' => 'invalid',
            'to' => 'x@example.com',
        ]);
        // CSRF or access denial — either way sample must not pretend success without DSN.
        self::assertTrue(\in_array($client->getResponse()->getStatusCode(), [403, 302], true));
    }
}

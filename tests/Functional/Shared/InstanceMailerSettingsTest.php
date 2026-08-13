<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

final class InstanceMailerSettingsTest extends DatabaseWebTestCase
{
    public function testMailerSettingsRequireAdmin(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-member@example.com');
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/mailer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanStoreEncryptedMailerDsnAndFromAddress(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-admin@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        // Halite key is auto-created in memory when var/secrets is unavailable; keep the kernel
        // so encrypted instance_settings stay readable across the save redirect.
        $client->disableReboot();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'falls back to MAILER_DSN');

        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'smtp://user:s3cret@mail.example:587',
            'instance_mailer_settings[mailerFrom]' => 'alerts@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/mailer');
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

        $actions = $em->getRepository(UserAction::class)->findBy(
            ['action' => UserActionType::InstanceMailerUpdated],
            ['id' => 'DESC'],
            1,
        );
        self::assertCount(1, $actions);
        $context = $actions[0]->getContext();
        self::assertTrue($context['dsn_changed'] ?? false);
        self::assertTrue($context['from_changed'] ?? false);
        self::assertFalse($context['cleared'] ?? true);
        self::assertSame('smtp', $context['scheme'] ?? null);
        self::assertSame('mail.example', $context['host'] ?? null);
        self::assertStringNotContainsString('s3cret', json_encode($context, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('smtp://', json_encode($context, \JSON_THROW_ON_ERROR));
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
        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[clearMailerDsn]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/mailer');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'falls back to MAILER_DSN');

        $em->clear();
        $reloaded = $em->find(InstanceSettings::class, 1);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getMailerDsn());
        self::assertFalse(self::getContainer()->get(ConfiguredMailer::class)->isConfiguredFromDatabase());

        $actions = $em->getRepository(UserAction::class)->findBy(
            ['action' => UserActionType::InstanceMailerUpdated],
            ['id' => 'DESC'],
            1,
        );
        self::assertCount(1, $actions);
        $context = $actions[0]->getContext();
        self::assertTrue($context['cleared'] ?? false);
        self::assertTrue($context['dsn_changed'] ?? false);
        self::assertSame('', $context['scheme'] ?? null);
        self::assertSame('', $context['host'] ?? null);
    }

    public function testRejectsInvalidAndNullMailerDsnOnSave(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-invalid@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'not-a-valid-dsn',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'scheme is not allowed');

        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
        $form = $crawler->selectButton('Save mailer settings')->form([
            'instance_mailer_settings[plainMailerDsn]' => 'file:///tmp/mail.sock',
        ]);
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'scheme is not allowed');

        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
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

        $client->disableReboot();
        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/admin/mailer');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Magic-link email credentials are ready');
        self::assertSelectorExists('form[action$="/admin/mailer/test"]');

        $form = $crawler->filter('form[action$="/admin/mailer/test"]')->form();
        $client->submit($form, [
            'mailer_test[to]' => 'mailer-sample@example.com',
        ]);
        self::assertResponseRedirects('/admin/mailer');
        $client->followRedirect();
        $body = $client->getResponse()->getContent() ?: '';
        self::assertTrue(
            str_contains($body, 'Sample email sent') || str_contains($body, 'Correo de muestra enviado'),
            'Expected sample-sent flash (EN or ES catalogue).',
        );
    }

    public function testSampleSendBlockedWithoutDeliverableDsn(): void
    {
        [$client, $user] = $this->bootWithDemoProject('mailer-sample-blocked@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user->setRoles(['ROLE_ADMIN']);
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/mailer');
        self::assertSelectorTextContains('body', 'Magic-link email is unavailable');
        self::assertSelectorNotExists('form[action$="/admin/mailer/test"]');

        $client->request(Request::METHOD_POST, '/admin/mailer/test', [
            '_token' => 'invalid',
            'to' => 'x@example.com',
        ]);
        // CSRF or access denial — either way sample must not pretend success without DSN.
        self::assertTrue(\in_array($client->getResponse()->getStatusCode(), [403, 302], true));
    }
}

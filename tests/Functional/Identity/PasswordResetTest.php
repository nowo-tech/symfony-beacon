<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Shared\Mailer\ConfiguredMailer;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\AuthKitBundle\PasswordReset\PasswordResetTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetTest extends DatabaseWebTestCase
{
    public function testPasswordResetRequestAndLinkComplete(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->enableEncryptedMailer();

        $user = new User();
        $user->setEmail('reset@link.example.com');
        $user->setDisplayName('Reset Link');
        $user->setPassword($hasher->hashPassword($user, 'OldSecret1!'));
        $em->persist($user);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/en/reset-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href*="reset-password/complete"]');

        $form = $crawler->filter('form')->form();
        $phpValues = $form->getPhpValues();
        $root = (string) array_key_first($phpValues);
        $phpValues[$root]['identifier'] = 'reset@link.example.com';
        $client->request(Request::METHOD_POST, '/en/reset-password', $phpValues);
        self::assertResponseRedirects();

        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'reset@link.example.com']);
        self::assertNotNull($user);

        /** @var PasswordResetTokenManagerInterface $tokens */
        $tokens = self::getContainer()->get(PasswordResetTokenManagerInterface::class);
        $result = $tokens->createForUser($user);
        $linkToken = $result->linkToken();
        self::assertNotNull($linkToken);

        $crawler = $client->request(Request::METHOD_GET, '/en/reset-password/reset/'.$linkToken);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form')->form();
        $phpValues = $form->getPhpValues();
        $root = (string) array_key_first($phpValues);
        $phpValues[$root]['password'] = 'NewSecret1!';
        $phpValues[$root]['password_confirm'] = 'NewSecret1!';
        $client->request(Request::METHOD_POST, '/en/reset-password/reset/'.$linkToken, $phpValues);
        self::assertResponseRedirects();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'reset@link.example.com']);
        self::assertNotNull($reloaded);
        self::assertTrue($hasher->isPasswordValid($reloaded, 'NewSecret1!'));
    }

    public function testPasswordResetCodeComplete(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->enableEncryptedMailer();

        $user = new User();
        $user->setEmail('reset@code.example.com');
        $user->setDisplayName('Reset Code');
        $user->setPassword($hasher->hashPassword($user, 'OldSecret1!'));
        $em->persist($user);
        $em->flush();

        /** @var PasswordResetTokenManagerInterface $tokens */
        $tokens = self::getContainer()->get(PasswordResetTokenManagerInterface::class);
        $result = $tokens->createForUser($user);
        $code = $result->code();
        self::assertNotNull($code);

        $crawler = $client->request(Request::METHOD_GET, '/en/reset-password/complete');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('nowo-otp-input, [data-controller*="nowo-otp-input"], [data-nowo-otp-digit]')->count(),
            'Password-reset code field should render OtpType (otp-input-bundle).',
        );
        $form = $crawler->filter('form')->form();
        $phpValues = $form->getPhpValues();
        $root = (string) array_key_first($phpValues);
        $phpValues[$root]['identifier'] = 'reset@code.example.com';
        $phpValues[$root]['code'] = $code;
        $phpValues[$root]['password'] = 'CodeSecret1!';
        $phpValues[$root]['password_confirm'] = 'CodeSecret1!';
        $client->request(Request::METHOD_POST, '/en/reset-password/complete', $phpValues);
        self::assertResponseRedirects();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'reset@code.example.com']);
        self::assertNotNull($reloaded);
        self::assertTrue($hasher->isPasswordValid($reloaded, 'CodeSecret1!'));
    }

    private function enableEncryptedMailer(): void
    {
        $repo = self::getContainer()->get(InstanceSettingsRepository::class);
        $settings = $repo->getOrCreate();
        $settings->setMailerDsn('smtp://user:pass@127.0.0.1:1025');
        $settings->setMailerFrom('beacon@example.com');
        $repo->save($settings);
        self::getContainer()->get(ConfiguredMailer::class)->reset();
        self::assertTrue(self::getContainer()->get(ConfiguredMailer::class)->isMagicLoginAvailable());
    }
}

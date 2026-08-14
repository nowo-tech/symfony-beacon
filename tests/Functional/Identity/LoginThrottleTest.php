<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginThrottleTest extends DatabaseWebTestCase
{
    public function testLoginLocksAfterMaxFailedAttempts(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $email = \sprintf('throttle-%s@example.com', bin2hex(random_bytes(4)));
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('Throttle');
        $user->setPassword($hasher->hashPassword($user, 'CorrectHorse1!'));
        $em->persist($user);
        $em->flush();

        // security.yaml login_throttling.max_attempts = 5 → lock on the 5th failure.
        for ($i = 0; $i < 4; ++$i) {
            $this->submitFailedLogin($client, $email);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.nowo-auth-kit__alert--error');
            self::assertStringNotContainsString(
                'Too many failed login attempts',
                (string) $client->getResponse()->getContent(),
                \sprintf('Attempt %d should not be throttled yet', $i + 1)
            );
        }

        $this->submitFailedLogin($client, $email);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.nowo-auth-kit__alert--error', 'Too many failed login attempts');
    }

    public function testFailedAttemptsDoNotThrottleUnrelatedUsernameOnSameIp(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $lockedEmail = \sprintf('throttle-a-%s@example.com', bin2hex(random_bytes(4)));
        $otherEmail = \sprintf('throttle-b-%s@example.com', bin2hex(random_bytes(4)));
        foreach ([$lockedEmail, $otherEmail] as $email) {
            $user = new User();
            $user->setEmail($email);
            $user->setDisplayName('Throttle peer');
            $user->setPassword($hasher->hashPassword($user, 'CorrectHorse1!'));
            $em->persist($user);
        }
        $em->flush();

        for ($i = 0; $i < 5; ++$i) {
            $this->submitFailedLogin($client, $lockedEmail);
        }
        self::assertSelectorTextContains('.nowo-auth-kit__alert--error', 'Too many failed login attempts');

        $this->submitFailedLogin($client, $otherEmail);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.nowo-auth-kit__alert--error');
        self::assertStringNotContainsString(
            'Too many failed login attempts',
            (string) $client->getResponse()->getContent(),
            'AuthKit nested username must be tracked per account, not per shared CI/E2E IP',
        );
    }

    private function submitFailedLogin(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();

        $button = $crawler->selectButton('Sign in');
        self::assertGreaterThan(0, $button->count(), 'Sign in button not found');

        $form = $button->form([
            'login_form[_username]' => $email,
            'login_form[_password]' => 'wrong-password',
        ]);
        $client->submit($form);
        // form_login failure redirects back to login
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
    }
}

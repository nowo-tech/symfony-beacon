<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Tests\Shared\DatabaseWebTestCase;
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

        $email = sprintf('throttle-%s@example.com', bin2hex(random_bytes(4)));
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
            self::assertSelectorExists('.flash-error');
            self::assertStringNotContainsString(
                'Too many failed login attempts',
                (string) $client->getResponse()->getContent(),
                sprintf('Attempt %d should not be throttled yet', $i + 1)
            );
        }

        $this->submitFailedLogin($client, $email);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash-error', 'Too many failed login attempts');
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

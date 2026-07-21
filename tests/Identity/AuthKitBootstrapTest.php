<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthKitBootstrapTest extends DatabaseWebTestCase
{
    public function testLoginPageIsPublic(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'symfony-beacon');
        self::assertSelectorExists('input[name="login_form[_remember_me]"]');
    }

    public function testSpanishLoginPage(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Iniciar sesión');
    }

    public function testRegisterPageAvailableWhenNoUsers(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/en/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testFirstUserCanRegisterThenLogin(): void
    {
        $client = self::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/en/register');
        self::assertResponseIsSuccessful();

        $button = $crawler->selectButton('Register');
        self::assertGreaterThan(0, $button->count(), 'Register submit button not found');

        $form = $button->form();
        $values = $form->getPhpValues();
        self::assertArrayHasKey('registration_form', $values);

        $formValues = [
            'registration_form' => [
                'email' => 'admin@example.com',
                'displayName' => 'Admin',
                'password' => 'Secret123!',
                'password_confirm' => 'Secret123!',
                '_token' => $values['registration_form']['_token'] ?? null,
            ],
        ];

        foreach ($values['registration_form'] as $key => $value) {
            if (!\array_key_exists((string) $key, $formValues['registration_form'])) {
                $formValues['registration_form'][$key] = $value;
            }
        }

        $client->request(Request::METHOD_POST, '/en/register', $formValues);
        self::assertTrue(
            $client->getResponse()->isRedirect(),
            'Expected redirect after registration, got '.$client->getResponse()->getStatusCode().': '.$client->getResponse()->getContent()
        );
        // AuthKit may redirect to /dashboard?_locale=…; UserPreferredLocaleSubscriber strips _locale.
        $client->followRedirect();
        $status = $client->getResponse()->getStatusCode();
        if ($status >= 300 && $status < 400) {
            $client->followRedirect();
        }
        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame('Admin', $user->getDisplayName());
    }

    public function testRegisterRedirectsWhenUserExists(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('existing@example.com');
        $user->setDisplayName('Existing');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $em->persist($user);
        $em->flush();

        $client->request(Request::METHOD_GET, '/en/register');
        self::assertResponseRedirects('/en/login');
    }
}

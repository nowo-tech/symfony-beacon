<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ops;

use App\Identity\Entity\User;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class OpsSecurityPostureFunctionalTest extends DatabaseWebTestCase
{
    public function testPostureWarningAppearsWhenFlagsWeakened(): void
    {
        [$client, $admin] = $this->bootAdmin('ops-posture@example.com');
        $this->login($client, $admin);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setAllowPrivateUrls(true);
        $settings->setMetricsRequireToken(false);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, '/admin/ops');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="ops-security-posture"]');
        self::assertCount(1, $crawler->filter('[data-posture-id="allow_private_urls"]'));
        self::assertCount(1, $crawler->filter('[data-posture-id="metrics_require_token_off"]'));
    }

    public function testNoPostureWarningWithSecureDefaults(): void
    {
        [$client, $admin] = $this->bootAdmin('ops-posture-ok@example.com');
        $this->login($client, $admin);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->setAllowPrivateUrls(false);
        $settings->setAllowAnonymousResolve(false);
        $settings->setMetricsRequireToken(true);
        $em->flush();

        $client->request(Request::METHOD_GET, '/admin/ops');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="ops-security-posture"]');
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
        $user->setDisplayName('Ops Tester');
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}

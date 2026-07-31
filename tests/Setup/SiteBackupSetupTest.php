<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Identity\Entity\User;
use App\Setup\AdminUserProvisioner;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\PlatformBootstrapState;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\SiteBackupBundle\Event\SetupCompletedEvent;
use Nowo\SiteBackupBundle\Setup\AdminUserProvisionerInterface;
use Nowo\SiteBackupBundle\Setup\Storage\SetupMarkerManager;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cold-start via SiteBackupBundle `/setup` + Beacon catalog-empty redirect.
 */
final class SiteBackupSetupTest extends DatabaseWebTestCase
{
    /** Must match `when@test` setup_token in config/packages/nowo_site_backup.yaml */
    private const string SETUP_TOKEN = 'test-setup-token';

    #[Override]
    protected bool $autoSeedPlatformCatalogs = false;

    public function testAdminProvisionerIsBound(): void
    {
        self::createClient();
        $provisioner = self::getContainer()->get(AdminUserProvisionerInterface::class);
        self::assertInstanceOf(AdminUserProvisioner::class, $provisioner);
        self::assertFalse($provisioner->adminExists());
    }

    public function testSetupRouteRequiresTokenWhenConfigured(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSetupRouteRespondsSuccessfullyWithTokenWhenCatalogsEmpty(): void
    {
        $client = self::createClient();
        self::assertTrue(self::getContainer()->get(PlatformBootstrapState::class)->needsPlatformSeed());

        $client->request(Request::METHOD_GET, '/setup?token='.self::SETUP_TOKEN);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        self::assertNotSame(404, $client->getResponse()->getStatusCode());
        self::assertTrue(self::getContainer()->get(SetupMarkerManager::class)->isRequiredMarked());
        self::assertSelectorExists('body');
    }

    public function testSetupTokenGateShowsFriendlyGateIllustration(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/setup');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorExists('[data-testid="setup-token-gate"]');
        self::assertSelectorExists('img.setup-token-gate__img[src*="error-403"]');
    }

    public function testLocalizedSetupRouteRespondsSuccessfullyWithToken(): void
    {
        $client = self::createClient();
        self::assertTrue(self::getContainer()->get(PlatformBootstrapState::class)->needsPlatformSeed());

        $client->request(Request::METHOD_GET, '/es/setup?token='.self::SETUP_TOKEN);
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="es"]');
    }

    public function testHomeRedirectsToSetupWhenPlatformCatalogsEmpty(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/');
        self::assertTrue($client->getResponse()->isRedirection());
        $location = (string) $client->getResponse()->headers->get('Location');
        // Bare /setup (token is not put in Location — operator opens ?token= from env).
        self::assertMatchesRegularExpression('#/setup/?$#', $location);
        self::assertStringNotContainsString('token=', $location);
    }

    public function testLoginAndHealthDoNotRedirectWhenPlatformCatalogsEmpty(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/en/login');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/health/live');
        self::assertResponseIsSuccessful();
    }

    public function testDashboardNotForcedToSetupAfterPlatformSeed(): void
    {
        $client = self::createClient();
        $this->seedPlatformCatalogs();
        self::assertFalse(self::getContainer()->get(PlatformBootstrapState::class)->needsPlatformSeed());

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertTrue($client->getResponse()->isRedirection());
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringNotContainsString('/setup', $location);
    }

    public function testSetupCompletedEventMarksInstanceSettings(): void
    {
        self::createClient();
        $settings = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        $settings->clearSetupCompleted();
        self::getContainer()->get(InstanceSettingsRepository::class)->save($settings);

        self::getContainer()->get('event_dispatcher')->dispatch(new SetupCompletedEvent('fresh_install'));

        $reloaded = self::getContainer()->get(InstanceSettingsRepository::class)->getOrCreate();
        self::assertTrue($reloaded->isSetupCompleted());
    }

    public function testNonAdminDoesNotGetCatalogRedirectToSetup(): void
    {
        $client = self::createClient();
        self::assertTrue(self::getContainer()->get(PlatformBootstrapState::class)->needsPlatformSeed());

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('member-setup@example.com');
        $user->setDisplayName('Member');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($hasher->hashPassword($user, 'secret'));
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/dashboard');
        if ($client->getResponse()->isRedirection()) {
            $location = (string) $client->getResponse()->headers->get('Location');
            self::assertStringNotContainsString('/setup', $location);

            return;
        }
        self::assertResponseIsSuccessful();
    }
}

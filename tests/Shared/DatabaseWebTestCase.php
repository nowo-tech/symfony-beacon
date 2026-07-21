<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Shared\Breadcrumb\BreadcrumbDemoSeeder;
use App\Shared\CookieConsent\CookieConsentDemoSeeder;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests against a disposable SQLite database (`var/cache/test/phpunit.db`).
 *
 * Each test deletes the DB file and recreates the schema so suites stay isolated
 * (dropSchema alone is unreliable with SQLite + foreign keys).
 */
abstract class DatabaseWebTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTestDatabase();
    }

    /**
     * Wipe SQLite file DB, recreate schema, then shut down the kernel so each
     * test boots a clean client via createClient() / bootWithDemoProject().
     */
    private function resetTestDatabase(): void
    {
        static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $params = $conn->getParams();
        $path = $params['path'] ?? null;

        $em->close();
        $conn->close();
        self::ensureKernelShutdown();

        $this->removeSqliteFiles(\is_string($path) ? $path : null);
        $this->clearRateLimiterCache();

        static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        if ([] !== $meta) {
            $schemaTool->createSchema($meta);
        }
        self::ensureKernelShutdown();
    }

    /**
     * Delete the SQLite main DB plus WAL/SHM sidecars.
     */
    private function removeSqliteFiles(?string $path): void
    {
        $candidates = [];
        if (null !== $path && '' !== $path) {
            $candidates[] = $path;
        }
        $projectDir = \dirname(__DIR__, 2);
        $candidates[] = $projectDir.'/var/cache/test/phpunit.db';
        $candidates[] = $projectDir.'/var/test.db';
        $candidates[] = '/tmp/symfony-beacon-phpunit.db';
        $candidates[] = '/dev/shm/symfony-beacon-phpunit.db';

        foreach (array_unique($candidates) as $file) {
            $dir = \dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            foreach ([$file, $file.'-wal', $file.'-shm', $file.'-journal'] as $target) {
                if (is_file($target) && !@unlink($target) && is_file($target)) {
                    throw new RuntimeException('Unable to remove SQLite test database file: '.$target);
                }
            }
        }
    }

    /**
     * login_throttling uses a filesystem cache pool in test; clear it so attempts
     * do not leak across tests (ArrayAdapter would reset, but wipes mid-request).
     */
    private function clearRateLimiterCache(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $poolDir = $projectDir.'/var/cache/test/pools';
        if (!is_dir($poolDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($poolDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                @unlink($file->getPathname());
            }
        }
    }

    /**
     * @return array{0: KernelBrowser, 1: User, 2: Project, 3: ProjectApiKey}
     */
    protected function bootWithDemoProject(string $email = 'user@example.com', string $password = 'secret'): array
    {
        $client = static::createClient();
        $this->seedPlatformCatalogs();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('Test User');
        $user->setPassword($hasher->hashPassword($user, $password));

        $slug = 'acme-'.substr(hash('sha256', $email), 0, 12);
        $project = new Project();
        $project->setName('Acme');
        $project->setSlug($slug);

        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole(ProjectRole::Owner);
        $project->addMembership($membership);

        $apiKey = ProjectApiKey::generate($project, 'Test');
        $project->addApiKey($apiKey);

        $em->persist($user);
        $em->persist($project);
        $em->flush();

        return [$client, $user, $project, $apiKey];
    }

    /**
     * Install menus / breadcrumbs / cookie consent so admin HTML is not forced to /setup.
     */
    protected function seedPlatformCatalogs(): void
    {
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        self::getContainer()->get(BreadcrumbDemoSeeder::class)->seedIfEmpty();
        self::getContainer()->get(CookieConsentDemoSeeder::class)->seedIfEmpty();
    }

    protected function login(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }

    /**
     * Envelope auth headers including secret when the API key has one.
     *
     * @return array<string, string>
     */
    protected function beaconAuthHeaders(ProjectApiKey $apiKey): array
    {
        $header = 'Beacon beacon_key='.$apiKey->getPublicKey();
        $secret = $apiKey->getSecretKey();
        if (null !== $secret && '' !== $secret) {
            $header .= ', beacon_secret='.$secret;
        }

        return ['HTTP_X_BEACON_AUTH' => $header];
    }
}

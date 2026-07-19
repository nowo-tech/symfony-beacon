<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DatabaseWebTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $meta = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($meta);
        $schemaTool->createSchema($meta);
        // Keep client for subclasses via recreate — actually we need fresh each time.
        self::ensureKernelShutdown();
    }

    /**
     * @return array{0: KernelBrowser, 1: User, 2: Project, 3: ProjectApiKey}
     */
    protected function bootWithDemoProject(string $email = 'user@example.com', string $password = 'secret'): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('Test User');
        $user->setPassword($hasher->hashPassword($user, $password));

        $project = new Project();
        $project->setName('Acme');
        $project->setSlug('acme');

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

    protected function login(KernelBrowser $client, User $user): void
    {
        $client->loginUser($user);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\Identity\Entity\User;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PerformanceAccessTest extends DatabaseWebTestCase
{
    public function testMemberCanOpenPerformance(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance');
        self::assertResponseIsSuccessful();
    }

    public function testStrangerIsForbidden(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner-perf@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('stranger-perf@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/performance');
        self::assertResponseStatusCodeSame(403);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectNotificationSettingsTest extends DatabaseWebTestCase
{
    public function testOwnerCanCreateDestinationAndMemberCannot(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-notif@example.com');
        $this->login($client, $owner);

        $crawler = $client->request('GET', '/projects/'.$project->getId().'/notifications/new');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="notification_destination[_token]"]')->attr('value');
        self::assertNotEmpty($token);

        $client->request('POST', '/projects/'.$project->getId().'/notifications/new', [
            'notification_destination' => [
                '_token' => $token,
                'label' => 'Slack errors',
                'type' => 'slack',
                'endpointUrl' => 'https://hooks.slack.com/services/T00/B00/XXX',
                'enabled' => '1',
                'categories' => ['error', 'n_plus_one'],
            ],
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $destinations = $em->getRepository(NotificationDestination::class)->findBy(['project' => $project]);
        self::assertCount(1, $destinations);
        self::assertSame('Slack errors', $destinations[0]->getLabel());

        $member = $this->addMember($project, 'member-notif@example.com', ProjectRole::Member);
        $this->login($client, $member);
        $client->request('GET', '/projects/'.$project->getId().'/notifications/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSettingsListsMaskedUrl(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-mask@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Hooks');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.com/very-secret-token-abcdef');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);
        $project->addNotificationDestination($destination);
        $em->persist($destination);
        $em->flush();

        $this->login($client, $owner);
        $client->request('GET', '/projects/'.$project->getId().'/settings');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('https://exa', $client->getResponse()->getContent() ?: '');
        self::assertStringNotContainsString('very-secret-token-abcdef', $client->getResponse()->getContent() ?: '');
    }

    private function addMember(Project $project, string $email, ProjectRole $role): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName('Member');
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole($role);
        $project->addMembership($membership);

        $em->persist($user);
        $em->flush();

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Identity\Entity\User;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectNotificationSettingsTest extends DatabaseWebTestCase
{
    public function testOwnerCanCreateDestinationAndMemberCannot(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-notif@example.com');
        $this->login($client, $owner);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/notifications/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller*="symfony--ux-autocomplete--autocomplete"]');
        self::assertSelectorExists('select[name="notification_destination[categories][]"][multiple]');

        $token = $crawler->filter('input[name="notification_destination[_token]"]')->attr('value');
        self::assertNotEmpty($token);

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/notifications/new', [
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

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $destinations = $em->getRepository(NotificationDestination::class)->findBy(['project' => $project]);
        self::assertCount(1, $destinations);
        self::assertSame('Slack errors', $destinations[0]->getLabel());

        $member = $this->addMember($project, 'member-notif@example.com', ProjectRole::Member);
        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/notifications/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberCanOpenSetupGuides(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-guides@example.com');
        $member = $this->addMember($project, 'member-guides@example.com', ProjectRole::Member);
        $this->login($client, $member);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/notifications/help');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.notification-help__title', 'Notification setup guides');
        self::assertSelectorExists('#help-slack');
        self::assertSelectorExists('#help-telegram');
        self::assertSelectorExists('#help-discord');
        self::assertSelectorExists('#help-teams');
        self::assertSelectorExists('#help-email');
        self::assertSelectorExists('#help-http');
    }

    public function testSettingsListsMaskedUrl(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-mask@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

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
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('https://exa', $client->getResponse()->getContent() ?: '');
        self::assertStringNotContainsString('very-secret-token-abcdef', $client->getResponse()->getContent() ?: '');
    }

    public function testOwnerCanCreateThresholdRuleAndMemberCannot(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-threshold@example.com');
        $this->login($client, $owner);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/threshold-rules/new');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="project_threshold_rule[_token]"]')->attr('value');
        self::assertNotEmpty($token);

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/threshold-rules/new', [
            'project_threshold_rule' => [
                '_token' => $token,
                'label' => 'Production spike',
                'enabled' => '1',
                'errorCount' => '50',
                'windowMinutes' => '15',
                'cooldownMinutes' => '60',
                'environment' => 'production',
                'releaseVersion' => '1.2.3',
            ],
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Production spike');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $rules = $em->getRepository(ProjectThresholdRule::class)->findBy(['project' => $project]);
        self::assertCount(1, $rules);
        self::assertSame('production', $rules[0]->getEnvironment());
        self::assertSame('1.2.3', $rules[0]->getReleaseVersion());

        $member = $this->addMember($project, 'member-threshold@example.com', ProjectRole::Member);
        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/threshold-rules/new');
        self::assertResponseStatusCodeSame(403);
    }

    private function addMember(Project $project, string $email, ProjectRole $role): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

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

<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IssueAssigneeFunctionalTest extends DatabaseWebTestCase
{
    public function testAssignsProjectMemberAndRejectsOutsider(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-assign@example.com');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('member-assign@example.com');
        $member->setDisplayName('Resolver');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $outsider = new User();
        $outsider->setEmail('outsider-assign@example.com');
        $outsider->setDisplayName('Outsider');
        $outsider->setPassword($hasher->hashPassword($outsider, 'secret'));

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'assign-demo'));
        $issue->setTitle('Assignable issue');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($member);
        $em->persist($outsider);
        $em->persist($issue);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request('GET', '/projects/'.$project->getId().'/issues/'.$issue->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Assignable issue');
        self::assertSelectorExists('select[name="issue_assignee[assignee]"]');
        self::assertSelectorExists('[data-controller*="symfony--ux-autocomplete--autocomplete"]');

        $form = $crawler->filter('form.issue-assignee-form')->form();
        $form['issue_assignee[assignee]']->disableValidation();
        $form['issue_assignee[assignee]']->setValue((string) $member->getId());
        $client->submit($form);
        self::assertResponseRedirects('/projects/'.$project->getId().'/issues/'.$issue->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Resolver');

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame($member->getId(), $reloaded->getAssignee()?->getId());

        $client->request('GET', '/projects/'.$project->getId().'/issues?assignee='.$member->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Assignable issue');

        // Direct POST with outsider id must not stick (membership guard).
        $token = $crawler->filter('form.issue-assignee-form input[name="issue_assignee[_token]"]')->attr('value');
        $client->request('POST', '/projects/'.$project->getId().'/issues/'.$issue->getId().'/assign', [
            'issue_assignee' => [
                'assignee' => (string) $outsider->getId(),
                '_token' => $token,
            ],
        ]);
        self::assertResponseRedirects();
        $em->clear();
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame($member->getId(), $reloaded->getAssignee()?->getId());

        // Autocomplete endpoint lists project members only.
        $crawler = $client->request('GET', '/projects/'.$project->getId().'/issues/'.$issue->getId());
        $autocompleteUrl = $crawler->filter('select[name="issue_assignee[assignee]"]')->attr('data-symfony--ux-autocomplete--autocomplete-url-value');
        self::assertNotNull($autocompleteUrl);
        $client->request('GET', $autocompleteUrl.(str_contains($autocompleteUrl, '?') ? '&' : '?').'query=Resolver');
        self::assertResponseIsSuccessful();
        /** @var array{results?: list<array{text?: string}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $labels = array_map(static fn (array $row): string => (string) ($row['text'] ?? ''), $payload['results'] ?? []);
        self::assertTrue(
            [] !== array_filter($labels, static fn (string $label): bool => str_contains($label, 'Resolver')),
            'Expected member in autocomplete results',
        );
        self::assertTrue(
            [] === array_filter($labels, static fn (string $label): bool => str_contains($label, 'Outsider')),
            'Outsider must not appear in project member autocomplete',
        );

        unset($outsider);
    }
}

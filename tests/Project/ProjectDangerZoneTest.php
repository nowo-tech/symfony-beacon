<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Analytics\Entity\DailyProjectStat;
use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Performance\Entity\PerfSpan;
use App\Performance\Entity\PerfTransaction;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectDangerZoneTest extends DatabaseWebTestCase
{
    public function testOwnerSeesDangerZoneAndCanClearHistory(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('owner-clear@example.com');
        $this->seedHistory($project);
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Danger zone');
        self::assertSelectorExists('form[action$="/clear-history"]');
        self::assertSelectorExists('form[action$="/delete"]');

        $token = $crawler->filter('form[action$="/clear-history"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/clear-history', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Project history cleared.');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, (int) $em->getRepository(Issue::class)->count(['project' => $project]));
        self::assertSame(0, (int) $em->getRepository(PerfTransaction::class)->count(['project' => $project]));
        self::assertSame(0, (int) $em->getRepository(DailyProjectStat::class)->count(['project' => $project]));
        self::assertNotNull($em->getRepository(Project::class)->find($project->getId()));
    }

    public function testDeleteRequiresMatchingProjectName(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('owner-del@example.com');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/delete', [
            '_token' => $token,
            'confirmation' => 'Wrong Name',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Project name did not match');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->getRepository(Project::class)->find($project->getId()));
    }

    public function testOwnerCanDeleteProjectWithTypedName(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('owner-gone@example.com');
        $projectId = $project->getId();
        $projectUuid = $project->getUuid();
        $this->seedHistory($project);
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$projectUuid.'/settings');
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$projectUuid.'/delete', [
            '_token' => $token,
            'confirmation' => 'Acme',
        ]);
        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Project deleted.');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->getRepository(Project::class)->find($projectId));
        self::assertSame(0, (int) $em->getRepository(Issue::class)->count([]));
    }

    public function testMemberCannotClearOrDelete(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-member@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('member-danger@example.com');
        $member->setDisplayName('Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/clear-history"]');
        self::assertSelectorNotExists('form[action$="/delete"]');

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid());
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/clear-history', [
            '_token' => 'x',
        ]);
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/delete', [
            '_token' => 'x',
            'confirmation' => 'Acme',
        ]);
        self::assertResponseStatusCodeSame(403);

        unset($owner);
    }

    private function seedHistory(Project $project): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $now = new DateTimeImmutable();

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(bin2hex(random_bytes(8)));
        $issue->setTitle('Boom');
        $issue->setCulprit('App::run');
        $issue->setFirstSeen($now);
        $issue->setLastSeen($now);

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId(bin2hex(random_bytes(16)));
        $event->setPayload(['message' => 'Boom']);
        $event->setPlatform('php');
        $event->setEventTimestamp($now);
        $event->setReceivedAt($now);

        $tx = new PerfTransaction();
        $tx->setProject($project);
        $tx->setEventId(bin2hex(random_bytes(16)));
        $tx->setTransactionName('GET /demo');
        $tx->setPayload([]);
        $tx->setReceivedAt($now);
        $span = new PerfSpan();
        $span->setSpanId(bin2hex(random_bytes(8)));
        $span->setOp('db');
        $span->setDescription('SELECT 1');
        $tx->addSpan($span);

        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->setStatDate($now->setTime(0, 0));
        $stat->incrementErrorCount();

        $em->persist($issue);
        $em->persist($event);
        $em->persist($tx);
        $em->persist($stat);
        $em->flush();
    }
}

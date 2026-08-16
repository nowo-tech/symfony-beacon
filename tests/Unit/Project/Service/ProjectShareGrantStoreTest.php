<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectShareGrantStore;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ProjectShareGrantStoreTest extends TestCase
{
    public function testIgnoresGrantWhenNoSessionRequestExists(): void
    {
        $store = new ProjectShareGrantStore(new RequestStack(), $this->createStub(ProjectShareLinkRepository::class));
        $project = (new Project())->setName('Beacon')->setSlug('beacon');

        $store->grantShareAccess($project, null, time() + 60, 'share-uuid');

        self::assertFalse($store->hasActiveShareGrant($project));
        self::assertNull($store->getActiveShareEntry($project));
    }

    public function testGrantsProjectWideAndIssueScopedAccessAndClearsInvalidEntries(): void
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $project = (new Project())->setName('Beacon')->setSlug('beacon');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $validLink = (new ProjectShareLink())
            ->setProject($project)
            ->setTokenHash('hash');
        $validShareUuid = $validLink->getUuid();

        $revokedLink = (new ProjectShareLink())
            ->setProject($project)
            ->setTokenHash('revoked');
        $revokedLink->revoke();

        $repo = $this->createStub(ProjectShareLinkRepository::class);
        $repo->method('findOneByUuid')->willReturnCallback(static function (string $uuid) use ($validShareUuid, $validLink, $revokedLink): ?ProjectShareLink {
            return match ($uuid) {
                $validShareUuid => $validLink,
                'revoked-share' => $revokedLink,
                default => null,
            };
        });

        $store = new ProjectShareGrantStore($stack, $repo);
        $store->grantShareAccess($project, null, time() + 120, $validShareUuid);
        self::assertTrue($store->hasActiveShareGrant($project));
        self::assertTrue($store->hasProjectWideShareGrant($project));
        self::assertTrue($store->hasShareGrantForIssue($project, 'issue-1'));
        self::assertNull($store->getActiveShareEntry($project)['issue']);

        $store->grantShareAccess($project, 'issue-1', time() + 120, $validShareUuid);
        self::assertFalse($store->hasProjectWideShareGrant($project));
        self::assertTrue($store->hasShareGrantForIssue($project, 'issue-1'));
        self::assertFalse($store->hasShareGrantForIssue($project, 'issue-2'));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() - 1, 'share' => $validShareUuid]]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120]]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => 'revoked-share']]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => $validShareUuid, 'issue' => 123]]);
        self::assertNull($store->getActiveShareEntry($project)['issue']);

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => $validShareUuid, 'issue' => '']]);
        self::assertNull($store->getActiveShareEntry($project)['issue']);

        $session->remove(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY);
        self::assertFalse($store->hasShareGrantForIssue($project, 'issue-3'));
    }
}

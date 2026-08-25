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
        $project = new Project()->setName('Beacon')->setSlug('beacon');

        $store->grantShareAccess($project, null, time() + 60, 'share-uuid');

        self::assertFalse($store->hasActiveShareGrant($project));
        self::assertNull($store->getActiveShareEntry($project));
    }

    public function testGrantsProjectWideAndIssueScopedAccess(): void
    {
        [$store, $project, $validShareUuid] = $this->storeWithValidLink();

        $store->grantShareAccess($project, null, time() + 120, $validShareUuid);
        self::assertTrue($store->hasActiveShareGrant($project));
        self::assertTrue($store->hasProjectWideShareGrant($project));
        self::assertTrue($store->hasShareGrantForIssue($project, 'issue-1'));
        $entry = $store->getActiveShareEntry($project);
        self::assertNotNull($entry);
        self::assertNull($entry['issue']);

        $store->grantShareAccess($project, 'issue-1', time() + 120, $validShareUuid);
        self::assertFalse($store->hasProjectWideShareGrant($project));
        self::assertTrue($store->hasShareGrantForIssue($project, 'issue-1'));
        self::assertFalse($store->hasShareGrantForIssue($project, 'issue-2'));
    }

    public function testClearsExpiredMissingShareAndRevokedEntries(): void
    {
        [$store, $project, $validShareUuid, $session] = $this->storeWithValidLink();

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() - 1, 'share' => $validShareUuid]]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120]]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => 'revoked-share']]);
        self::assertNull($store->getActiveShareEntry($project));

        $session->remove(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY);
        self::assertFalse($store->hasShareGrantForIssue($project, 'issue-3'));
    }

    public function testNormalizesNonStringAndBlankIssueToNull(): void
    {
        [$store, $project, $validShareUuid, $session] = $this->storeWithValidLink();

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => $validShareUuid, 'issue' => 123]]);
        $withNonStringIssue = $store->getActiveShareEntry($project);
        self::assertNotNull($withNonStringIssue);
        self::assertNull($withNonStringIssue['issue']);

        $session->set(ProjectShareGrantStore::SHARE_ACCESS_SESSION_KEY, [$project->getUuid() => ['expires' => time() + 120, 'share' => $validShareUuid, 'issue' => '']]);
        $withBlankIssue = $store->getActiveShareEntry($project);
        self::assertNotNull($withBlankIssue);
        self::assertNull($withBlankIssue['issue']);
    }

    /**
     * @return array{0: ProjectShareGrantStore, 1: Project, 2: string, 3: Session}
     */
    private function storeWithValidLink(): array
    {
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $project = new Project()->setName('Beacon')->setSlug('beacon');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $validLink = new ProjectShareLink()
            ->setProject($project)
            ->setTokenHash('hash');
        $validShareUuid = $validLink->getUuid();

        $revokedLink = new ProjectShareLink()
            ->setProject($project)
            ->setTokenHash('revoked');
        $revokedLink->revoke();

        $repo = $this->createStub(ProjectShareLinkRepository::class);
        $repo->method('findOneByUuid')->willReturnCallback(static fn (string $uuid): ?ProjectShareLink => match ($uuid) {
            $validShareUuid => $validLink,
            'revoked-share' => $revokedLink,
            default => null,
        });

        return [new ProjectShareGrantStore($stack, $repo), $project, $validShareUuid, $session];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueCommentRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueShowPageBuilder;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectRole;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IssueShowPageBuilderTest extends TestCase
{
    public function testBuildAssemblesPageContext(): void
    {
        $project = new Project()->setName('P')->setSlug('p');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $issue = new Issue()
            ->setProject($project)
            ->setTitle('Boom')
            ->setStatus(IssueStatus::Unresolved)
            ->setPriority(IssuePriority::High);
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, 7);
        $similar = new Issue()->setProject($project)->setTitle('Similar')->setStatus(IssueStatus::Unresolved);
        new ReflectionProperty(Issue::class, 'id')->setValue($similar, 8);

        $event = new Event();
        $events = $this->createStub(EventRepository::class);
        $events->method('findLatestForIssue')->willReturn([$event]);
        $events->method('occurrenceStatsForIssue')->willReturn(new IssueOccurrenceStats(3, 1, 2, 3));
        $comments = $this->createStub(IssueCommentRepository::class);
        $comments->method('findLatestForIssue')->willReturn([]);
        $history = $this->createStub(IssueHistoryEntryRepository::class);
        $history->method('findLatestForIssue')->willReturn([]);
        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findDuplicateCandidates')->willReturn([]);
        $issues->method('findSimilarIssues')->willReturn([$similar]);

        $formView = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($formView);
        $factory = $this->createStub(FormFactoryInterface::class);
        $factory->method('create')->willReturn($form);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/action');

        $page = new IssueShowPageBuilder($events, $comments, $history, $issues, $factory, $urls)
            ->build($project, $issue, new User(), new ProjectAccess(ProjectRole::Member));

        self::assertSame($project, $page['project']);
        self::assertSame($issue, $page['issue']);
        self::assertSame([$event], $page['events']);
        self::assertSame($event, $page['latestEvent']);
        self::assertSame(3, $page['occurrence']->total);
        self::assertSame([$similar], $page['similarIssues']);
        self::assertArrayHasKey(IssueStatus::Resolved->value, $page['statusForms']);
        self::assertArrayNotHasKey(IssueStatus::Unresolved->value, $page['statusForms']);
        self::assertArrayHasKey(8, $page['quickDuplicateForms']);
        self::assertTrue($page['can_triage']);
    }
}

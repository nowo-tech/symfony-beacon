<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueShowTab;
use App\Issues\Enum\IssueStatus;
use App\Issues\Form\IssueAssigneeType;
use App\Issues\Form\IssueCommentType;
use App\Issues\Form\IssueDuplicateType;
use App\Issues\Form\IssuePriorityType;
use App\Issues\Form\IssueStatusType;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueCommentRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Assembles Twig variables for the issue detail page.
 */
final readonly class IssueShowPageBuilder
{
    public function __construct(
        private EventRepository $eventRepository,
        private IssueCommentRepository $commentRepository,
        private IssueHistoryEntryRepository $historyEntryRepository,
        private IssueRepository $issueRepository,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{
     *     project: Project,
     *     issue: Issue,
     *     issueTab: IssueShowTab,
     *     issueTabs: list<IssueShowTab>,
     *     events: list<Event>,
     *     latestEvent: ?Event,
     *     occurrence: IssueOccurrenceStats,
     *     assigneeForm: mixed,
     *     issueHistory: list<mixed>,
     *     comments: list<mixed>,
     *     duplicateCandidates: list<Issue>,
     *     similarIssues: list<Issue>,
     *     statusForms: array<string, mixed>,
     *     priorityForm: mixed,
     *     quickDuplicateForms: array<int|null, mixed>,
     *     duplicateDialogForm: mixed,
     *     commentForm: mixed,
     *     can_triage: bool
     * }
     */
    public function build(
        Project $project,
        Issue $issue,
        User $user,
        ProjectAccess $access,
        IssueShowTab $tab = IssueShowTab::Main,
    ): array {
        $events = $this->eventRepository->findLatestForIssue($issue);
        $latestEvent = $events[0] ?? null;
        $occurrence = $this->eventRepository->occurrenceStatsForIssue($issue);
        $history = $this->historyEntryRepository->findLatestForIssue($issue);
        $comments = $this->commentRepository->findLatestForIssue($issue);
        $duplicateCandidates = $this->issueRepository->findDuplicateCandidates($project, $issue);
        $similarIssues = $this->issueRepository->findSimilarIssues($issue);

        $statusForms = [];
        foreach (IssueStatus::cases() as $status) {
            if ($issue->getStatus() === $status) {
                continue;
            }

            $statusForms[$status->value] = $this->formFactory->create(IssueStatusType::class, [
                'status' => $status->value,
            ], [
                'action' => $this->urlGenerator->generate('issue_status', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_status',
            ])->createView();
        }

        $quickDuplicateForms = [];
        foreach ($similarIssues as $similarIssue) {
            $quickDuplicateForms[$similarIssue->getId()] = $this->formFactory->create(IssueDuplicateType::class, [
                'canonical_uuid' => $similarIssue->getUuid(),
            ], [
                'action' => $this->urlGenerator->generate('issue_mark_duplicate', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_duplicate',
            ])->createView();
        }

        return [
            'project' => $project,
            'issue' => $issue,
            'issueTab' => $tab,
            'issueTabs' => IssueShowTab::cases(),
            'events' => $events,
            'latestEvent' => $latestEvent,
            'occurrence' => $occurrence,
            'assigneeForm' => $this->formFactory->create(IssueAssigneeType::class, $issue, [
                'project_id' => $project->getId(),
                'action' => $this->urlGenerator->generate('issue_assign', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
            ])->createView(),
            'issueHistory' => $history,
            'comments' => $comments,
            'duplicateCandidates' => $duplicateCandidates,
            'similarIssues' => $similarIssues,
            'statusForms' => $statusForms,
            'priorityForm' => $this->formFactory->create(IssuePriorityType::class, [
                'priority' => $issue->getPriority()->value,
            ], [
                'action' => $this->urlGenerator->generate('issue_priority', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_priority',
            ])->createView(),
            'quickDuplicateForms' => $quickDuplicateForms,
            'duplicateDialogForm' => $this->formFactory->create(IssueDuplicateType::class, null, [
                'action' => $this->urlGenerator->generate('issue_mark_duplicate', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_duplicate',
            ])->createView(),
            'commentForm' => $this->formFactory->create(IssueCommentType::class, [
                'body' => '',
            ], [
                'action' => $this->urlGenerator->generate('issue_comment_add', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_comment',
            ])->createView(),
            'can_triage' => $access->canTriageIssues(),
        ];
    }
}

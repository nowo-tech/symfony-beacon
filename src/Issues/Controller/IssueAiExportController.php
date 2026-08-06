<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Export\AiIssueExportFormatter;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AI-oriented issue export (Markdown / JSON) for triage handoff.
 */
#[IsGranted('ROLE_USER')]
final class IssueAiExportController extends AbstractController
{
    public function __construct(
        private readonly AiIssueExportFormatter $aiIssueExportFormatter,
        private readonly EventRepository $eventRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route('/projects/{projectId}/issues/{id}/export/ai.md', name: 'issue_export_ai_md', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET'])]
    public function exportAiMarkdown(
        Request $request,
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): Response {
        return $this->exportAi($request, $projectId, $issue, 'md');
    }

    #[Route('/projects/{projectId}/issues/{id}/export/ai.json', name: 'issue_export_ai_json', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET'])]
    public function exportAiJson(
        Request $request,
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): Response {
        return $this->exportAi($request, $projectId, $issue, 'json');
    }

    private function exportAi(Request $request, string $projectId, Issue $issue, string $format): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireIssueRead($project, $user, $issue->getUuid());

        $event = null;
        $eventId = $request->query->getString('event');
        if ('' !== $eventId) {
            $event = $this->eventRepository->findOneByProjectAndEventId($project, $eventId);
            if (!$event instanceof Event || $event->getIssue()?->getId() !== $issue->getId()) {
                throw $this->createNotFoundException();
            }
        } else {
            $events = $this->eventRepository->findLatestForIssue($issue, 1);
            $event = $events[0] ?? null;
        }

        $absoluteUrl = $this->generateUrl(
            'issue_show',
            ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $data = $this->aiIssueExportFormatter->buildCanonical($project, $issue, $event, $absoluteUrl);

        if ('json' === $format) {
            return new Response(
                $this->aiIssueExportFormatter->toJson($data),
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/json; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="beacon-issue-'.$issue->getUuid().'.ai.json"',
                ],
            );
        }

        return new Response(
            $this->aiIssueExportFormatter->toMarkdown($data),
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="beacon-issue-'.$issue->getUuid().'.ai.md"',
            ],
        );
    }
}

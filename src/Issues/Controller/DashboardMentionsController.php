<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueMention;
use App\Issues\Repository\IssueMentionRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Shared\Pagination\PagePagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-project @mentions inbox in the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardMentionsController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly IssueMentionRepository $mentionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/dashboard/mentions', name: 'dashboard_mentions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = $this->resolveProjectFilter($accessible, $request->query->getString('project'));
        $projects = null !== $projectFilter ? [$projectFilter] : $accessible;
        $unreadOnly = $request->query->getBoolean('unread');

        $total = $this->mentionRepository->countInboxForUser($user, $projects, $unreadOnly);
        $pagination = PagePagination::fromRequest($request, $total);
        $mentions = $this->mentionRepository->findInboxForUser(
            $user,
            $projects,
            $unreadOnly,
            $pagination['per_page'],
            $pagination['offset'],
        );

        return $this->render('dashboard/mentions.html.twig', [
            'mentions' => $mentions,
            'projects' => $accessible,
            'filters' => [
                'project' => $projectFilter?->getUuid() ?? '',
                'unread' => $unreadOnly ? '1' : '',
                'per_page' => $pagination['per_page'],
            ],
            'pagination' => $pagination,
            'unread_count' => $this->mentionRepository->countInboxForUser($user, $accessible, true),
        ]);
    }

    #[Route('/dashboard/mentions/read-all', name: 'dashboard_mentions_read_all', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('mention_read_all', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $this->mentionRepository->markAllReadForUser($user, $accessible);
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_mentions');
    }

    #[Route('/dashboard/mentions/{id}/read', name: 'dashboard_mentions_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('mention_read_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $mention = $this->mentionRepository->findOneForUser($user, $id);
        if (!$mention instanceof IssueMention) {
            throw $this->createNotFoundException();
        }

        $mention->markRead();
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_mentions', $this->redirectQuery($request));
    }

    /**
     * @param list<Project> $accessible
     */
    private function resolveProjectFilter(array $accessible, string $uuid): ?Project
    {
        if ('' === $uuid) {
            return null;
        }
        foreach ($accessible as $project) {
            if ($project->getUuid() === $uuid) {
                return $project;
            }
        }

        return null;
    }

    /**
     * @return array<string, scalar>
     */
    private function redirectQuery(Request $request): array
    {
        $query = [];
        foreach (['project', 'unread', 'page', 'per_page'] as $key) {
            $value = $request->request->getString($key);
            if ('' === $value) {
                $value = $request->query->getString($key);
            }
            if ('' !== $value) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}

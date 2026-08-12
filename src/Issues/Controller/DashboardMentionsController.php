<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueMention;
use App\Issues\Form\DashboardMentionsFilterType;
use App\Issues\Form\MentionsMarkAllReadType;
use App\Issues\Form\MentionsMarkReadType;
use App\Issues\Repository\IssueMentionRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectFilter;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Pagination\PagePagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/mentions', name: 'dashboard_mentions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));
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

        $unreadCount = $this->mentionRepository->countInboxForUser($user, $accessible, true);
        $filters = [
            'project' => $projectFilter?->getUuid() ?? '',
            'unread' => $unreadOnly ? '1' : '',
            'page' => 1,
            'per_page' => $pagination['per_page'],
        ];
        $redirectQuery = array_filter(
            [
                'project' => $filters['project'],
                'unread' => $filters['unread'],
                'per_page' => (string) $filters['per_page'],
            ],
            static fn (string $value): bool => '' !== $value,
        );

        $markReadForms = [];
        foreach ($mentions as $mention) {
            if (!$mention->isUnread()) {
                continue;
            }
            $markReadForms[$mention->getId()] = $this->createForm(MentionsMarkReadType::class, null, [
                'csrf_token_id' => 'mention_read_'.$mention->getId(),
                'redirect_query' => $redirectQuery,
            ])->createView();
        }

        $projectChoices = [];
        foreach ($accessible as $project) {
            $projectChoices[$project->getName()] = $project->getUuid();
        }

        return $this->render('dashboard/mentions.html.twig', [
            'mentions' => $mentions,
            'projects' => $accessible,
            'filters' => $filters,
            'filterForm' => $this->getFilterFormFactory->create(DashboardMentionsFilterType::class, [
                'project' => $filters['project'],
                'unread' => '1' === $filters['unread'],
                'page' => 1,
                'per_page' => $pagination['per_page'],
            ], [
                'action' => $this->generateUrl('dashboard_mentions'),
                'project_choices' => $projectChoices,
            ])->createView(),
            'pagination' => $pagination,
            'unread_count' => $unreadCount,
            'markAllReadForm' => $unreadCount > 0
                ? $this->createForm(MentionsMarkAllReadType::class)
                : null,
            'markReadForms' => $markReadForms,
        ]);
    }

    #[Route('/dashboard/mentions/read-all', name: 'dashboard_mentions_read_all', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(MentionsMarkAllReadType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
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

        $form = $this->createForm(MentionsMarkReadType::class, null, [
            'csrf_token_id' => 'mention_read_'.$id,
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $mention = $this->mentionRepository->findOneForUser($user, $id);
        if (!$mention instanceof IssueMention) {
            throw $this->createNotFoundException();
        }

        $mention->markRead();
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_mentions', $this->redirectQuery($form));
    }

    /**
     * @return array<string, scalar>
     */
    private function redirectQuery(FormInterface $form): array
    {
        $query = [];
        foreach (['project', 'unread', 'per_page'] as $key) {
            if (!$form->has($key)) {
                continue;
            }
            $value = (string) ($form->get($key)->getData() ?? '');
            if ('' !== $value) {
                $query[$key] = $value;
            }
        }

        return $query;
    }
}

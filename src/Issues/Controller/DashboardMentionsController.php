<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueMention;
use App\Issues\Form\DashboardMentionsFilterType;
use App\Issues\Form\MentionsMarkAllReadType;
use App\Issues\Form\MentionsMarkReadType;
use App\Issues\Repository\IssueMentionRepository;
use App\Issues\Service\DashboardMentionsFilterResolver;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Pagination\PagePagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    use RequiresValidFormTrait;

    public function __construct(
        private readonly IssueMentionRepository $mentionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DashboardMentionsFilterResolver $filterResolver,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/mentions', name: 'dashboard_mentions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filters = $this->filterResolver->resolve($user, $request);

        $total = $this->mentionRepository->countInboxForUser($user, $filters->selectedProjects, $filters->unreadOnly);
        $pagination = PagePagination::fromRequest($request, $total);
        $mentions = $this->mentionRepository->findInboxForUser(
            $user,
            $filters->selectedProjects,
            $filters->unreadOnly,
            $pagination['per_page'],
            $pagination['offset'],
        );

        $unreadCount = $this->mentionRepository->countInboxForUser($user, $filters->accessibleProjects, true);
        $formData = $filters->formData($pagination['per_page']);
        $redirectQuery = $filters->redirectQuery($pagination['per_page']);

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

        return $this->render('dashboard/mentions.html.twig', [
            'mentions' => $mentions,
            'projects' => $filters->accessibleProjects,
            'filters' => $formData,
            'filterForm' => $this->getFilterFormFactory->create(DashboardMentionsFilterType::class, $formData, [
                'action' => $this->generateUrl('dashboard_mentions'),
                'project_choices' => $filters->projectChoices(),
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
    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(MentionsMarkAllReadType::class);
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        $filters = $this->filterResolver->resolve($user, $request);
        $this->mentionRepository->markAllReadForUser($user, $filters->accessibleProjects);
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_mentions');
    }

    #[Route('/dashboard/mentions/{id}/read', name: 'dashboard_mentions_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(MentionsMarkReadType::class, null, [
            'csrf_token_id' => 'mention_read_'.$id,
        ]);
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        $mention = $this->mentionRepository->findOneForUser($user, $id);
        if (!$mention instanceof IssueMention) {
            throw $this->createNotFoundException();
        }

        $mention->markRead();
        $this->entityManager->flush();

        return $this->redirectToRoute('dashboard_mentions', $this->redirectQuery($form));
    }

    /**
     * @param FormInterface<mixed> $form
     *
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

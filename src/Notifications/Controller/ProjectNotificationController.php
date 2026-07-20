<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Form\NotificationDestinationFormType;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project notification destination CRUD and send-test actions.
 */
#[IsGranted('ROLE_USER')]
final class ProjectNotificationController extends AbstractController
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/notifications/new', name: 'project_notification_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Alerts');
        $form = $this->createForm(NotificationDestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addNotificationDestination($destination);
            $this->entityManager->persist($destination);
            $this->entityManager->flush();
            $this->addFlash('success', 'notifications.flash.created');

            return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => false,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/edit', name: 'project_notification_edit', requirements: ['projectId' => '\d+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $projectId, NotificationDestination $destination, Request $request): Response
    {
        $project = $destination->getProject();
        if (!$project instanceof Project || $project->getId() !== $projectId) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $form = $this->createForm(NotificationDestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'notifications.flash.updated');

            return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => true,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/toggle', name: 'project_notification_toggle', requirements: ['projectId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function toggle(int $projectId, NotificationDestination $destination, Request $request): Response
    {
        $project = $this->requireManagedDestination($projectId, $destination);
        if (!$this->isCsrfTokenValid('notif_toggle_'.$destination->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $destination->setEnabled(!$destination->isEnabled());
        $this->entityManager->flush();
        $this->addFlash('success', $destination->isEnabled() ? 'notifications.flash.enabled' : 'notifications.flash.disabled');

        return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/delete', name: 'project_notification_delete', requirements: ['projectId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function delete(int $projectId, NotificationDestination $destination, Request $request): Response
    {
        $project = $this->requireManagedDestination($projectId, $destination);
        if (!$this->isCsrfTokenValid('notif_delete_'.$destination->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->entityManager->remove($destination);
        $this->entityManager->flush();
        $this->addFlash('success', 'notifications.flash.deleted');

        return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/test', name: 'project_notification_test', requirements: ['projectId' => '\d+', 'id' => '\d+'], methods: ['POST'])]
    public function test(int $projectId, NotificationDestination $destination, Request $request): Response
    {
        $project = $this->requireManagedDestination($projectId, $destination);
        if (!$this->isCsrfTokenValid('notif_test_'.$destination->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $id = $destination->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }

        $this->notificationDispatcher->dispatchTest($project, $id, $destination->getLabel());
        $this->addFlash('success', 'notifications.flash.test_queued');

        return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
    }

    private function requireManagedDestination(int $projectId, NotificationDestination $destination): Project
    {
        $project = $destination->getProject();
        if (!$project instanceof Project || $project->getId() !== $projectId) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        return $project;
    }
}

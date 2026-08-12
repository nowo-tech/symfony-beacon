<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Form\NotificationDestinationFormType;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectChildEntityGuard;
use App\Shared\Form\CsrfOnlyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project notification destination CRUD and send-test actions.
 */
#[IsGranted('ROLE_USER')]
final class ProjectNotificationController extends AbstractController
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectChildEntityGuard $childEntityGuard,
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/notifications/new', name: 'project_notification_new', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function new(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::NOTIFICATIONS_MANAGE);

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

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => false,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/edit', name: 'project_notification_edit', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): Response {
        $project = $destination->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::NOTIFICATIONS_MANAGE);

        $form = $this->createForm(NotificationDestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'notifications.flash.updated');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => true,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/toggle', name: 'project_notification_toggle', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function toggle(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedDestination($projectId, $destination);
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'notif_toggle_'.$destination->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $destination->setEnabled(!$destination->isEnabled());
        $this->entityManager->flush();
        $this->addFlash('success', $destination->isEnabled() ? 'notifications.flash.enabled' : 'notifications.flash.disabled');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/resume', name: 'project_notification_resume', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function resume(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedDestination($projectId, $destination);
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'notif_resume_'.$destination->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $destination->resumeCircuit();
        $this->entityManager->flush();
        $this->addFlash('success', 'notifications.flash.circuit_resumed');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/delete', name: 'project_notification_delete', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedDestination($projectId, $destination);
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'notif_delete_'.$destination->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $this->entityManager->remove($destination);
        $this->entityManager->flush();
        $this->addFlash('success', 'notifications.flash.deleted');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/test', name: 'project_notification_test', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function test(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedDestination($projectId, $destination);
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'notif_test_'.$destination->getId(),
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException();
        }

        $id = $destination->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }

        $this->notificationDispatcher->dispatchTest($project, $destination);
        $this->addFlash('success', 'notifications.flash.test_queued');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{id}/notifications/help', name: 'project_notification_help', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function help(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireAccess($project, $user);

        return $this->render('notifications/help.html.twig', [
            'project' => $project,
        ]);
    }

    private function requireManagedDestination(string $projectId, NotificationDestination $destination): Project
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->childEntityGuard->requireManagedChild(
            $projectId,
            $destination->getProject(),
            $user,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Form\NotificationDestinationFormType;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Security\ProjectPermission;
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
        private readonly NotificationDispatcher $notificationDispatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/notifications/new', name: 'project_notification_new', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function new(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
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

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => false,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/edit', name: 'project_notification_edit', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function edit(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): Response {
        $this->assertDestinationBelongsToProject($project, $destination);

        $form = $this->createForm(NotificationDestinationFormType::class, $destination);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'notifications.flash.updated');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
        }

        return $this->render('notifications/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'destination' => $destination,
            'is_edit' => true,
        ]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/toggle', name: 'project_notification_toggle', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function toggle(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $this->assertDestinationBelongsToProject($project, $destination);
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

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/resume', name: 'project_notification_resume', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function resume(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $this->assertDestinationBelongsToProject($project, $destination);
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

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/delete', name: 'project_notification_delete', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function delete(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $this->assertDestinationBelongsToProject($project, $destination);
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

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
    }

    #[Route('/projects/{projectId}/notifications/{id}/test', name: 'project_notification_test', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::NOTIFICATIONS_MANAGE, 'project')]
    public function test(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        NotificationDestination $destination,
        Request $request,
    ): RedirectResponse {
        $this->assertDestinationBelongsToProject($project, $destination);
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

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Alerts->value]);
    }

    #[Route('/projects/{id}/notifications/help', name: 'project_notification_help', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(ProjectPermission::VIEW, 'project')]
    public function help(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): Response {
        return $this->render('notifications/help.html.twig', [
            'project' => $project,
        ]);
    }

    private function assertDestinationBelongsToProject(Project $project, NotificationDestination $destination): void
    {
        if ($destination->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
    }
}

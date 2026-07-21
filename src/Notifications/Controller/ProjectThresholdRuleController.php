<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Form\ProjectThresholdRuleType;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use App\Shared\ProjectRole;
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
 * Project threshold rule CRUD and toggle actions.
 */
#[IsGranted('ROLE_USER')]
final class ProjectThresholdRuleController extends AbstractController
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/threshold-rules/new', name: 'project_threshold_rule_new', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function new(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $rule = new ProjectThresholdRule();
        $rule->setProject($project);

        $form = $this->createForm(ProjectThresholdRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->addThresholdRule($rule);
            $this->entityManager->persist($rule);
            $this->entityManager->flush();
            $this->addFlash('success', 'thresholds.flash.created');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        return $this->render('notifications/threshold_rule_form.html.twig', [
            'project' => $project,
            'form' => $form,
            'rule' => $rule,
            'is_edit' => false,
        ]);
    }

    #[Route('/projects/{projectId}/threshold-rules/{id}/edit', name: 'project_threshold_rule_edit', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        ProjectThresholdRule $rule,
        Request $request,
    ): Response {
        $project = $this->requireManagedRule($projectId, $rule);

        $form = $this->createForm(ProjectThresholdRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'thresholds.flash.updated');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        return $this->render('notifications/threshold_rule_form.html.twig', [
            'project' => $project,
            'form' => $form,
            'rule' => $rule,
            'is_edit' => true,
        ]);
    }

    #[Route('/projects/{projectId}/threshold-rules/{id}/toggle', name: 'project_threshold_rule_toggle', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function toggle(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        ProjectThresholdRule $rule,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedRule($projectId, $rule);
        if (!$this->isCsrfTokenValid('threshold_toggle_'.$rule->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $rule->setEnabled(!$rule->isEnabled());
        $this->entityManager->flush();
        $this->addFlash('success', $rule->isEnabled() ? 'thresholds.flash.enabled' : 'thresholds.flash.disabled');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route('/projects/{projectId}/threshold-rules/{id}/delete', name: 'project_threshold_rule_delete', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        ProjectThresholdRule $rule,
        Request $request,
    ): RedirectResponse {
        $project = $this->requireManagedRule($projectId, $rule);
        if (!$this->isCsrfTokenValid('threshold_delete_'.$rule->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->entityManager->remove($rule);
        $this->entityManager->flush();
        $this->addFlash('success', 'thresholds.flash.deleted');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    private function requireManagedRule(string $projectId, ProjectThresholdRule $rule): Project
    {
        $project = $rule->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        return $project;
    }
}

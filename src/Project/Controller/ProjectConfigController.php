<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectConfigPortability;
use App\Shared\Http\JsonUploadReader;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project Settings export/import of config bundle (089) — no user creation.
 */
#[IsGranted('ROLE_USER')]
final class ProjectConfigController extends AbstractController
{
    public function __construct(
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectConfigPortability $portability,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/config/export', name: 'project_config_export', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function export(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SETTINGS_MANAGE);

        $payload = $this->portability->export([$project]);
        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectConfigExported,
            $user,
            $user,
            [
                'schema' => ProjectConfigPortability::SCHEMA,
                'count' => 1,
                'project_uuid' => $project->getUuid(),
                'scope' => 'panel',
            ],
        );

        $safeCode = preg_replace('/[^a-z0-9\-]+/', '-', $project->getCode() ?: $project->getSlug()) ?: 'project';
        $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n";

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="beacon-project-'.$safeCode.'.json"',
        ]);
    }

    #[Route('/projects/{id}/config/import', name: 'project_config_import', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function import(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SETTINGS_MANAGE);

        if (!$this->isCsrfTokenValid('project_config_import_'.$project->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'flash.project.config_invalid_csrf');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $file = $request->files->get('bundle');
        try {
            $payload = JsonUploadReader::decodeObject($file instanceof UploadedFile ? $file : null);
            $result = $this->portability->importPanel($payload, $project, $user);
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'missing_file' => 'flash.project.config_missing_file',
                'too_large' => 'flash.project.config_file_too_large',
                'code_mismatch', 'project_not_in_bundle' => 'flash.project.config_code_mismatch',
                default => 'flash.project.config_import_failed',
            };
            $this->addFlash('error', $key);

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectConfigImported,
            $user,
            $user,
            [
                'schema' => ProjectConfigPortability::SCHEMA,
                'scope' => 'panel',
                'project_uuid' => $project->getUuid(),
                'memberships_applied' => $result['memberships_applied'],
                'memberships_skipped' => \count($result['memberships_skipped']),
            ],
        );

        $this->addFlash('success', 'flash.project.config_imported_panel');
        if ([] !== $result['memberships_skipped']) {
            $this->addFlash(
                'warning',
                'flash.project.config_users_skipped',
            );
        }
        foreach (\array_slice($result['warnings'], 0, 5) as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }
}

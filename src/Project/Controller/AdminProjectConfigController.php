<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
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
 * Administration export/import of project config bundles (089).
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProjectConfigController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectConfigPortability $portability,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/admin/projects/export', name: 'admin_projects_export', methods: ['GET'])]
    public function exportAll(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $ids = $request->query->all('ids');
        if ([] !== $ids) {
            $uuids = [];
            foreach ($ids as $uuid) {
                if (\is_string($uuid) && '' !== $uuid) {
                    $uuids[] = $uuid;
                }
            }
            $projects = $this->projectRepository->findByUuids($uuids);
        } else {
            $projects = $this->projectRepository->findAllOrdered();
        }

        $payload = $this->portability->export($projects);
        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectConfigExported,
            $user,
            $user,
            [
                'schema' => ProjectConfigPortability::SCHEMA,
                'count' => \count($projects),
                'scope' => 'admin',
            ],
        );

        return $this->jsonDownload($payload, 'beacon-projects.json');
    }

    #[Route('/admin/projects/{id}/export', name: 'admin_project_export', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function exportOne(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->portability->export([$project]);
        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectConfigExported,
            $user,
            $user,
            [
                'schema' => ProjectConfigPortability::SCHEMA,
                'count' => 1,
                'project_uuid' => $project->getUuid(),
                'scope' => 'admin',
            ],
        );

        $safeCode = preg_replace('/[^a-z0-9\-]+/', '-', $project->getCode() ?: $project->getSlug()) ?: 'project';

        return $this->jsonDownload($payload, 'beacon-project-'.$safeCode.'.json');
    }

    #[Route('/admin/projects/import', name: 'admin_projects_import', methods: ['POST'])]
    public function import(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('admin_projects_import', $request->request->getString('_token'))) {
            $this->addFlash('error', 'flash.project.config_invalid_csrf');

            return $this->redirectToRoute('admin_projects');
        }

        $file = $request->files->get('bundle');
        try {
            $payload = JsonUploadReader::decodeObject($file instanceof UploadedFile ? $file : null);
            $result = $this->portability->importAdmin($payload, $user);
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'missing_file' => 'flash.project.config_missing_file',
                'too_large' => 'flash.project.config_file_too_large',
                default => 'flash.project.config_import_failed',
            };
            $this->addFlash('error', $key);

            return $this->redirectToRoute('admin_projects');
        }

        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectConfigImported,
            $user,
            $user,
            [
                'schema' => ProjectConfigPortability::SCHEMA,
                'scope' => 'admin',
                'projects_upserted' => $result['projects_upserted'],
                'users_created' => $result['users_created'],
                'memberships_applied' => $result['memberships_applied'],
            ],
        );

        $this->addFlash('success', 'flash.project.config_imported_admin');
        if ([] !== $result['warnings']) {
            foreach (\array_slice($result['warnings'], 0, 5) as $warning) {
                $this->addFlash('warning', $warning);
            }
        }

        return $this->redirectToRoute('admin_projects');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonDownload(array $payload, string $filename): Response
    {
        $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n";

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}

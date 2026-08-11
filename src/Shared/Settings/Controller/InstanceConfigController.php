<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Shared\Http\JsonUploadReader;
use App\Shared\Settings\Service\InstanceConfigPortability;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin export/import of allowlisted non-secret instance settings (044).
 */
#[IsGranted('ROLE_ADMIN')]
final class InstanceConfigController extends AbstractController
{
    public function __construct(
        private readonly InstanceConfigPortability $portability,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/admin/instance-config', name: 'admin_instance_config', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/instance_config.html.twig');
    }

    #[Route('/admin/instance-config/export', name: 'admin_instance_config_export', methods: ['GET'])]
    public function export(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $payload = $this->portability->export();
        $this->userActionRecorder->recordAndFlush(
            UserActionType::InstanceConfigExported,
            $user,
            $user,
            ['schema' => InstanceConfigPortability::SCHEMA, 'version' => InstanceConfigPortability::VERSION],
        );

        $json = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES)."\n";

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="beacon-instance-config.json"',
        ]);
    }

    #[Route('/admin/instance-config/import', name: 'admin_instance_config_import', methods: ['POST'])]
    public function import(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('instance_config_import', $request->request->getString('_token'))) {
            $this->addFlash('error', 'settings.instance_config.invalid_csrf');

            return $this->redirectToRoute('admin_instance_config');
        }

        $file = $request->files->get('config');
        try {
            $payload = JsonUploadReader::decodeObject($file instanceof UploadedFile ? $file : null);
            $applied = $this->portability->import($payload);
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'missing_file' => 'settings.instance_config.missing_file',
                'too_large' => 'settings.instance_config.file_too_large',
                default => 'settings.instance_config.import_failed',
            };
            $this->addFlash('error', $key);

            return $this->redirectToRoute('admin_instance_config');
        }

        $this->userActionRecorder->recordAndFlush(
            UserActionType::InstanceConfigImported,
            $user,
            $user,
            [
                'schema' => InstanceConfigPortability::SCHEMA,
                'version' => InstanceConfigPortability::VERSION,
                'applied' => implode(',', $applied),
            ],
        );
        $this->addFlash('success', 'settings.instance_config.imported');

        return $this->redirectToRoute('admin_instance_config');
    }
}

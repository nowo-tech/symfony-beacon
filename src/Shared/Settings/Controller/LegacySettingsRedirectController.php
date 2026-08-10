<?php

declare(strict_types=1);

namespace App\Shared\Settings\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Permanent redirects from legacy /settings/* admin URLs to /admin/*.
 */
final class LegacySettingsRedirectController extends AbstractController
{
    #[Route('/settings/appearance', name: 'legacy_settings_appearance', methods: ['GET'])]
    public function appearance(): RedirectResponse
    {
        return $this->redirectToRoute('admin_appearance', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/settings/appearance/{section}',
        name: 'legacy_settings_appearance_section',
        requirements: ['section' => 'themes|brand|layout|colors'],
        defaults: ['sub' => null],
        methods: ['GET', 'POST'],
        priority: 10,
    )]
    #[Route(
        '/settings/appearance/{section}/{sub}',
        name: 'legacy_settings_appearance_section',
        requirements: [
            'section' => 'themes|brand|layout|colors',
            'sub' => 'accents|status|surfaces',
        ],
        methods: ['GET', 'POST'],
    )]
    public function appearanceSection(string $section, ?string $sub = null): RedirectResponse
    {
        $params = ['section' => $section];
        if (null !== $sub && '' !== $sub) {
            $params['sub'] = $sub;
        }

        return $this->redirectToRoute('admin_appearance_section', $params, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/mailer', name: 'legacy_settings_mailer', methods: ['GET', 'POST'])]
    public function mailer(): RedirectResponse
    {
        return $this->redirectToRoute('admin_mailer', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/mailer/test', name: 'legacy_settings_mailer_test', methods: ['POST'])]
    public function mailerTest(): RedirectResponse
    {
        // POST bodies are not replayed on 301; send operators to the new form.
        return $this->redirectToRoute('admin_mailer', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/mercure', name: 'legacy_settings_mercure', methods: ['GET', 'POST'])]
    public function mercure(): RedirectResponse
    {
        return $this->redirectToRoute('admin_mercure', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/ops-defaults', name: 'legacy_settings_ops_defaults', methods: ['GET'])]
    public function opsDefaults(): RedirectResponse
    {
        return $this->redirectToRoute('admin_ops_defaults', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/settings/ops-defaults/{section}',
        name: 'legacy_settings_ops_defaults_section',
        requirements: ['section' => 'governance|ingest|metrics|inbound|notifications'],
        methods: ['GET', 'POST'],
    )]
    public function opsDefaultsSection(string $section): RedirectResponse
    {
        return $this->redirectToRoute('admin_ops_defaults_section', [
            'section' => $section,
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/instance-config', name: 'legacy_settings_instance_config', methods: ['GET'])]
    public function instanceConfig(): RedirectResponse
    {
        return $this->redirectToRoute('admin_instance_config', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/instance-config/export', name: 'legacy_settings_instance_config_export', methods: ['GET'])]
    public function instanceConfigExport(): RedirectResponse
    {
        return $this->redirectToRoute('admin_instance_config_export', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/settings/instance-config/import', name: 'legacy_settings_instance_config_import', methods: ['POST'])]
    public function instanceConfigImport(): RedirectResponse
    {
        // POST bodies are not replayed on 301; send operators to the new form.
        return $this->redirectToRoute('admin_instance_config', status: Response::HTTP_MOVED_PERMANENTLY);
    }
}

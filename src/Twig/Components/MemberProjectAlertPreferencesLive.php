<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Identity\Entity\User;
use App\Identity\Form\MemberProjectAlertPreferencesType;
use App\Notifications\Enum\MemberAlertEvent;
use App\Notifications\Service\MemberAlertPreferenceManager;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Per-project member-alert preferences with Live cascading visibility.
 */
#[AsLiveComponent]
#[IsGranted('ROLE_USER')]
final class MemberProjectAlertPreferencesLive extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $projectUuid = '';

    #[LiveProp]
    public string $projectName = '';

    /**
     * @var array{
     *     enabled?: bool,
     *     resetOverrides?: bool,
     *     events?: array<string, array{enabled: bool, involved: bool}>
     * }
     */
    #[LiveProp]
    public array $initialFormData = [];

    /** Where to redirect after save: `account` or `project`. */
    #[LiveProp]
    public string $returnTo = 'account';

    /** When true, render cancel/close controls for confirm-dialog embedding. */
    #[LiveProp]
    public bool $showModalActions = false;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->createNamed(
            MemberProjectAlertPreferencesType::formNameForUuid($this->projectUuid),
            MemberProjectAlertPreferencesType::class,
            $this->initialFormData,
            [
                // Live endpoint CSRF — see MemberAlertPreferencesLive.
                'csrf_protection' => false,
            ],
        );
    }

    public function isProjectEnabled(): bool
    {
        return (bool) $this->getForm()->get('enabled')->getData();
    }

    #[LiveAction]
    public function save(
        ProjectRepository $projectRepository,
        ProjectAccessService $projectAccess,
        MemberAlertPreferenceManager $memberAlertPreferenceManager,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $this->submitForm();

        $project = null;
        foreach ($projectRepository->findByUuids([$this->projectUuid]) as $candidate) {
            if ($candidate->getUuid() === $this->projectUuid) {
                $project = $candidate;
                break;
            }
        }
        if (!$project instanceof Project) {
            throw $this->createNotFoundException('Project not found.');
        }

        /** @var User $user */
        $user = $this->getUser();
        // Own prefs for any accessible project (viewer+); not Settings admin surface.
        $projectAccess->requireAccess($project, $user);

        /** @var array<string, mixed> $data */
        $data = $this->getForm()->getData();
        /** @var array<string, mixed> $projectEvents */
        $projectEvents = \is_array($data['events'] ?? null) ? $data['events'] : [];
        $memberAlertPreferenceManager->saveProjectPreferences($user, [[
            'project' => $project,
            'enabled' => \array_key_exists('enabled', $data) ? (bool) $data['enabled'] : true,
            'resetOverrides' => (bool) ($data['resetOverrides'] ?? false),
            'events' => MemberAlertEvent::mapEventsFromFormKeys($projectEvents),
        ]]);
        $entityManager->flush();
        $this->addFlash('success', 'flash.preferences.member_alerts_project_saved');

        if ('project' === $this->returnTo) {
            return $this->redirectToRoute('project_settings', [
                'id' => $this->projectUuid,
                '_fragment' => 'member-alerts',
            ]);
        }

        return $this->redirectToRoute('account_display_notifications');
    }
}

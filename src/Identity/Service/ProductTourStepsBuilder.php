<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Tour\ProductTourContext;
use App\Identity\Tour\ProductTourPage;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use App\Shared\ProjectRole;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds localized, permission-aware driver.js steps and Twig mount variables.
 *
 * @phpstan-type TourStep array{element?: string, popover: array{title: string, description: string, side?: string, align?: string}}
 */
final class ProductTourStepsBuilder
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ProjectAccessService $projectAccess,
        private readonly InstanceSettingsRepository $instanceSettingsRepository,
    ) {
    }

    public function contextForDashboard(): ProductTourContext
    {
        return new ProductTourContext(
            page: ProductTourPage::Dashboard,
            isInstanceAdmin: $this->security->isGranted('ROLE_ADMIN'),
            canCreateProject: $this->security->isGranted('ROLE_USER'),
        );
    }

    public function contextForProjectIssues(Project $project, User $user): ProductTourContext
    {
        $access = $this->projectAccess->resolveAccess($project, $user);
        if (null === $access) {
            $access = $this->projectAccess->requireMembership($project, $user);
        }

        return new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: $this->security->isGranted('ROLE_ADMIN'),
            canCreateProject: $this->security->isGranted('ROLE_USER'),
            projectRole: $access->role,
        );
    }

    public function contextForAdmin(): ProductTourContext
    {
        return new ProductTourContext(
            page: ProductTourPage::Admin,
            isInstanceAdmin: true,
            canCreateProject: true,
        );
    }

    /**
     * @return array{
     *     autoStartProductTour: bool,
     *     forceProductTour: bool,
     *     productTourPage: string,
     *     productTourSteps: list<TourStep>,
     *     productTourLabels: array<string, string>
     * }
     */
    public function twigVars(ProductTourContext $context, User $user, Request $request): array
    {
        $force = $request->query->getBoolean('tour');
        $setupCompleted = $this->instanceSettingsRepository->getOrCreate()->isSetupCompleted();
        $autoStart = $force
            || (
                $setupCompleted
                && !$user->isProductTourSeen()
                && !$user->hasSeenTourPage($context->page->value)
            );

        return [
            'autoStartProductTour' => $autoStart,
            'forceProductTour' => $force,
            'productTourPage' => $context->page->value,
            'productTourSteps' => $this->build($context),
            'productTourLabels' => [
                'next' => $this->translator->trans('tour.controls.next'),
                'previous' => $this->translator->trans('tour.controls.previous'),
                'done' => $this->translator->trans('tour.controls.done'),
                'close' => $this->translator->trans('tour.controls.close'),
                'progress' => $this->translator->trans('tour.controls.progress'),
            ],
        ];
    }

    /**
     * @return list<TourStep>
     */
    public function build(ProductTourContext $context): array
    {
        return match ($context->page) {
            ProductTourPage::Dashboard => $this->buildDashboard($context),
            ProductTourPage::ProjectIssues => $this->buildProjectIssues($context),
            ProductTourPage::Admin => $this->buildAdmin(),
        };
    }

    /**
     * @return list<TourStep>
     */
    private function buildDashboard(ProductTourContext $context): array
    {
        $steps = [
            $this->step(null, 'tour.steps.dashboard.welcome'),
            $this->step('[data-tour="sidebar"]', 'tour.steps.dashboard.sidebar', 'right', 'start'),
            $this->step('[data-tour="projects"]', 'tour.steps.dashboard.projects', 'bottom', 'start'),
        ];

        if ($context->canCreateProject) {
            $steps[] = $this->step('[data-tour="new-project"]', 'tour.steps.dashboard.new_project', 'bottom', 'end');
        }

        $steps[] = $this->step('[data-tour="user-menu"]', 'tour.steps.dashboard.user_menu', 'bottom', 'end');

        if ($context->isInstanceAdmin) {
            $steps[] = $this->step('[data-tour="admin-link"]', 'tour.steps.dashboard.admin', 'left', 'start');
        }

        return $steps;
    }

    /**
     * @return list<TourStep>
     */
    private function buildProjectIssues(ProductTourContext $context): array
    {
        $roleKey = $context->projectRole instanceof ProjectRole
            ? $context->projectRole->value
            : 'viewer';
        $steps = [
            $this->step(null, 'tour.steps.project.welcome', descriptionParams: [
                '%role%' => $this->translator->trans('tour.role.'.$roleKey),
            ]),
            $this->step('[data-tour="project-nav"]', 'tour.steps.project.nav', 'bottom', 'start'),
            $this->step('[data-tour="issue-filters"]', 'tour.steps.project.filters', 'bottom', 'start'),
        ];

        if ($context->canTriageIssues()) {
            $steps[] = $this->step('[data-tour="issue-saved-views"]', 'tour.steps.project.saved_views', 'bottom', 'start');
        }

        $steps[] = $this->step('[data-tour="issue-list"]', 'tour.steps.project.list', 'top', 'start');

        if ($context->canManageProject()) {
            $steps[] = $this->step('[data-tour="project-settings"]', 'tour.steps.project.settings', 'bottom', 'end');
        } elseif ($context->projectRole?->canTriageIssues()) {
            $steps[] = $this->step('[data-tour="project-settings"]', 'tour.steps.project.settings_member', 'bottom', 'end');
        }

        return $steps;
    }

    /**
     * @return list<TourStep>
     */
    private function buildAdmin(): array
    {
        return [
            $this->step(null, 'tour.steps.admin.welcome'),
            $this->step('[data-tour="admin-users"]', 'tour.steps.admin.users', 'bottom', 'start'),
            $this->step('[data-tour="admin-projects"]', 'tour.steps.admin.projects', 'bottom', 'start'),
            $this->step('[data-tour="admin-mailer"]', 'tour.steps.admin.mailer', 'bottom', 'start'),
            $this->step('[data-tour="admin-setup"]', 'tour.steps.admin.setup', 'bottom', 'start'),
        ];
    }

    /**
     * @param array<string, string|int|float> $descriptionParams
     *
     * @return TourStep
     */
    private function step(
        ?string $element,
        string $keyPrefix,
        ?string $side = null,
        ?string $align = null,
        array $descriptionParams = [],
    ): array {
        $popover = [
            'title' => $this->translator->trans($keyPrefix.'.title'),
            'description' => $this->translator->trans($keyPrefix.'.description', $descriptionParams),
        ];
        if (null !== $side) {
            $popover['side'] = $side;
        }
        if (null !== $align) {
            $popover['align'] = $align;
        }

        if (null === $element) {
            return ['popover' => $popover];
        }

        return [
            'element' => $element,
            'popover' => $popover,
        ];
    }
}

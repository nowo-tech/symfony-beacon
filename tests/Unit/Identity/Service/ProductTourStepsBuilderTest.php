<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Service;

use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Identity\Tour\ProductTourContext;
use App\Identity\Tour\ProductTourPage;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProductTourStepsBuilderTest extends TestCase
{
    private Security&Stub $security;
    private ProjectMembershipRepository&Stub $membershipRepository;
    private InstanceSettingsRepository&Stub $settingsRepository;
    private ProductTourStepsBuilder $builder;

    protected function setUp(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => [] === $params
                ? $id
                : $id.'|'.json_encode($params, \JSON_THROW_ON_ERROR),
        );

        $this->security = $this->createStub(Security::class);
        $this->membershipRepository = $this->createStub(ProjectMembershipRepository::class);
        $this->settingsRepository = $this->createStub(InstanceSettingsRepository::class);

        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $access = new ProjectAccessService(
            $this->membershipRepository,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $this->builder = new ProductTourStepsBuilder(
            $translator,
            $this->security,
            $access,
            $this->settingsRepository,
        );
    }

    public function testDashboardStepsIncludeAdminAndCreateWhenGranted(): void
    {
        $steps = $this->builder->build(new ProductTourContext(
            page: ProductTourPage::Dashboard,
            isInstanceAdmin: true,
            canCreateProject: true,
        ));

        $elements = array_column($steps, 'element');
        self::assertContains('[data-tour="new-project"]', $elements);
        self::assertContains('[data-tour="admin-link"]', $elements);
        self::assertSame('tour.steps.dashboard.welcome.title', $steps[0]['popover']['title']);
    }

    public function testDashboardStepsOmitPrivilegedTargetsForMember(): void
    {
        $steps = $this->builder->build(new ProductTourContext(
            page: ProductTourPage::Dashboard,
            isInstanceAdmin: false,
            canCreateProject: false,
        ));

        $elements = array_column($steps, 'element');
        self::assertNotContains('[data-tour="new-project"]', $elements);
        self::assertNotContains('[data-tour="admin-link"]', $elements);
    }

    public function testProjectIssuesStepsDependOnRole(): void
    {
        $viewer = $this->builder->build(new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: false,
            projectRole: ProjectRole::Viewer,
        ));
        $viewerElements = array_column($viewer, 'element');
        self::assertNotContains('[data-tour="issue-saved-views"]', $viewerElements);
        self::assertNotContains('[data-tour="project-settings"]', $viewerElements);

        $member = $this->builder->build(new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: false,
            projectRole: ProjectRole::Member,
        ));
        self::assertContains('[data-tour="issue-saved-views"]', array_column($member, 'element'));
        self::assertSame('tour.steps.project.settings_member.title', $this->settingsStepTitle($member));

        $admin = $this->builder->build(new ProductTourContext(
            page: ProductTourPage::ProjectIssues,
            isInstanceAdmin: false,
            canCreateProject: false,
            projectRole: ProjectRole::Admin,
        ));
        self::assertSame('tour.steps.project.settings.title', $this->settingsStepTitle($admin));
    }

    public function testAdminStepsAndContextHelpers(): void
    {
        $this->security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => \in_array($attribute, ['ROLE_ADMIN', 'ROLE_USER'], true),
        );
        $project = new Project();
        $user = new User();
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn(
            new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Full),
        );

        self::assertSame(ProductTourPage::Dashboard, $this->builder->contextForDashboard()->page);
        self::assertTrue($this->builder->contextForDashboard()->isInstanceAdmin);
        self::assertSame(ProjectRole::Full, $this->builder->contextForProjectIssues($project, $user)->projectRole);
        self::assertSame(ProductTourPage::Admin, $this->builder->contextForAdmin()->page);

        $adminSteps = $this->builder->build($this->builder->contextForAdmin());
        self::assertCount(5, $adminSteps);
        self::assertSame('[data-tour="admin-users"]', $adminSteps[1]['element']);
    }

    public function testTwigVarsForceAndAutoStart(): void
    {
        $settings = InstanceSettings::defaults();
        $settings->markSetupCompleted();
        $this->settingsRepository->method('getOrCreate')->willReturn($settings);

        $user = new User();
        $context = new ProductTourContext(ProductTourPage::Dashboard, false, true);

        $forced = $this->builder->twigVars($context, $user, Request::create('/?tour=1'));
        self::assertTrue($forced['autoStartProductTour']);
        self::assertTrue($forced['forceProductTour']);
        self::assertSame('dashboard', $forced['productTourPage']);
        self::assertSame('tour.controls.next', $forced['productTourLabels']['next']);

        $auto = $this->builder->twigVars($context, $user, Request::create('/'));
        self::assertTrue($auto['autoStartProductTour']);
        self::assertFalse($auto['forceProductTour']);

        $user->getUiPreferences()->markProductTourSeen();
        $seen = $this->builder->twigVars($context, $user, Request::create('/'));
        self::assertFalse($seen['autoStartProductTour']);
    }

    /**
     * @param list<array{element?: string, popover: array{title: string}}> $steps
     */
    private function settingsStepTitle(array $steps): string
    {
        foreach ($steps as $step) {
            if (($step['element'] ?? null) === '[data-tour="project-settings"]') {
                return $step['popover']['title'];
            }
        }

        self::fail('Missing project-settings tour step');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Twig;

use App\Shared\Rbac\RbacPermissionCategoryTranslator;
use App\Shared\Rbac\RbacPermissionTranslator;
use App\Shared\Rbac\RbacRoleTranslator;
use App\Shared\Twig\CsrfActionTwigExtension;
use App\Shared\Twig\RbacPermissionTwigExtension;
use App\Shared\Twig\RbacRoleTwigExtension;
use Nowo\FormKitBundle\Form\CsrfOnlyFormFactory;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SharedTwigExtensionsTest extends TestCase
{
    public function testRbacExtensionsExposeFilters(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $permission = new RbacPermissionTwigExtension(
            new RbacPermissionTranslator($translator),
            new RbacPermissionCategoryTranslator($translator),
        );
        $role = new RbacRoleTwigExtension(new RbacRoleTranslator($translator));

        self::assertCount(4, $permission->getFilters());
        self::assertCount(2, $role->getFilters());
        self::assertSame('rbac_permission_name', $permission->getFilters()[0]->getName());
        self::assertSame('rbac_role_name', $role->getFilters()[0]->getName());
    }

    public function testCsrfActionTwigExtensionBuildsForms(): void
    {
        $view = new FormView();
        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn($view);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects(self::exactly(2))
            ->method('createNamed')
            ->willReturn($form);
        $formFactory->expects(self::exactly(3))
            ->method('create')
            ->willReturn($form);

        $ext = new CsrfActionTwigExtension(
            new CsrfOnlyFormFactory($formFactory),
            $formFactory,
            new GetFilterFormFactory($formFactory),
        );
        self::assertCount(4, $ext->getFunctions());
        self::assertSame($view, $ext->csrfActionForm('/action', 'token-id'));
        self::assertSame($view, $ext->csrfActionForm('/action', 'token-id', fields: ['enabled' => '1']));
        self::assertSame($view, $ext->getFilterForm(\App\Setup\Form\SetupTokenGateType::class, ['token' => '']));
        self::assertSame($view, $ext->searchQueryForm('/search', 'q', ['placeholder' => 'Search']));
        self::assertSame($view, $ext->flatHiddenFields(['_section' => 'config']));
    }
}

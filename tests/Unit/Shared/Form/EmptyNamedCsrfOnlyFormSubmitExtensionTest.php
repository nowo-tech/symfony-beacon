<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use App\Shared\Form\EmptyNamedCsrfOnlyFormSubmitExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

final class EmptyNamedCsrfOnlyFormSubmitExtensionTest extends TestCase
{
    public function testBuildFormDoesNotReplaceExistingConfirmField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::once())->method('has')->with('_confirm')->willReturn(true);
        $builder->expects(self::never())->method('add');

        (new EmptyNamedCsrfOnlyFormSubmitExtension())->buildForm($builder, []);
    }
}

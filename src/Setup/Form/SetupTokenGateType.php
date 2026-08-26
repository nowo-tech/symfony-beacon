<?php

declare(strict_types=1);

namespace App\Setup\Form;

use App\Shared\Form\AbstractGetFilterType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * GET token gate for SiteBackup setup ({@code ?token=}).
 */
final class SetupTokenGateType extends AbstractGetFilterType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('token', 'text', [
                'required' => true,
                'help' => false,
                'placeholder' => false,
                'attr' => [
                    'id' => 'setup-token',
                    'class' => 'input setup-token-gate__input',
                    'autocomplete' => 'off',
                    'autocapitalize' => 'off',
                    'spellcheck' => 'false',
                    'inputmode' => 'text',
                    'aria-labelledby' => 'setup-token-heading',
                ],
                'row_attr' => [
                    'class' => 'setup-token-gate__field',
                ],
            ]);
        });
    }
}

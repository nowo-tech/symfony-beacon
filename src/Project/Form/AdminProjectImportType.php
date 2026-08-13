<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uploads an admin project portability bundle JSON file (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code admin_project_import.*}.
 */
final class AdminProjectImportType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('bundle', 'file', [
                'placeholder' => false,
                'help' => false,
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'accept' => 'application/json,.json',
                    'data-testid' => 'admin-projects-config-file',
                ],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'admin_projects_import',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_project_import';
    }
}

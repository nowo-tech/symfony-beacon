<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uploads an instance config JSON export for import (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code instance_config_import.*}.
 */
final class InstanceConfigImportType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('config', 'file', [
                'placeholder' => false,
                'help' => false,
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'accept' => 'application/json,.json',
                    'data-testid' => 'instance-config-file',
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
            'csrf_token_id' => 'instance_config_import',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'instance_config_import';
    }
}

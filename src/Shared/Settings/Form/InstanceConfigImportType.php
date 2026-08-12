<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use Override;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uploads an instance config JSON export for import.
 */
final class InstanceConfigImportType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('config', 'file', [
                'required' => true,
                'mapped' => false,
                'label' => false,
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'instance_config_import',
            'translation_domain' => 'messages',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}

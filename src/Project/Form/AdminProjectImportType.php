<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Uploads an admin project portability bundle JSON file.
 */
final class AdminProjectImportType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('bundle', 'file', [
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
            'csrf_token_id' => 'admin_projects_import',
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

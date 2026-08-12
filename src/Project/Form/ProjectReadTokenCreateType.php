<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Creates a project read token from Settings.
 */
final class ProjectReadTokenCreateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('label', [
                'required' => false,
                'label' => false,
                'constraints' => [new Length(max: 120)],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_read_token_create',
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

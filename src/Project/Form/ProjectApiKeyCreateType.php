<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Creates a project API key (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_api_key_create.*}.
 */
final class ProjectApiKeyCreateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTextField('label', [
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 120)],
                'attr' => [
                    'maxlength' => 120,
                    'autocomplete' => 'off',
                    'data-human-key-label-target' => 'label',
                ],
                'row_attr' => ['class' => 'text-sm grow min-w-[10rem]'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_key_create',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_api_key_create';
    }
}

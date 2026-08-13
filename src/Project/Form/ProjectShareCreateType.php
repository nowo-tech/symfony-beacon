<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Creates a share link (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_share_create.*}.
 */
final class ProjectShareCreateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addIntegerField('days', [
                'help' => false,
                'placeholder' => false,
                'required' => true,
                'constraints' => [new Range(min: 1, max: 30)],
                'attr' => ['min' => 1, 'max' => 30],
                'row_attr' => ['class' => 'text-sm'],
            ]);
            $this->addIntegerField('max_uses', [
                'required' => false,
                'constraints' => [new Range(min: 1, max: 10_000)],
                'attr' => ['min' => 1, 'max' => 10_000],
                'row_attr' => ['class' => 'text-sm'],
            ]);
            $this->addTextField('issue_uuid', [
                'help' => false,
                'required' => false,
                'constraints' => [new Length(max: 64)],
                'attr' => ['autocomplete' => 'off'],
                'row_attr' => ['class' => 'text-sm grow min-w-[12rem]'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_share_create',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_share_create';
    }
}

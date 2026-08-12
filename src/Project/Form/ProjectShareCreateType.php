<?php

declare(strict_types=1);

namespace App\Project\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Creates a share link with expiry, optional usage limit, and optional issue scope.
 */
final class ProjectShareCreateType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addIntegerField('days', [
                'required' => true,
                'label' => false,
                'constraints' => [new Range(min: 1, max: 30)],
                'attr' => ['min' => 1, 'max' => 30],
            ]);
            $this->addIntegerField('max_uses', [
                'required' => false,
                'label' => false,
                'constraints' => [new Range(min: 1, max: 10_000)],
                'attr' => ['min' => 1, 'max' => 10_000],
            ]);
            $this->addTextField('issue_uuid', [
                'required' => false,
                'label' => false,
                'constraints' => [new Length(max: 64)],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'csrf_protection' => true,
            'csrf_token_id' => 'project_share_create',
            'translation_domain' => 'messages',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}

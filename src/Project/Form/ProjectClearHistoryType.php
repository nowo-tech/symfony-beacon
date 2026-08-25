<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Danger-zone clear history: CSRF + slide-to-confirm (UX friction, not authorization).
 */
final class ProjectClearHistoryType extends FormKitAbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addSlideToConfirmField('confirm', [
                'profile' => 'danger',
                'required' => true,
                'label' => false,
                'text' => 'project.danger.clear_slide',
                'confirmed_text' => 'project.danger.clear_slid',
                'hint' => 'project.danger.clear_slide_hint',
                'translation_domain' => 'messages',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'project_clear',
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_clear_history';
    }
}

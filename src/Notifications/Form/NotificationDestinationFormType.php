<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Create/edit a project notification destination.
 *
 * @extends AbstractType<NotificationDestination>
 */
final class NotificationDestinationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoryChoices = [];
        foreach (NotificationCategories::ALL as $category) {
            $categoryChoices['notifications.category.'.$category] = $category;
        }

        $builder
            ->add('label', TextType::class, [
                'label' => 'notifications.form.label',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 120),
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => NotificationDestinationType::class,
                'label' => 'notifications.form.type',
                'choice_label' => static fn (NotificationDestinationType $type): string => 'notifications.type.'.$type->value,
                'choice_translation_domain' => 'messages',
            ])
            ->add('endpointUrl', UrlType::class, [
                'label' => 'notifications.form.endpoint',
                'default_protocol' => 'https',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Url(protocols: ['http', 'https']),
                    new Assert\Length(max: 2048),
                ],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'notifications.form.enabled',
                'required' => false,
            ])
            ->add('categories', ChoiceType::class, [
                'label' => 'notifications.form.categories',
                'choices' => $categoryChoices,
                'multiple' => true,
                'expanded' => true,
                'choice_translation_domain' => 'messages',
                'constraints' => [
                    new Assert\Count(min: 1),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NotificationDestination::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'notification_destination';
    }
}

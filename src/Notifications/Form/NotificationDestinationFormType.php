<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Service\NotificationOutboundFormatter;
use App\Notifications\Service\OutboundUrlGuard;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Create/edit a project notification destination.
 *
 * @extends AbstractType<NotificationDestination>
 */
final class NotificationDestinationFormType extends AbstractType
{
    public function __construct(
        private readonly NotificationOutboundFormatter $outboundFormatter,
        private readonly OutboundUrlGuard $outboundUrlGuard,
        private readonly TranslatorInterface $translator,
    ) {
    }

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
            ->add('endpointUrl', TextType::class, [
                'label' => 'notifications.form.endpoint',
                'help' => 'notifications.form.endpoint_help',
                'constraints' => [
                    new Assert\NotBlank(),
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
                'expanded' => false,
                'autocomplete' => true,
                'choice_translation_domain' => 'messages',
                'attr' => [
                    'data-notification-categories' => '1',
                ],
                'tom_select_options' => [
                    'plugins' => ['remove_button'],
                    'maxItems' => \count(NotificationCategories::ALL),
                    'closeAfterSelect' => false,
                    'openOnFocus' => true,
                    'highlight' => true,
                    'create' => false,
                    'persist' => false,
                ],
                'preload' => 'focus',
                'constraints' => [
                    new Assert\Count(min: 1),
                ],
            ])
            ->add('quietHoursEnabled', CheckboxType::class, [
                'label' => 'notifications.form.quiet_hours_enabled',
                'required' => false,
                'help' => 'notifications.form.quiet_hours_help',
            ])
            ->add('quietHoursTimezone', TextType::class, [
                'label' => 'notifications.form.quiet_hours_timezone',
                'required' => false,
                'empty_data' => 'UTC',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 64),
                ],
            ])
            ->add('quietHoursStart', TextType::class, [
                'label' => 'notifications.form.quiet_hours_start',
                'required' => false,
                'attr' => ['placeholder' => '22:00'],
            ])
            ->add('quietHoursEnd', TextType::class, [
                'label' => 'notifications.form.quiet_hours_end',
                'required' => false,
                'attr' => ['placeholder' => '07:00'],
            ])
            ->add('digestEnabled', CheckboxType::class, [
                'label' => 'notifications.form.digest_enabled',
                'required' => false,
                'help' => 'notifications.form.digest_help',
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var NotificationDestination $data */
            $data = $event->getData();
            $form = $event->getForm();

            try {
                new DateTimeZone($data->getQuietHoursTimezone());
            } catch (Exception) {
                $form->get('quietHoursTimezone')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_timezone_invalid'),
                ));
            }

            $start = $data->getQuietHoursStart();
            $end = $data->getQuietHoursEnd();
            $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

            if (null !== $start && 1 !== preg_match($timePattern, $start)) {
                $form->get('quietHoursStart')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_time_invalid'),
                ));
            }
            if (null !== $end && 1 !== preg_match($timePattern, $end)) {
                $form->get('quietHoursEnd')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_time_invalid'),
                ));
            }

            if ($data->isQuietHoursEnabled()) {
                if (null === $start || null === $end) {
                    $form->get('quietHoursStart')->addError(new FormError(
                        $this->translator->trans('notifications.form.quiet_hours_required'),
                    ));
                } elseif ($start === $end) {
                    $form->get('quietHoursEnd')->addError(new FormError(
                        $this->translator->trans('notifications.form.quiet_hours_range_invalid'),
                    ));
                }
            }

            $endpoint = $data->getEndpointUrl();
            $type = $data->getType();

            $valid = match ($type) {
                NotificationDestinationType::Email => false !== filter_var($endpoint, \FILTER_VALIDATE_EMAIL),
                NotificationDestinationType::Telegram => $this->isValidTelegramEndpoint($endpoint),
                NotificationDestinationType::Slack,
                NotificationDestinationType::Discord,
                NotificationDestinationType::Teams,
                NotificationDestinationType::Http => (bool) filter_var($endpoint, \FILTER_VALIDATE_URL)
                    && str_starts_with(strtolower($endpoint), 'http'),
            };

            if (!$valid) {
                $form->get('endpointUrl')->addError(new FormError(
                    $this->translator->trans('notifications.form.endpoint_invalid'),
                ));

                return;
            }

            if (\in_array($type, [
                NotificationDestinationType::Slack,
                NotificationDestinationType::Discord,
                NotificationDestinationType::Teams,
                NotificationDestinationType::Http,
            ], true)) {
                try {
                    $this->outboundUrlGuard->assertSafeHttpUrl($endpoint);
                } catch (InvalidArgumentException) {
                    $form->get('endpointUrl')->addError(new FormError(
                        $this->translator->trans('notifications.form.endpoint_ssrf'),
                    ));
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NotificationDestination::class,
        ]);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'notification_destination';
    }

    private function isValidTelegramEndpoint(string $endpoint): bool
    {
        try {
            $this->outboundFormatter->parseTelegramEndpoint($endpoint);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Form;

use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\NotificationCategories;
use App\Notifications\Service\NotificationOutboundFormatter;
use App\Notifications\Service\OutboundUrlGuard;
use App\Shared\Form\FormKitAbstractType;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PasswordToggleBundle\Form\Type\PasswordType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Create/edit a project notification destination (FormKit).
 */
final class NotificationDestinationFormType extends FormKitAbstractType
{
    public function __construct(
        FormOptionsMerger $formOptionsMerger,
        FormTypeMap $formTypeMap,
        private readonly NotificationOutboundFormatter $outboundFormatter,
        private readonly OutboundUrlGuard $outboundUrlGuard,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($formOptionsMerger, $formTypeMap);
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $categoryChoices = [];
        foreach (NotificationCategories::ALL as $category) {
            $categoryChoices['notifications.category.'.$category] = $category;
        }

        $this->withBuilder($builder, function () use ($categoryChoices): void {
            $this->addTextField('label', [
                'label' => 'notifications.form.label',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 120),
                ],
            ]);
            $this->boundBuilder()->add(
                'type',
                EnumType::class,
                $this->mergeFieldOptions('type', 'choice', [
                    'class' => NotificationDestinationType::class,
                    'label' => 'notifications.form.type',
                    'choice_label' => static fn (NotificationDestinationType $type): string => 'notifications.type.'.$type->value,
                ]),
            );
            $this->addTextField('endpointUrl', [
                'label' => 'notifications.form.endpoint',
                'help' => 'notifications.form.endpoint_help',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 2048),
                ],
            ]);
            $this->boundBuilder()->add(
                'signingSecret',
                PasswordType::class,
                $this->mergeFieldOptions('signingSecret', 'password', [
                    'label' => 'notifications.form.signing_secret',
                    'help' => 'notifications.form.signing_secret_help',
                    'required' => false,
                    'mapped' => false,
                    'always_empty' => true,
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ]),
            );
            $this->addCheckboxField('clearSigningSecret', [
                'label' => 'notifications.form.clear_signing_secret',
                'required' => false,
                'mapped' => false,
            ]);
            $this->addCheckboxField('enabled', [
                'label' => 'notifications.form.enabled',
                'required' => false,
            ]);
            $this->addChoiceField('categories', [
                'label' => 'notifications.form.categories',
                'choices' => $categoryChoices,
                'multiple' => true,
                'expanded' => false,
                'autocomplete' => true,
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
            ]);
            $this->addCheckboxField('quietHoursEnabled', [
                'label' => 'notifications.form.quiet_hours_enabled',
                'required' => false,
                'help' => 'notifications.form.quiet_hours_help',
            ]);
            $this->addTextField('quietHoursTimezone', [
                'label' => 'notifications.form.quiet_hours_timezone',
                'required' => false,
                'empty_data' => 'UTC',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 64),
                ],
            ]);
            $this->addTextField('quietHoursStart', [
                'label' => 'notifications.form.quiet_hours_start',
                'required' => false,
            ]);
            $this->addTextField('quietHoursEnd', [
                'label' => 'notifications.form.quiet_hours_end',
                'required' => false,
            ]);
            $this->addCheckboxField('digestEnabled', [
                'label' => 'notifications.form.digest_enabled',
                'required' => false,
                'placeholder' => false,
                'help' => 'notifications.form.digest_help',
            ]);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var NotificationDestination $data */
            $data = $event->getData();
            $form = $event->getForm();

            if (true === $form->get('clearSigningSecret')->getData()) {
                $data->setSigningSecret(null);
            } else {
                $plainSecret = trim((string) $form->get('signingSecret')->getData());
                if ('' !== $plainSecret) {
                    $data->setSigningSecret($plainSecret);
                }
            }

            try {
                new DateTimeZone($data->getQuietHoursTimezone());
            } catch (Exception) {
                $form->get('quietHoursTimezone')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_timezone_invalid', [], 'form'),
                ));
            }

            $start = $data->getQuietHoursStart();
            $end = $data->getQuietHoursEnd();
            $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

            if (null !== $start && 1 !== preg_match($timePattern, $start)) {
                $form->get('quietHoursStart')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_time_invalid', [], 'form'),
                ));
            }
            if (null !== $end && 1 !== preg_match($timePattern, $end)) {
                $form->get('quietHoursEnd')->addError(new FormError(
                    $this->translator->trans('notifications.form.quiet_hours_time_invalid', [], 'form'),
                ));
            }

            if ($data->isQuietHoursEnabled()) {
                if (null === $start || null === $end) {
                    $form->get('quietHoursStart')->addError(new FormError(
                        $this->translator->trans('notifications.form.quiet_hours_required', [], 'form'),
                    ));
                } elseif ($start === $end) {
                    $form->get('quietHoursEnd')->addError(new FormError(
                        $this->translator->trans('notifications.form.quiet_hours_range_invalid', [], 'form'),
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
                    $this->translator->trans('notifications.form.endpoint_invalid', [], 'form'),
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
                        $this->translator->trans('notifications.form.endpoint_ssrf', [], 'form'),
                    ));
                }
            }
        });
    }

    #[Override]
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

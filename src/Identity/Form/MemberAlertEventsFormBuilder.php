<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Notifications\Enum\MemberAlertEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Shared enabled/involved event matrix for member-alert preference forms.
 */
final class MemberAlertEventsFormBuilder
{
    /**
     * Adds an {@code events} compound with one row per {@see MemberAlertEvent}.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function addEventsMatrix(
        FormBuilderInterface $builder,
        ?string $eventsLabel = null,
    ): void {
        $eventsOptions = [
            'label' => $eventsLabel ?? false,
            'required' => false,
        ];
        if (null !== $eventsLabel) {
            $eventsOptions['translation_domain'] = 'messages';
        }

        $events = $builder->create('events', FormType::class, $eventsOptions);
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $row = $builder->create($event->formKey(), FormType::class, [
                'label' => $event->translationKey(),
                'translation_domain' => 'messages',
                'required' => false,
            ]);
            $row->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.member_alerts.enabled',
                'translation_domain' => 'messages',
            ]);
            $row->add('involved', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.member_alerts.scope.involved',
                'translation_domain' => 'messages',
            ]);
            $events->add($row);
        }
        $builder->add($events);
    }
}

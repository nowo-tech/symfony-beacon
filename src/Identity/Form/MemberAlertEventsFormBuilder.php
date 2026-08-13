<?php

declare(strict_types=1);

namespace App\Identity\Form;

use App\Notifications\Enum\MemberAlertEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Shared enabled/involved event matrix for member-alert preference forms.
 *
 * Nested compounds are built outside FormKit merge; the root {@code events}
 * field sets {@code translation_domain: form} once so children inherit it
 * (same {@code preferences.*} keys as Twig).
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
        $events = $builder->create('events', FormType::class, [
            'label' => $eventsLabel ?? false,
            'required' => false,
            // Outside FormKit helpers — profile domain would not apply otherwise.
            'translation_domain' => 'form',
        ]);
        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            $row = $events->create($event->formKey(), FormType::class, [
                'label' => $event->translationKey(),
                'required' => false,
            ]);
            $row->add('enabled', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.member_alerts.enabled',
            ]);
            $row->add('involved', CheckboxType::class, [
                'required' => false,
                'label' => 'preferences.member_alerts.scope.involved',
            ]);
            $events->add($row);
        }
        $builder->add($events);
    }
}

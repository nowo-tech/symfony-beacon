<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Form;

use App\Identity\Form\MemberAlertEventsFormBuilder;
use App\Notifications\Enum\MemberAlertEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

final class MemberAlertEventsFormBuilderTest extends TestCase
{
    public function testAddEventsMatrixCreatesRowPerUiEvent(): void
    {
        $builder = Forms::createFormFactory()->createBuilder();
        MemberAlertEventsFormBuilder::addEventsMatrix($builder, 'Events');

        $form = $builder->getForm();
        self::assertTrue($form->has('events'));
        $events = $form->get('events');
        self::assertSame('form', $events->getConfig()->getOption('translation_domain'));
        self::assertSame('Events', $events->getConfig()->getOption('label'));

        foreach (MemberAlertEvent::casesInUiOrder() as $event) {
            self::assertTrue($events->has($event->formKey()), $event->formKey());
            $row = $events->get($event->formKey());
            self::assertTrue($row->has('enabled'));
            self::assertTrue($row->has('involved'));
            self::assertSame($event->translationKey(), $row->getConfig()->getOption('label'));
        }
    }
}

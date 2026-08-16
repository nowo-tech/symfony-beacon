<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Form;

use App\Identity\Entity\User;
use App\Identity\Form\AccountDisplayType;
use App\Issues\IssuePanelIds;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\TagInputBundle\Form\TagType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\PreloadedExtension;
use InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountDisplayTypeTest extends TestCase
{
    public function testAppearanceSectionBuildsLocaleAndPreferenceChoices(): void
    {
        $form = $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => AccountDisplayType::SECTION_APPEARANCE,
            'enabled_locales' => ['en', 'es'],
        ]);

        self::assertTrue($form->has('preferredLocale'));
        self::assertSame(['EN' => 'en', 'ES' => 'es'], $form->get('preferredLocale')->getConfig()->getOption('choices'));
        self::assertTrue($form->has('preferredMotion'));
    }

    public function testPanelTourAndNotificationSectionsBuildExpectedFields(): void
    {
        $panels = $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => AccountDisplayType::SECTION_PANELS,
        ]);
        $panelView = $panels->createView()['preferredCollapsedIssuePanels'];
        self::assertTrue($panels->has('preferredCollapsedIssuePanels'));
        self::assertStringContainsString('nowo-tag-input', (string) ($panelView->vars['attr']['data-controller'] ?? ''));
        self::assertSame(json_encode(IssuePanelIds::all(), JSON_THROW_ON_ERROR), $panelView->vars['attr']['data-nowo-tag-input-whitelist-value']);

        $tours = $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => AccountDisplayType::SECTION_TOURS,
        ]);
        self::assertTrue($tours->has('productTourEnabledPages'));
        self::assertTrue($tours->get('productTourEnabledPages')->getConfig()->getOption('multiple'));

        $notificationsOff = $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => AccountDisplayType::SECTION_NOTIFICATIONS,
            'push_available' => false,
        ]);
        self::assertFalse($notificationsOff->has('pushNotificationsEnabled'));

        $notificationsOn = $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => AccountDisplayType::SECTION_NOTIFICATIONS,
            'push_available' => true,
        ]);
        self::assertTrue($notificationsOn->has('pushNotificationsEnabled'));
    }

    public function testRejectsUnknownSection(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->formFactory()->create(AccountDisplayType::class, new User(), [
            'section' => 'unknown',
        ]);
    }

    public function testBuildFormRejectsUnknownSectionAfterOptionResolution(): void
    {
        $type = new AccountDisplayType($this->formOptionsMerger(), new FormTypeMap());

        $this->expectException(InvalidArgumentException::class);
        $type->buildForm(
            $this->createStub(FormBuilderInterface::class),
            [
                'enabled_locales' => ['en'],
                'section' => 'unknown',
                'push_available' => false,
            ],
        );
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension(
                [new AccountDisplayType($this->formOptionsMerger(), new FormTypeMap()), new TagType()],
                [ChoiceType::class => [new ChoiceUiOptionsExtension()]],
            ))
            ->getFormFactory();
    }

    private function formOptionsMerger(): FormOptionsMerger
    {
        return new FormOptionsMerger([
            'beacon' => [
                'translation_domain' => 'form',
                'auto_placeholder' => true,
                'auto_help' => true,
                'defaults' => [
                    'attr' => ['class' => 'input'],
                    'row_attr' => ['class' => 'row'],
                ],
                'field_types' => [
                    'text' => [],
                    'choice' => [],
                    'checkbox' => [],
                ],
            ],
        ], 'beacon', new ConstraintDefinitionFactory());
    }
}

final class ChoiceUiOptionsExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined([
            'autocomplete',
            'preload',
            'tom_select_options',
            'select_all',
            'select_all_label',
            'select_all_translation_domain',
            'select_all_css_class',
            'select_all_wrapper_css_class',
            'select_all_label_css_class',
            'select_all_container_css_class',
        ]);
    }
}

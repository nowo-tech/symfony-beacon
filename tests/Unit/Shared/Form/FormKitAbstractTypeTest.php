<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Form;

use App\Shared\Form\FormKitAbstractType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class FormKitAbstractTypeTest extends TestCase
{
    public function testTwigOwnedChromeOptionsDisableTranslatorOwnedChrome(): void
    {
        $type = new ReflectionClass(FormKitAbstractTypeHarness::class)->newInstanceWithoutConstructor();

        self::assertSame([
            'label' => false,
            'help' => false,
            'placeholder' => false,
            'translation_domain' => false,
        ], $type->exposeTwigOwnedChromeOptions());
    }
}

final class FormKitAbstractTypeHarness extends FormKitAbstractType
{
    public function exposeTwigOwnedChromeOptions(): array
    {
        return $this->twigOwnedChromeOptions();
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Controller;

use App\Shared\Controller\RequiresValidFormTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RequiresValidFormTraitTest extends TestCase
{
    public function testRequireValidFormThrowsWhenNotSubmittedOrInvalid(): void
    {
        $controller = new class {
            use RequiresValidFormTrait;

            public function check(FormInterface $form): void
            {
                $this->requireValidForm($form);
            }

            public function checkCsrf(FormInterface $form): void
            {
                $this->requireValidCsrfForm($form);
            }

            protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message, $previous);
            }
        };

        $invalid = $this->createStub(FormInterface::class);
        $invalid->method('isSubmitted')->willReturn(false);
        $this->expectException(AccessDeniedHttpException::class);
        $controller->check($invalid);
    }

    public function testRequireValidCsrfFormAcceptsValidSubmission(): void
    {
        $controller = new class {
            use RequiresValidFormTrait;

            public function checkCsrf(FormInterface $form): void
            {
                $this->requireValidCsrfForm($form);
            }

            protected function createAccessDeniedException(string $message = 'Access Denied.', ?\Throwable $previous = null): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message, $previous);
            }
        };

        $valid = $this->createStub(FormInterface::class);
        $valid->method('isSubmitted')->willReturn(true);
        $valid->method('isValid')->willReturn(true);
        $controller->checkCsrf($valid);
        self::assertTrue(true);
    }
}

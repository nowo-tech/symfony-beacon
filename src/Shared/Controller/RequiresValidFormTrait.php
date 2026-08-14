<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Shared CSRF / form validation deny for AbstractController subclasses.
 *
 * Controllers must still call handleRequest() or submit() before this helper.
 *
 * @method void addFlash(string $type, mixed $message)
 */
trait RequiresValidFormTrait
{
    /**
     * @param FormInterface<mixed> $form
     *
     * @throws AccessDeniedHttpException
     */
    protected function requireValidForm(FormInterface $form, string $message = 'Invalid form submission.'): void
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException($message);
        }
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @throws AccessDeniedHttpException
     */
    protected function requireValidCsrfForm(FormInterface $form): void
    {
        $this->requireValidForm($form, 'Invalid CSRF token.');
    }

    /**
     * Accept a submitted role ChoiceType form, or flash and signal the caller to redirect.
     *
     * Invalid choice values (tampered POST) map to {@code $invalidRoleFlash} instead of 403.
     * Missing submission / CSRF failures still deny access.
     *
     * @param FormInterface<mixed> $form
     *
     * @return bool true when the form is valid; false when the role field is invalid (flash already set)
     *
     * @throws AccessDeniedHttpException
     */
    protected function acceptSubmittedRoleForm(
        FormInterface $form,
        string $invalidRoleFlash = 'flash.project.member_invalid_role',
    ): bool {
        if (!$form->isSubmitted()) {
            $this->requireValidForm($form);
        }
        if ($form->isValid()) {
            return true;
        }
        if ($form->has('_token') && !$form->get('_token')->isValid()) {
            $this->requireValidCsrfForm($form);
        }
        if ($form->has('role') && !$form->get('role')->isValid()) {
            $this->addFlash('error', $invalidRoleFlash);

            return false;
        }

        $this->requireValidForm($form);

        return true;
    }
}

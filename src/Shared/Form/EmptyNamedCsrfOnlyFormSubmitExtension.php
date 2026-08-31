<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Nowo\BreadcrumbKitBundle\Form\Dashboard\DashboardPostDeleteType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Symfony 8+ keeps CSRF out of {@see \Symfony\Component\Form\FormInterface::all()} (view-only
 * field in FormTypeCsrfExtension::finishView). Empty-named CSRF-only forms then never
 * auto-submit: HttpFoundationRequestHandler requires at least one intersecting child key.
 *
 * BreadcrumbKit delete confirms use createNamedBuilder('', DashboardPostDeleteType) — add a
 * dummy mapped=false field so POST with {@code _confirm} + {@code _token} is submitted.
 */
final class EmptyNamedCsrfOnlyFormSubmitExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DashboardPostDeleteType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($builder->has('_confirm')) {
            return;
        }

        $builder->add('_confirm', HiddenType::class, [
            'mapped' => false,
            'data' => '1',
        ]);
    }
}

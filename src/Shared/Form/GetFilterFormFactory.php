<?php

declare(strict_types=1);

namespace App\Shared\Form;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Builds rootless GET forms so field names stay query-string friendly.
 */
final readonly class GetFilterFormFactory
{
    public function __construct(
        private FormFactoryInterface $formFactory,
    ) {
    }

    /**
     * @param class-string         $type
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     *
     * @return FormInterface<mixed>
     */
    public function create(string $type, array $data = [], array $options = []): FormInterface
    {
        return $this->formFactory->createNamed('', $type, $data, $options);
    }
}

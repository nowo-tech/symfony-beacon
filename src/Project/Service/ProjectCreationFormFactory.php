<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Form\ProjectType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Creates the standard new-project form used by dashboard and project flows.
 */
final readonly class ProjectCreationFormFactory
{
    public function __construct(
        private FormFactoryInterface $formFactory,
    ) {
    }

    /**
     * @return FormInterface<mixed>
     */
    public function create(array $data = [], array $options = []): FormInterface
    {
        return $this->formFactory->create(ProjectType::class, $data, $options);
    }
}

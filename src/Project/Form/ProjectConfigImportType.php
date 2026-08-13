<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\AbstractJsonImportType;
use Override;

/**
 * Uploads a project config bundle JSON file (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code project_config_import.*}.
 */
final class ProjectConfigImportType extends AbstractJsonImportType
{
    protected function fileFieldName(): string
    {
        return 'bundle';
    }

    protected function fileInputTestId(): string
    {
        return 'project-config-file';
    }

    protected function csrfTokenId(): string
    {
        return 'project_config_import';
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'project_config_import';
    }
}

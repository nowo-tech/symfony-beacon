<?php

declare(strict_types=1);

namespace App\Project\Form;

use App\Shared\Form\AbstractJsonImportType;
use Override;

/**
 * Uploads an admin project portability bundle JSON file (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code admin_project_import.*}.
 */
final class AdminProjectImportType extends AbstractJsonImportType
{
    protected function fileFieldName(): string
    {
        return 'bundle';
    }

    protected function fileInputTestId(): string
    {
        return 'admin-projects-config-file';
    }

    protected function csrfTokenId(): string
    {
        return 'admin_projects_import';
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'admin_project_import';
    }
}

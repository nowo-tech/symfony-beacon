<?php

declare(strict_types=1);

namespace App\Shared\Settings\Form;

use App\Shared\Form\AbstractJsonImportType;
use Override;

/**
 * Uploads an instance config JSON export for import (FormKit {@code beacon}).
 *
 * Catalogue: {@code translations/form.*.yaml} → {@code instance_config_import.*}.
 */
final class InstanceConfigImportType extends AbstractJsonImportType
{
    protected function fileFieldName(): string
    {
        return 'config';
    }

    protected function fileInputTestId(): string
    {
        return 'instance-config-file';
    }

    protected function csrfTokenId(): string
    {
        return 'instance_config_import';
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'instance_config_import';
    }
}

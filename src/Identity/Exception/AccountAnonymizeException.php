<?php

declare(strict_types=1);

namespace App\Identity\Exception;

use RuntimeException;

/**
 * Raised when GDPR anonymize cannot proceed (sole owner, last admin, already scrubbed).
 */
final class AccountAnonymizeException extends RuntimeException
{
    public const string ALREADY_ANONYMIZED = 'already_anonymized';
    public const string SOLE_OWNER = 'sole_owner';
    public const string LAST_ADMIN = 'last_admin';

    /**
     * @param list<array{uuid: string, name: string}> $soleOwnerProjects
     */
    public function __construct(
        public readonly string $reasonCode,
        public readonly array $soleOwnerProjects = [],
        string $message = '',
    ) {
        parent::__construct('' !== $message ? $message : $reasonCode);
    }
}

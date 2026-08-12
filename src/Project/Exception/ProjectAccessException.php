<?php

declare(strict_types=1);

namespace App\Project\Exception;

use RuntimeException;

/**
 * Domain failure while mutating project memberships or group access links.
 *
 * Controllers map {@see $reasonCode} to flash keys via {@see \App\Project\Service\ProjectAccessFlashKeys}.
 */
final class ProjectAccessException extends RuntimeException
{
    public const string USER_NOT_FOUND = 'user_not_found';
    public const string USER_DISABLED = 'user_disabled';
    public const string ALREADY_MEMBER = 'already_member';
    public const string INVALID_ROLE = 'invalid_role';
    public const string LAST_OWNER = 'last_owner';
    public const string CANNOT_REMOVE_FULL = 'cannot_remove_full';
    public const string CANNOT_MANAGE_OWNER = 'cannot_manage_owner';
    public const string CANNOT_TRANSFER_TO_SELF = 'cannot_transfer_to_self';
    public const string ALREADY_OWNER = 'already_owner';
    public const string GROUP_ALREADY_LINKED = 'group_already_linked';
    public const string GROUP_LINK_FORBIDDEN = 'group_link_forbidden';
    public const string WRONG_PROJECT = 'wrong_project';
    public const string FORBIDDEN = 'forbidden';

    public function __construct(
        public readonly string $reasonCode,
        string $message = '',
    ) {
        parent::__construct('' !== $message ? $message : $reasonCode);
    }

    public static function of(string $reasonCode): self
    {
        return new self($reasonCode);
    }

    public function isForbidden(): bool
    {
        return self::FORBIDDEN === $this->reasonCode;
    }
}

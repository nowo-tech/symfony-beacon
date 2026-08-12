<?php

declare(strict_types=1);

namespace App\Project\Service;

use App\Project\Exception\ProjectAccessException;

/**
 * Maps project membership manager exception codes to flash translation keys.
 */
final class ProjectAccessFlashKeys
{
    public static function forException(ProjectAccessException $exception): string
    {
        return self::forCode($exception->reasonCode);
    }

    public static function forCode(string $code): string
    {
        return match ($code) {
            ProjectAccessException::USER_NOT_FOUND => 'flash.project.member_user_not_found',
            ProjectAccessException::USER_DISABLED => 'flash.project.member_user_disabled',
            ProjectAccessException::ALREADY_MEMBER => 'flash.project.member_already',
            ProjectAccessException::INVALID_ROLE => 'flash.project.member_invalid_role',
            ProjectAccessException::LAST_OWNER => 'flash.project.member_last_owner',
            ProjectAccessException::CANNOT_REMOVE_FULL => 'flash.project.member_cannot_remove_full',
            ProjectAccessException::CANNOT_MANAGE_OWNER => 'flash.project.member_cannot_manage_owner',
            ProjectAccessException::CANNOT_TRANSFER_TO_SELF => 'flash.project.transfer_to_self',
            ProjectAccessException::ALREADY_OWNER => 'flash.project.transfer_already_owner',
            ProjectAccessException::GROUP_ALREADY_LINKED => 'flash.project.group_already',
            ProjectAccessException::GROUP_LINK_FORBIDDEN => 'flash.project.group_link_forbidden',
            default => 'flash.project.member_error',
        };
    }
}

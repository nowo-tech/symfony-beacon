<?php

declare(strict_types=1);

namespace App\Project\Service;

/**
 * Maps project membership manager exception codes to flash translation keys.
 */
final class ProjectAccessFlashKeys
{
    public static function forCode(string $code): string
    {
        return match ($code) {
            'user_not_found' => 'flash.project.member_user_not_found',
            'user_disabled' => 'flash.project.member_user_disabled',
            'already_member' => 'flash.project.member_already',
            'invalid_role' => 'flash.project.member_invalid_role',
            'last_owner' => 'flash.project.member_last_owner',
            'cannot_remove_full' => 'flash.project.member_cannot_remove_full',
            'cannot_manage_owner' => 'flash.project.member_cannot_manage_owner',
            'cannot_transfer_to_self' => 'flash.project.transfer_to_self',
            'already_owner' => 'flash.project.transfer_already_owner',
            'group_already_linked' => 'flash.project.group_already',
            'group_link_forbidden' => 'flash.project.group_link_forbidden',
            default => 'flash.project.member_error',
        };
    }
}

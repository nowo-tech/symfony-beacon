<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Exception\ProjectAccessException;
use App\Project\Service\ProjectAccessFlashKeys;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectAccessFlashKeysTest extends TestCase
{
    #[DataProvider('knownCodes')]
    public function testKnownCodesMapToDistinctFlashKeys(string $code, string $expected): void
    {
        self::assertSame($expected, ProjectAccessFlashKeys::forCode($code));
        self::assertSame($expected, ProjectAccessFlashKeys::forException(ProjectAccessException::of($code)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function knownCodes(): iterable
    {
        yield 'user_not_found' => [ProjectAccessException::USER_NOT_FOUND, 'flash.project.member_user_not_found'];
        yield 'user_disabled' => [ProjectAccessException::USER_DISABLED, 'flash.project.member_user_disabled'];
        yield 'already_member' => [ProjectAccessException::ALREADY_MEMBER, 'flash.project.member_already'];
        yield 'invalid_role' => [ProjectAccessException::INVALID_ROLE, 'flash.project.member_invalid_role'];
        yield 'last_owner' => [ProjectAccessException::LAST_OWNER, 'flash.project.member_last_owner'];
        yield 'cannot_remove_full' => [ProjectAccessException::CANNOT_REMOVE_FULL, 'flash.project.member_cannot_remove_full'];
        yield 'cannot_manage_owner' => [ProjectAccessException::CANNOT_MANAGE_OWNER, 'flash.project.member_cannot_manage_owner'];
        yield 'transfer_to_self' => [ProjectAccessException::CANNOT_TRANSFER_TO_SELF, 'flash.project.transfer_to_self'];
        yield 'already_owner' => [ProjectAccessException::ALREADY_OWNER, 'flash.project.transfer_already_owner'];
        yield 'group_already' => [ProjectAccessException::GROUP_ALREADY_LINKED, 'flash.project.group_already'];
        yield 'group_forbidden' => [ProjectAccessException::GROUP_LINK_FORBIDDEN, 'flash.project.group_link_forbidden'];
    }

    public function testUnknownCodesFallBackToGenericError(): void
    {
        self::assertSame('flash.project.member_error', ProjectAccessFlashKeys::forCode(ProjectAccessException::WRONG_PROJECT));
        self::assertSame('flash.project.member_error', ProjectAccessFlashKeys::forCode('totally_unknown'));
    }
}

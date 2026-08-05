<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Service\InteractionActionToken;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InteractionActionTokenTest extends TestCase
{
    public function testRoundTripValidResolveToken(): void
    {
        $svc = new InteractionActionToken();
        $token = $svc->issueResolveToken(
            'secret',
            'd-uuid',
            'p-uuid',
            'i-uuid',
            1_700_000_000,
            3600,
        );

        self::assertTrue($svc->isValidResolveToken('secret', $token, 1_700_000_100));
        self::assertNotSame('', $token['n']);
        self::assertFalse($svc->isValidResolveToken('wrong', $token, 1_700_000_100));
        self::assertFalse($svc->isValidResolveToken('secret', $token, 1_700_003_601));
        self::assertFalse($svc->isValidAssignToken('secret', $token, 1_700_000_100));

        $withoutNonce = $token;
        unset($withoutNonce['n']);
        self::assertFalse($svc->isValidResolveToken('secret', $withoutNonce, 1_700_000_100));
    }

    public function testRoundTripValidAssignToken(): void
    {
        $svc = new InteractionActionToken();
        $token = $svc->issueAssignToken(
            'secret',
            'd-uuid',
            'p-uuid',
            'i-uuid',
            1_700_000_000,
            3600,
        );

        self::assertSame('assign', $token['a']);
        self::assertTrue($svc->isValidAssignToken('secret', $token, 1_700_000_100));
        self::assertFalse($svc->isValidResolveToken('secret', $token, 1_700_000_100));
        self::assertFalse($svc->isValidAssignToken('wrong', $token, 1_700_000_100));
    }

    public function testRejectsTamperedClaims(): void
    {
        $svc = new InteractionActionToken();
        $token = $svc->issueResolveToken('secret', 'd', 'p', 'i', 1_700_000_000, 3600);
        $token['i'] = 'other';

        self::assertFalse($svc->isValidResolveToken('secret', $token, 1_700_000_000));
    }

    public function testRejectsUnsupportedAction(): void
    {
        $svc = new InteractionActionToken();
        $this->expectException(InvalidArgumentException::class);
        $svc->issueActionToken('delete', 'secret', 'd', 'p', 'i');
    }
}

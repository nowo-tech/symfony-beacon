<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Notifications\Service\InteractionActionToken;
use PHPUnit\Framework\TestCase;

final class InteractionActionTokenTest extends TestCase
{
    public function testRoundTripValidToken(): void
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
        self::assertFalse($svc->isValidResolveToken('wrong', $token, 1_700_000_100));
        self::assertFalse($svc->isValidResolveToken('secret', $token, 1_700_003_601));
    }

    public function testRejectsTamperedClaims(): void
    {
        $svc = new InteractionActionToken();
        $token = $svc->issueResolveToken('secret', 'd', 'p', 'i', 1_700_000_000, 3600);
        $token['i'] = 'other';

        self::assertFalse($svc->isValidResolveToken('secret', $token, 1_700_000_000));
    }
}

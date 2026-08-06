<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications;

use App\Notifications\Service\SlackRequestSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class SlackRequestSignatureVerifierTest extends TestCase
{
    public function testAcceptsValidSignatureWithinWindow(): void
    {
        $verifier = new SlackRequestSignatureVerifier();
        $secret = 'test-signing-secret';
        $ts = '1700000000';
        $body = 'payload=%7B%22type%22%3A%22block_actions%22%7D';
        $sig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, $secret);

        self::assertTrue($verifier->isValid($secret, $ts, $sig, $body, 1_700_000_030));
    }

    public function testRejectsTamperedBody(): void
    {
        $verifier = new SlackRequestSignatureVerifier();
        $secret = 'test-signing-secret';
        $ts = '1700000000';
        $body = 'payload=ok';
        $sig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, $secret);

        self::assertFalse($verifier->isValid($secret, $ts, $sig, $body.'x', 1_700_000_000));
    }

    public function testRejectsStaleTimestamp(): void
    {
        $verifier = new SlackRequestSignatureVerifier();
        $secret = 'test-signing-secret';
        $ts = '1700000000';
        $body = 'payload=ok';
        $sig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, $secret);

        self::assertFalse($verifier->isValid($secret, $ts, $sig, $body, 1_700_000_000 + 301));
    }
}

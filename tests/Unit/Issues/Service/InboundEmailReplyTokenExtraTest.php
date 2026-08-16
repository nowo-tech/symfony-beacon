<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\InboundEmailReplyToken;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use PHPUnit\Framework\TestCase;

final class InboundEmailReplyTokenExtraTest extends TestCase
{
    public function testParseValidRejectsInvalidJsonAndMissingClaims(): void
    {
        $tokenizer = $this->tokenizer('reply-secret');

        $badPayload = rtrim(strtr(base64_encode('{not-json}'), '+/', '-_'), '=');
        $badToken = $badPayload.'.'.hash_hmac('sha256', $badPayload, 'reply-secret');
        self::assertNull($tokenizer->parseValid($badToken, 100));

        $missingClaimsPayload = rtrim(strtr(base64_encode(json_encode(['i' => '', 'u' => 'ops@example.com', 'exp' => 120], \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $missingClaimsToken = $missingClaimsPayload.'.'.hash_hmac('sha256', $missingClaimsPayload, 'reply-secret');
        self::assertNull($tokenizer->parseValid($missingClaimsToken, 100));
    }

    private function tokenizer(string $secret): InboundEmailReplyToken
    {
        $settings = InstanceSettings::defaults()->setInboundWebhookSecret($secret);
        $repository = $this->createStub(InstanceSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        return new InboundEmailReplyToken(new InstanceOpsDefaults($repository));
    }
}

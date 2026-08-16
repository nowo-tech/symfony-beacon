<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Sms;

use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\Provider\SmsBridgeProvider;
use App\Shared\Sms\SmsOutboundMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SmsBridgeProviderExtraTest extends TestCase
{
    public function testNativeCreateIncludesOptionalFields(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(json_encode([
                'id' => 'native-optional',
                'status' => 'queued',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 202]);
        });

        $provider = new SmsBridgeProvider(
            $http,
            'https://sms-bridge.example',
            'smscli_testtoken',
            '',
            '+34600000001',
            'device-7',
            'native',
        );

        $provider->send(new SmsOutboundMessage('+34600111222', 'Hi', '+34600000001', null, 'client-7', 'https://example.test/status'));

        $body = json_decode((string) $requests[0][2]['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('+34600000001', $body['from']);
        self::assertSame('device-7', $body['deviceId']);
        self::assertSame('client-7', $body['clientMessageId']);
    }

    public function testTwilioAndNativeTransportExceptionsAreWrapped(): void
    {
        $twilio = new SmsBridgeProvider(
            new MockHttpClient(static fn (): never => throw new TransportException('twilio transport')),
            'https://sms-bridge.example',
            'smscli_testtoken',
            'ACbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        );

        try {
            $twilio->send(new SmsOutboundMessage('+34600111222', 'Hello'));
            self::fail('Expected SmsSendException for Twilio transport error');
        } catch (SmsSendException $e) {
            self::assertStringContainsString('transport error', $e->getMessage());
            self::assertStringContainsString('twilio transport', $e->getMessage());
        }

        $native = new SmsBridgeProvider(
            new MockHttpClient(static fn (): never => throw new TransportException('native transport')),
            'https://sms-bridge.example',
            'smscli_testtoken',
            '',
            '',
            '',
            'native',
        );

        try {
            $native->send(new SmsOutboundMessage('+34600111222', 'Hello'));
            self::fail('Expected SmsSendException for native transport error');
        } catch (SmsSendException $e) {
            self::assertStringContainsString('transport error', $e->getMessage());
            self::assertStringContainsString('native transport', $e->getMessage());
        }
    }
}

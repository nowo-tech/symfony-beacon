<?php

declare(strict_types=1);

namespace App\Shared\Sms\Provider;

use App\Shared\Sms\Exception\SmsSendException;
use App\Shared\Sms\SmsOutboundMessage;
use App\Shared\Sms\SmsSenderInterface;
use App\Shared\Sms\SmsSendResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SMS Bridge provider — Twilio-compatible Messages API (preferred) or native `/api/v1/sms`.
 *
 * @see sibling repo sms-bridge
 */
final readonly class SmsBridgeProvider implements SmsSenderInterface
{
    public const string PROVIDER_ID = 'sms_bridge';

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(SMS_BRIDGE_BASE_URL)%')]
        private string $baseUrl = '',
        #[Autowire('%env(SMS_BRIDGE_AUTH_TOKEN)%')]
        private string $authToken = '',
        #[Autowire('%env(SMS_BRIDGE_ACCOUNT_SID)%')]
        private string $accountSid = '',
        #[Autowire('%env(SMS_BRIDGE_FROM)%')]
        private string $defaultFrom = '',
        #[Autowire('%env(SMS_BRIDGE_DEVICE_ID)%')]
        private string $defaultDeviceId = '',
        #[Autowire('%env(SMS_BRIDGE_API_MODE)%')]
        private string $apiMode = 'twilio',
    ) {
    }

    public function getProviderId(): string
    {
        return self::PROVIDER_ID;
    }

    public function isConfigured(): bool
    {
        if ('' === trim($this->baseUrl) || '' === trim($this->authToken)) {
            return false;
        }

        if ($this->usesTwilioSurface()) {
            return 1 === preg_match('/^AC[0-9a-fA-F]{32}$/', trim($this->accountSid));
        }

        return str_starts_with(trim($this->authToken), 'smscli_');
    }

    public function send(SmsOutboundMessage $message): SmsSendResult
    {
        if (!$this->isConfigured()) {
            throw new SmsSendException('SMS Bridge provider is not configured (base URL / credentials).');
        }

        $to = $this->normalizeE164($message->toE164);
        $body = trim($message->body);
        if ('' === $body) {
            throw new SmsSendException('SMS body is required.');
        }

        $from = $message->fromE164 ?? ('' !== trim($this->defaultFrom) ? trim($this->defaultFrom) : null);
        $deviceId = $message->deviceId ?? ('' !== trim($this->defaultDeviceId) ? trim($this->defaultDeviceId) : null);

        return $this->usesTwilioSurface()
            ? $this->sendTwilioCompatible($to, $body, $from, $deviceId, $message)
            : $this->sendNative($to, $body, $from, $deviceId, $message);
    }

    private function usesTwilioSurface(): bool
    {
        $mode = strtolower(trim($this->apiMode));

        return 'native' !== $mode && '' !== trim($this->accountSid);
    }

    private function sendTwilioCompatible(
        string $to,
        string $body,
        ?string $from,
        ?string $deviceId,
        SmsOutboundMessage $message,
    ): SmsSendResult {
        $accountSid = trim($this->accountSid);
        $url = rtrim(trim($this->baseUrl), '/').'/2010-04-01/Accounts/'.$accountSid.'/Messages.json';

        $form = [
            'To' => $to,
            'Body' => $body,
        ];
        if (null !== $from && '' !== $from) {
            $form['From'] = $this->normalizeE164($from);
        }
        if (null !== $deviceId && '' !== $deviceId) {
            $form['DeviceId'] = $deviceId;
        }
        if (null !== $message->clientMessageId && '' !== $message->clientMessageId) {
            $form['ClientMessageId'] = $message->clientMessageId;
        }
        if (null !== $message->statusCallbackUrl && '' !== $message->statusCallbackUrl) {
            $form['StatusCallback'] = $message->statusCallbackUrl;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'auth_basic' => [$accountSid, trim($this->authToken)],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
                'body' => $form,
                'timeout' => 15,
            ]);
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new SmsSendException('SMS Bridge transport error: '.$e->getMessage(), 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $detail = (string) ($payload['message'] ?? $payload['error'] ?? json_encode($payload));
            throw new SmsSendException(\sprintf('SMS Bridge rejected message (HTTP %d): %s', $statusCode, $detail));
        }

        return new SmsSendResult(
            self::PROVIDER_ID,
            (string) ($payload['sid'] ?? ''),
            (string) ($payload['status'] ?? 'queued'),
            $payload,
        );
    }

    private function sendNative(
        string $to,
        string $body,
        ?string $from,
        ?string $deviceId,
        SmsOutboundMessage $message,
    ): SmsSendResult {
        $url = rtrim(trim($this->baseUrl), '/').'/api/v1/sms';
        $json = [
            'to' => $to,
            'body' => $body,
        ];
        if (null !== $from && '' !== $from) {
            $json['from'] = $this->normalizeE164($from);
        }
        if (null !== $deviceId && '' !== $deviceId) {
            $json['deviceId'] = $deviceId;
        }
        if (null !== $message->clientMessageId && '' !== $message->clientMessageId) {
            $json['clientMessageId'] = $message->clientMessageId;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.trim($this->authToken),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $json,
                'timeout' => 15,
            ]);
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new SmsSendException('SMS Bridge transport error: '.$e->getMessage(), 0, $e);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $detail = (string) ($payload['error'] ?? json_encode($payload));
            throw new SmsSendException(\sprintf('SMS Bridge rejected message (HTTP %d): %s', $statusCode, $detail));
        }

        return new SmsSendResult(
            self::PROVIDER_ID,
            (string) ($payload['id'] ?? ''),
            (string) ($payload['status'] ?? 'queued'),
            $payload,
        );
    }

    private function normalizeE164(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', trim($phone)) ?? '';
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            throw new SmsSendException('Phone must be E.164 (e.g. +34600111222).');
        }

        return $phone;
    }
}

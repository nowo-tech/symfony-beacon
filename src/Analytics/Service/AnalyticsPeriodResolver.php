<?php

declare(strict_types=1);

namespace App\Analytics\Service;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves Analytics period presets / custom ranges from the query string (UTC days).
 *
 * @phpstan-type ResolvedPeriod array{
 *     period: string,
 *     from: DateTimeImmutable,
 *     to: DateTimeImmutable,
 *     valid: bool,
 *     error: string|null
 * }
 */
final class AnalyticsPeriodResolver
{
    public const string DEFAULT_PERIOD = '30';

    public const int MAX_SPAN_DAYS = 366;

    /** @var list<string> */
    public const array PRESETS = ['7', '14', '30', '90', 'custom'];

    /**
     * @return ResolvedPeriod
     */
    public function resolve(Request $request): array
    {
        $period = $request->query->getString('period');
        if ('' === $period) {
            $period = self::DEFAULT_PERIOD;
        }

        if (!\in_array($period, self::PRESETS, true)) {
            return $this->fallback('analytics.period.invalid');
        }

        if ('custom' === $period) {
            return $this->resolveCustom(
                $request->query->getString('from'),
                $request->query->getString('to'),
            );
        }

        $days = (int) $period;
        $to = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $from = $to->modify(\sprintf('-%d days', $days - 1));

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Query params to preserve across forms / pagination (excluding page).
     *
     * @param ResolvedPeriod $resolved
     *
     * @return array<string, string>
     */
    public function queryParams(array $resolved, ?string $environment, ?string $release, ?string $level): array
    {
        $query = ['period' => $resolved['period']];
        if ('custom' === $resolved['period']) {
            $query['from'] = $resolved['from']->format('Y-m-d');
            $query['to'] = $resolved['to']->format('Y-m-d');
        }
        if (null !== $environment && '' !== $environment) {
            $query['environment'] = $environment;
        }
        if (null !== $release && '' !== $release) {
            $query['release'] = $release;
        }
        if (null !== $level && '' !== $level) {
            $query['level'] = $level;
        }

        return $query;
    }

    /**
     * @return ResolvedPeriod
     */
    private function resolveCustom(string $fromRaw, string $toRaw): array
    {
        if ('' === $fromRaw || '' === $toRaw) {
            return $this->fallback('analytics.period.custom_required');
        }

        try {
            $from = $this->parseDay($fromRaw);
            $to = $this->parseDay($toRaw);
        } catch (InvalidArgumentException) {
            return $this->fallback('analytics.period.invalid_date');
        }

        if ($to < $from) {
            return $this->fallback('analytics.period.end_before_start');
        }

        $span = (int) $from->diff($to)->days + 1;
        if ($span > self::MAX_SPAN_DAYS) {
            return $this->fallback('analytics.period.too_long');
        }

        return [
            'period' => 'custom',
            'from' => $from,
            'to' => $to,
            'valid' => true,
            'error' => null,
        ];
    }

    private function parseDay(string $raw): DateTimeImmutable
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (false === $dt || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            throw new InvalidArgumentException('Invalid date');
        }

        return $dt;
    }

    /**
     * @return ResolvedPeriod
     */
    private function fallback(string $errorKey): array
    {
        $to = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $from = $to->modify(\sprintf('-%d days', (int) self::DEFAULT_PERIOD - 1));

        return [
            'period' => self::DEFAULT_PERIOD,
            'from' => $from,
            'to' => $to,
            'valid' => false,
            'error' => $errorKey,
        ];
    }
}

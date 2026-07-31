<?php

declare(strict_types=1);

namespace App\Shared\Metrics;

/**
 * Renders Prometheus text exposition format 0.0.4.
 */
final class PrometheusTextFormatter
{
    /**
     * @param list<array{name: string, type: string, help: string, samples: list<array{labels: array<string, string>, value: float}>}> $families
     */
    public function format(array $families): string
    {
        $lines = [];
        foreach ($families as $family) {
            $lines[] = '# HELP '.$family['name'].' '.$family['help'];
            $lines[] = '# TYPE '.$family['name'].' '.$family['type'];
            foreach ($family['samples'] as $sample) {
                $lines[] = $family['name'].$this->formatLabels($sample['labels']).' '.$this->formatValue($sample['value']);
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<string, string> $labels
     */
    private function formatLabels(array $labels): string
    {
        if ([] === $labels) {
            return '';
        }
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = $k.'="'.$this->escapeLabel($v).'"';
        }

        return '{'.implode(',', $parts).'}';
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private function formatValue(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        return rtrim(rtrim(\sprintf('%.6F', $value), '0'), '.') ?: '0';
    }
}

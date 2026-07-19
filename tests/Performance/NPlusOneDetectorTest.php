<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\Performance\Service\NPlusOneDetector;
use PHPUnit\Framework\TestCase;

final class NPlusOneDetectorTest extends TestCase
{
    public function testDetectsRepeatedQueries(): void
    {
        $spans = [];
        for ($i = 1; $i <= 6; ++$i) {
            $spans[] = [
                'op' => 'db.sql.query',
                'description' => \sprintf('SELECT * FROM users WHERE id = %d', $i),
                'span_id' => 's'.$i,
            ];
        }

        $result = new NPlusOneDetector()->detect($spans);

        self::assertSame(1, $result['count']);
        self::assertSame(6, $result['groups'][0]['repeats']);
        self::assertContains('s1', $result['candidate_span_ids']);
    }

    public function testIgnoresBelowThreshold(): void
    {
        $spans = [
            ['op' => 'db', 'description' => 'SELECT 1', 'span_id' => 'a'],
            ['op' => 'db', 'description' => 'SELECT 2', 'span_id' => 'b'],
        ];

        $result = new NPlusOneDetector()->detect($spans);
        self::assertSame(0, $result['count']);
    }
}

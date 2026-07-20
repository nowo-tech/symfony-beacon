<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Project\Service\HumanFriendlyTokenGenerator;
use PHPUnit\Framework\TestCase;

final class HumanFriendlyTokenGeneratorTest extends TestCase
{
    public function testGenerateLabelIsAdjectiveNoun(): void
    {
        $generator = new HumanFriendlyTokenGenerator();
        $label = $generator->generateLabel();

        self::assertMatchesRegularExpression('/^[a-z]+-[a-z]+$/', $label);
    }

    public function testGenerateKeyAppendsHexSuffix(): void
    {
        $generator = new HumanFriendlyTokenGenerator();
        $key = $generator->generateKey(3);

        self::assertMatchesRegularExpression('/^[a-z]+-[a-z]+-[a-f0-9]{6}$/', $key);
        self::assertLessThanOrEqual(64, \strlen($key));
    }
}

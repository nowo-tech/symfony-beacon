<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Issues\Service\IssueStackPresenter;
use PHPUnit\Framework\TestCase;

final class IssueStackPresenterTest extends TestCase
{
    public function testOpensInnermostInAppFrame(): void
    {
        $rows = new IssueStackPresenter()->displayFrames([
            ['filename' => 'src/Kernel.php', 'in_app' => true, 'lineno' => 1],
            ['filename' => 'src/Repo.php', 'in_app' => true, 'lineno' => 158],
            ['filename' => 'vendor/pdo.php', 'in_app' => false, 'lineno' => 9],
        ]);

        self::assertCount(3, $rows);
        self::assertSame('vendor/pdo.php', $rows[0]['frame']['filename']);
        self::assertFalse($rows[0]['open']);
        self::assertSame('src/Repo.php', $rows[1]['frame']['filename']);
        self::assertTrue($rows[1]['open']);
        self::assertSame('src/Kernel.php', $rows[2]['frame']['filename']);
        self::assertFalse($rows[2]['open']);
    }

    public function testOpensInnermostWhenNoInApp(): void
    {
        $rows = new IssueStackPresenter()->displayFrames([
            ['filename' => 'a.php', 'in_app' => false],
            ['filename' => 'b.php', 'in_app' => false],
        ]);

        self::assertTrue($rows[0]['open']);
        self::assertSame('b.php', $rows[0]['frame']['filename']);
        self::assertFalse($rows[1]['open']);
    }

    public function testIgnoresNonArrayFrames(): void
    {
        self::assertSame([], new IssueStackPresenter()->displayFrames(['nope', 1]));
        self::assertSame([], new IssueStackPresenter()->displayFrames(null));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Service\ThresholdRuleWriter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ThresholdRuleWriterExtraTest extends TestCase
{
    public function testFlushAndDeleteDelegateToEntityManager(): void
    {
        $rule = new ProjectThresholdRule();
        $removed = [];
        $flushes = 0;

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $em->method('flush')->willReturnCallback(static function () use (&$flushes): void {
            ++$flushes;
        });

        $writer = new ThresholdRuleWriter($em);
        $writer->flush();
        $writer->delete($rule);

        self::assertSame([$rule], $removed);
        self::assertSame(2, $flushes);
    }
}

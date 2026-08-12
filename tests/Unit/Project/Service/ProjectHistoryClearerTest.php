<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Project\Entity\Project;
use App\Project\Service\ProjectHistoryClearer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProjectHistoryClearerTest extends TestCase
{
    public function testClearNoOpsWithoutProjectId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getConnection');

        (new ProjectHistoryClearer($em))->clear(new Project());
    }

    public function testClearDeletesTelemetryTables(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $ref = new ReflectionProperty(Project::class, 'id');
        $ref->setValue($project, 42);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(6))->method('executeStatement');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->expects(self::once())->method('clear');

        (new ProjectHistoryClearer($em))->clear($project);
    }
}

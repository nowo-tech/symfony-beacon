<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Controller;

use App\Notifications\Controller\ProjectThresholdRuleController;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProjectThresholdRuleControllerTest extends TestCase
{
    public function testAssertRuleBelongsToProject(): void
    {
        $controller = new ProjectThresholdRuleController($this->createStub(EntityManagerInterface::class));
        $method = new ReflectionMethod(ProjectThresholdRuleController::class, 'assertRuleBelongsToProject');

        $project = (new Project())->setName('Acme')->setSlug('acme');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 5);
        $other = (new Project())->setName('Other')->setSlug('other');
        (new ReflectionProperty(Project::class, 'id'))->setValue($other, 6);

        $rule = new ProjectThresholdRule();
        $rule->setProject($project);
        $method->invoke($controller, $project, $rule);

        $this->expectException(NotFoundHttpException::class);
        $method->invoke($controller, $other, $rule);
    }
}

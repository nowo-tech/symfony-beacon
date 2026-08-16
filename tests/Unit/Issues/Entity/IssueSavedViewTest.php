<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Entity;

use App\Identity\Entity\User;
use App\Issues\Entity\IssueSavedView;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;

final class IssueSavedViewTest extends TestCase
{
    public function testAccessorsPersistTrimmedNameProjectUserAndQuery(): void
    {
        $user = new User()->setEmail('saved-view@example.com');
        $project = new Project()->setName('Beacon')->setSlug('beacon');
        $view = new IssueSavedView();

        $view
            ->setUser($user)
            ->setProject($project)
            ->setName('  '.str_repeat('x', IssueSavedView::NAME_MAX_LENGTH + 5).'  ')
            ->setQueryJson(['status' => 'unresolved', 'sort' => 'last_seen']);

        self::assertNull($view->getId());
        self::assertSame($user, $view->getUser());
        self::assertSame($project, $view->getProject());
        self::assertSame(IssueSavedView::NAME_MAX_LENGTH, \strlen($view->getName()));
        self::assertSame(['status' => 'unresolved', 'sort' => 'last_seen'], $view->getQueryJson());
    }
}

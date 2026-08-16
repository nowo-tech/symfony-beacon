<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\Tour\ProductTourPage;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserUiPreferencesCoverageCloseTest extends TestCase
{
    public function testTourSeenTimestampAccessorsAndAutoCompletion(): void
    {
        $prefs = new UserUiPreferences();
        self::assertNull($prefs->getProductTourSeenAt());

        foreach (ProductTourPage::all() as $page) {
            $prefs->markTourPageSeen($page->value);
        }

        self::assertInstanceOf(DateTimeImmutable::class, $prefs->getProductTourSeenAt());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\Embeddable\UserUiPreferences;
use App\Identity\Entity\InstancePermissionTranslation;
use App\Identity\Entity\PasswordHistory;
use App\Identity\Entity\User;
use App\Identity\Tour\ProductTourPage;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

final class CoverageRegressionTest extends TestCase
{
    public function testUserUiPreferencesExposesSeenTimestampAndMarksAllPagesSeen(): void
    {
        $preferences = new UserUiPreferences();
        $seenAt = new DateTimeImmutable('2026-08-16T10:00:00+00:00');

        $preferences->markProductTourSeen($seenAt);
        self::assertSame($seenAt, $preferences->getProductTourSeenAt());

        $preferences->clearProductTourSeen();
        foreach (ProductTourPage::all() as $page) {
            $preferences->markTourPageSeen($page->value);
        }

        self::assertTrue($preferences->isProductTourSeen());
        self::assertGreaterThan(0, $preferences->getProductTourSeenAt()->getTimestamp());
    }

    public function testInstancePermissionTranslationTrimsValuesAndStartsWithoutId(): void
    {
        $translation = new InstancePermissionTranslation();

        self::assertNull($translation->getId());
        self::assertSame('es', $translation->setLocale(' ES ')->getLocale());
        self::assertSame('Manage members', $translation->setName('  Manage members  ')->getName());
        self::assertNull($translation->setDescription(' 
 ')->getDescription());
        self::assertSame('Readable description', $translation->setDescription(' Readable description ')->getDescription());
    }

    public function testPasswordHistoryStartsWithoutIdAndTracksAssignments(): void
    {
        $user = new User()->setEmail('history@example.com');
        $history = new PasswordHistory();

        self::assertNull($history->getId());
        self::assertInstanceOf(DateTimeInterface::class, $history->getCreatedAt());

        $history->setUser($user)->setPassword('hashed-secret');

        self::assertSame($user, $history->getUser());
        self::assertSame('hashed-secret', $history->getPassword());
    }
}

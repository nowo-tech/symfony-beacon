<?php

declare(strict_types=1);

namespace App\Tests\Setup;

use App\Setup\SiteBackupSecurityDefaultsGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SiteBackupSecurityDefaultsGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SiteBackupSecurityDefaultsGuard::resetCheckedFlag();
    }

    public function testDevEnvironmentAllowsLocalDefaults(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'dev',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $guard->assertProductionSecretsSafe();
        $this->addToAssertionCount(1);
    }

    public function testProdRejectsEmptySetupToken(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard('prod', '', '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SITE_SETUP_TOKEN must be set');
        $guard->assertProductionSecretsSafe();
    }

    public function testProdRejectsDocumentedLocalSetupToken(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'prod',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('documented local default');
        $guard->assertProductionSecretsSafe();
    }

    public function testProdRejectsDocumentedLocalPanelHash(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'prod',
            'unique-production-setup-token',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SITE_BACKUP_PASSWORD_HASH');
        $guard->assertProductionSecretsSafe();
    }

    public function testProdAcceptsRotatedSecrets(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'prod',
            'unique-production-setup-token',
            '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        );
        $guard->assertProductionSecretsSafe();
        $this->addToAssertionCount(1);
    }
}

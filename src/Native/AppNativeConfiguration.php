<?php

declare(strict_types=1);

namespace App\Native;

use Symfony\UX\Native\Attribute\AsNativeConfiguration;
use Symfony\UX\Native\Attribute\AsNativeConfigurationProvider;
use Symfony\UX\Native\Configuration\Configuration;
use Symfony\UX\Native\Configuration\Rule;

/**
 * Hotwire Native path configuration for iOS and Android shells.
 *
 * Served at /config/ios_v1.json and /config/android_v1.json (dynamic in dev;
 * dump with `bin/console ux:native:build-configs` for production).
 */
#[AsNativeConfigurationProvider]
final class AppNativeConfiguration
{
    #[AsNativeConfiguration('/config/ios_v1.json')]
    public function iosV1(): Configuration
    {
        return $this->sharedConfiguration();
    }

    #[AsNativeConfiguration('/config/android_v1.json')]
    public function androidV1(): Configuration
    {
        return $this->sharedConfiguration();
    }

    private function sharedConfiguration(): Configuration
    {
        return new Configuration(
            settings: [
                // Keep false until ActionCable / Mercure bridge is wired for native.
                'use_local_db' => false,
            ],
            rules: [
                // Envelope ingest is an API — never open inside the native WebView stack.
                new Rule(
                    patterns: [
                        '/api/.*/envelope/?',
                        '/api/.*/envelope',
                    ],
                    properties: [
                        'context' => 'default',
                        'presentation' => 'none',
                    ],
                ),
                // Auth flows work well as a modal sheet on mobile.
                new Rule(
                    patterns: [
                        '/(en|es)/login/?.*',
                        '/(en|es)/register/?.*',
                        '/(en|es)/logout/?.*',
                    ],
                    properties: [
                        'context' => 'modal',
                        'pull_to_refresh_enabled' => false,
                    ],
                ),
                // Default: Turbo Drive pages with pull-to-refresh.
                new Rule(
                    patterns: ['.*'],
                    properties: [
                        'context' => 'default',
                        'pull_to_refresh_enabled' => true,
                    ],
                ),
            ],
        );
    }
}

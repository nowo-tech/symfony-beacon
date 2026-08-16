import { defineConfig } from 'vitest/config';

/**
 * REQ-QA-002 TS gate: hard 100% on the includable Stimulus/shell set.
 * Browser-heavy / vendor re-exports stay excluded — see docs/COVERAGE.md.
 */
export default defineConfig({
  test: {
    environment: 'jsdom',
    setupFiles: ['assets/vitest.setup.ts'],
    include: ['assets/**/*.{test,spec}.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: 'var/coverage-js',
      include: [
        'assets/theme-boot.ts',
        'assets/controllers/**/*.{ts,js}',
        'assets/lib/thinking-orbs/presets.ts',
        'assets/lib/thinking-orbs/theme.ts',
        'assets/lib/thinking-orbs/engine/profiles.ts',
      ],
      exclude: [
        'assets/**/*.{test,spec}.ts',
        'assets/vitest.setup.ts',
        'assets/stimulus-env.d.ts',
        'assets/**/*.d.ts',
        // Type-only / re-export barrels.
        'assets/lib/thinking-orbs/types.ts',
        'assets/lib/thinking-orbs/engine/types.ts',
        'assets/lib/thinking-orbs/index.ts',
        // Canvas draw engines need a real WebGL/canvas loop; covered via E2E / manual.
        'assets/lib/thinking-orbs/engine/{core,lattice,morph,orbits,ribbon,registry}.ts',
        // Vendor Stimulus peer re-exports (no host runtime to unit-test).
        'assets/controllers/page_loader_controller.ts',
        'assets/controllers/confirm_submit_controller.ts',
        // Browser / chart / Mercure / tour / canvas surfaces — Playwright e2e.
        'assets/controllers/analytics_chart_controller.ts',
        'assets/controllers/issue_realtime_controller.ts',
        'assets/controllers/product_tour_controller.ts',
        'assets/controllers/qr_login_controller.ts',
        'assets/controllers/thinking_orb_controller.ts',
        'assets/controllers/datatable_controller.ts',
        'assets/controllers/temporary_reveal_controller.ts',
      ],
      thresholds: {
        lines: 100,
        statements: 100,
      },
    },
  },
});

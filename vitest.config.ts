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
        'assets/controllers/collapse_panel_controller.ts',
        'assets/controllers/combobox_controller.ts',
        'assets/controllers/csrf_protection_controller.ts',
        'assets/controllers/human_key_label_controller.ts',
        'assets/controllers/issue_panels_reset_controller.ts',
        'assets/controllers/menu_nested_collapse_controller.ts',
        'assets/controllers/navigate_select_controller.ts',
        'assets/controllers/password_confirm_mirror_controller.ts',
        'assets/controllers/password_toggle_controller.ts',
        'assets/controllers/temporary_reveal_controller.ts',
        'assets/lib/morphicons/index.ts',
        'assets/lib/thinking-orbs/presets.ts',
        'assets/lib/thinking-orbs/theme.ts',
        'assets/lib/thinking-orbs/engine/profiles.ts',
      ],
      exclude: [
        'assets/**/*.{test,spec}.ts',
        'assets/vitest.setup.ts',
        'assets/stimulus-env.d.ts',
        'assets/**/*.d.ts',
      ],
      thresholds: {
        lines: 100,
        statements: 100,
      },
    },
  },
});

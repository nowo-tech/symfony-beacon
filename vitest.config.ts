import { defineConfig } from 'vitest/config';

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
        'assets/lib/**/*.{ts,js}',
      ],
      exclude: [
        'assets/**/*.{test,spec}.ts',
        'assets/vitest.setup.ts',
        'assets/stimulus-env.d.ts',
        'assets/**/*.d.ts',
        // Canvas draw engines need a real WebGL/canvas loop; covered via E2E / manual.
        'assets/lib/thinking-orbs/engine/{core,lattice,morph,orbits,ribbon,registry}.ts',
      ],
    },
  },
});

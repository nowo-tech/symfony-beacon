import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';
import * as esbuild from 'esbuild';

const rootDir = path.dirname(fileURLToPath(import.meta.url));

/**
 * Compile assets/theme-boot.ts → public/build/theme-boot.js as a classic IIFE.
 * Modules are deferred (FOUC); a blocking <script> must be classic for pre-paint theme.
 */
function themeBootIife(): Plugin {
  const entry = path.join(rootDir, 'assets/theme-boot.ts');
  const outfile = path.join(rootDir, 'public/build/theme-boot.js');

  const buildIife = async (): Promise<void> => {
    await esbuild.build({
      entryPoints: [entry],
      outfile,
      bundle: true,
      format: 'iife',
      platform: 'browser',
      target: ['es2018'],
      minify: true,
      logLevel: 'silent',
    });
  };

  return {
    name: 'theme-boot-iife',
    async buildStart() {
      await buildIife();
    },
    async closeBundle() {
      await buildIife();
    },
    configureServer(server) {
      server.watcher.add(entry);
      server.watcher.on('change', (file) => {
        if (path.resolve(file) === entry) {
          void buildIife();
        }
      });
    },
  };
}

/**
 * Inside Docker, Vite always listens on 5173 (compose maps host VITE_PORT → 5173).
 * Assets are served over HTTPS via Caddy reverse_proxy (/build → vite:5173)
 * so the browser does not hit mixed-content blocks on https://localhost:9444.
 */
const listenPort = Number(process.env.VITE_LISTEN_PORT || process.env.VITE_PORT || 5173);
const publicOrigin = process.env.DEFAULT_URI || process.env.VITE_ORIGIN || '';
const hmrClientPort = Number(process.env.HTTPS_PORT || 443);

export default defineConfig({
    plugins: [
        themeBootIife(),
        tailwindcss(),
        symfonyPlugin({
            viteDevServerHostname: process.env.VITE_DEV_SERVER_HOST || 'localhost',
            stimulus: './assets/controllers.json',
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: listenPort,
        strictPort: true,
        origin: publicOrigin || undefined,
        hmr: publicOrigin
            ? {
                  protocol: 'wss',
                  host: process.env.VITE_DEV_SERVER_HOST || 'localhost',
                  clientPort: hmrClientPort,
              }
            : true,
        watch: {
            usePolling: process.env.VITE_USE_POLLING === '1',
        },
    },
    build: {
        rollupOptions: {
            input: {
                app: './assets/app.ts',
                // Kit admin shells (menus / breadcrumbs / cookie consent) — Bootstrap + layout helpers.
                'kit-admin': './assets/kit-admin.ts',
                // Nelmio Swagger UI init (CSP: no inline script on /admin/api/doc).
                'swagger-ui-boot': './assets/swagger-ui-boot.ts',
            },
        },
    },
});

import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';

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
            },
        },
    },
});

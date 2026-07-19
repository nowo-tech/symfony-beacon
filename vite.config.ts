import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        symfonyPlugin({
            viteDevServerHostname: process.env.VITE_DEV_SERVER_HOST || 'localhost',
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT || 5174),
        strictPort: true,
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

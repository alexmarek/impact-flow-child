import {readFileSync, existsSync} from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import { reloadOnChange } from '../impact-flow/assets/vite-plugins/reload-on-change.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Local WordPress URL for the dev server proxy and HMR origin.
 * Loaded from .env.local / .env so each developer can override without
 * editing source. See the parent's vite.config.js for the loader
 * implementation — the same logic is duplicated here because Vite
 * configs can't share code (they're each entry points).
 */
function loadEnvFile(filePath) {
    if (!existsSync(filePath)) return;
    for (const line of readFileSync(filePath, 'utf-8').split('\n')) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const eq = trimmed.indexOf('=');
        if (eq === -1) continue;
        const key = trimmed.slice(0, eq).trim();
        const value = trimmed.slice(eq + 1).trim();
        if (process.env[key] === undefined) {
            process.env[key] = value;
        }
    }
}

loadEnvFile(path.join(__dirname, '.env.local'));
loadEnvFile(path.join(__dirname, '.env'));

const localURL = process.env.IMPACTFLOW_LOCAL_URL || 'https://testing.local';

/**
 * File patterns that should trigger a full-page reload (CSS HMR handles
 * SCSS / JS automatically). Twig templates and dist/ output are PHP-rendered
 * so they need a reload, not a module swap.
 *
 * The parent theme's reloadOnChange plugin lives at
 * ../../impact-flow/assets/vite-plugins/reload-on-change.js
 * — imported via relative path so we don't duplicate the watcher logic.
 */
const reloadPatterns = [
    '*.php',
    'templates/**/*.php',
    'inc/**/*.php',
    'views/**/*.twig',
    'dist/**/*',
];

// https://vitejs.dev/config
export default {
    plugins: [
        reloadOnChange(reloadPatterns),
    ],

    root: '',
    base: process.env.NODE_ENV === 'development'
        ? '/'
        : '/dist/',

    // Dev defaults — explicit so a future config edit doesn't accidentally
    // enable minification in `vite dev`. The parent theme runs on 5173 by
    // default; child uses 5174 so both can run simultaneously.
    server: {
        port: 5174,
        strictPort: false,
        open: false,
        cors: true,
        host: true,
        hmr: { overlay: true },
        proxy: {
            '/': {
                target: localURL,
                changeOrigin: true,
                secure: false,
                ws: true,
            },
        },
    },

    build: {
        outDir: __dirname + '/dist',
        emptyOutDir: true,
        manifest: true,
        target: 'es2018',
        minify: true,
        cssCodeSplit: true,
        sourcemap: true,
        rollupOptions: {
            input: {
                child: __dirname + '/child-theme.js',
            },
            output: {
                format: 'es',
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/js/[name].[hash].js',
                assetFileNames: (chunkInfo) => {
                    if (chunkInfo.name.includes('child') || chunkInfo.name.includes('main')) {
                        return `[name].css`;
                    }
                    return `chunks/css/[name].[hash][extname]`;
                },
            },
        },
    },

    resolve: {},
};

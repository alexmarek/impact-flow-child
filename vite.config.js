import path from 'path';
import {fileURLToPath} from 'url';
import { reloadOnChange } from '../impact-flow/assets/vite-plugins/reload-on-change.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * Local WordPress URL for the dev server proxy and HMR origin.
 * Override per-developer via a .env.local or by editing this value directly.
 */
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

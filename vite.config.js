// View your website at your own local server
// for example http://vite-php-setup.test

// http://localhost:3000 is serving Vite on development
// but accessing it directly will be empty

// IMPORTANT image urls in CSS works fine
// BUT you need to create a symlink on dev server to map this folder during dev:
// ln -s {path_to_vite}/src/assets {path_to_public_html}/assets
// on production everything will work just fine

//import vue from '@vitejs/plugin-vue';
import { execSync } from "child_process";
import VitePluginBrowserSync from 'vite-plugin-browser-sync';
import liveReload from 'vite-plugin-live-reload';

// set local serving url
const localURL = 'https://testing.local';


// https://vitejs.dev/config
export default {

    plugins: [
        //vue(),
        liveReload(['*.php','templates/**/*.php','inc/**/*.php','assets/**/*.scss','assets/**/*.js','dist/**/*.js','dist/**/*.css'],{ root: process.cwd() }),
        VitePluginBrowserSync({
            mode: 'snippet',
            bs: {
                ui: {

                    port: 3000
                },
                notify: true,

            }
        }),
        CustomHmr(),

    ],

    // config
    root: '',
    base: process.env.NODE_ENV === 'development'
        ? '/'
        : '/dist/',

    build: {
        // ssrManifest:true,
        // ssr:true,
        // output dir for production build
        outDir: __dirname+ '/dist',
        emptyOutDir: true,

        // emit manifest so PHP can find the hashed files
        manifest: true,

        // esbuild target
        target: 'es2018',

        // our entry
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
                }


            },
        },

        minify: true,
        cssCodeSplit: true,
        write: true
    },

    server: {
        proxy: {
            '/': {
                target: localURL,
                changeOrigin: true,
                secure: false,
                ws: true,
                // rewrite: (path) => path.replace(/^\//, '')
            }
        },
        // required to load scripts from custom host
        cors: true,
        host:true,
        open:true,
        origin: localURL,
        port:3000,
        hmr: true,
    },

    resolve: {

    }
}



function CustomHmr() {
    return {
        name: 'custom-hmr',
        enforce: 'post',
        // HMR
        apply:'serve',
        handleHotUpdate({ file, server }) {
            if (file.endsWith('.scss') || file.endsWith('.js')) {
                execSync("npm run prod");
                server.restart();

            }
        },
        closeBundle() {
        }
    }
}
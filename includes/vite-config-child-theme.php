<?php
define('VITE_BUILD_FOLDER_URI_CHILD_THEME', get_stylesheet_directory_uri() . '/dist');
define('VITE_BUILD_FOLDER_PATH_CHILD_THEME', get_stylesheet_directory() . '/dist');

/**
 * Child-theme asset enqueue.
 *
 * In dev mode (VITE running on the configured host, detected the same way
 * as the parent theme), enqueue the live child-theme.js source from the
 * Vite dev server so the browser sees HMR updates without a manual
 * rebuild. Otherwise enqueue the compiled dist/ output as before.
 */
add_action( 'wp_enqueue_scripts', function() {

    // Bail if the parent helper isn't available (parent theme not loaded/activated).
    if ( ! function_exists( 'impactflow_get_content_from_file' ) ) {
        return;
    }

    // Mirror the parent's dev-mode detection so a single ?vite_dev=1
    // query param flips both parent and child into dev mode together.
    $isDev = ( defined('IMPACTFLOW_VITE_DEV') && IMPACTFLOW_VITE_DEV )
        || ! empty( $_GET['vite_dev'] );

    $entry = 'child-theme.js';

    if ( $isDev ) {
        $devUrl = rtrim( getenv('IMPACTFLOW_VITE_HOST') ?: 'http://localhost:5174', '/' );

        wp_enqueue_script_module('@vite/client-child', $devUrl . '/@vite/client', [], null);
        wp_enqueue_script_module(
            'app-child-script-dev',
            $devUrl . '/' . $entry,
            [ '@vite/client-child', 'jquery', 'app-parent-script-dev' ],
            null
        );
        return;
    }

    // read manifest.json to figure out what to enqueue
	$manifest = json_decode( impactflow_get_content_from_file( VITE_BUILD_FOLDER_PATH_CHILD_THEME . '/.vite/manifest.json' ), true );

    if (is_array($manifest)) {

        if (isset($manifest[$entry])) {
	        $version = filemtime( VITE_BUILD_FOLDER_PATH_CHILD_THEME . '/.vite/manifest.json' );
            // enqueue CSS files
            $count=0;
            if(isset($manifest[$entry]['css']) && count($manifest[$entry]['css'])>0) {

                foreach ($manifest[$entry]['css'] as $css_file) {
                    $count++;

	                wp_enqueue_style( 'app-child-style', VITE_BUILD_FOLDER_URI_CHILD_THEME . '/' . $css_file, array( 'app-parent-style-build' ), $version );
                }
            }
            // enqueue main JS file


            $js_file = $manifest[$entry]['file'];
            if ( $js_file!='') {

	            wp_enqueue_script( 'app-child-script-main', VITE_BUILD_FOLDER_URI_CHILD_THEME . '/' . $js_file, array(
		            'jquery',
		            'app-parent-script-main'
	            ), $version, true );
            }

        }

    }
});

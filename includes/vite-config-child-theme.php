<?php
define('VITE_BUILD_FOLDER_URI_CHILD_THEME', get_stylesheet_directory_uri() . '/dist');
define('VITE_BUILD_FOLDER_PATH_CHILD_THEME', get_stylesheet_directory() . '/dist');

// enqueue hook
add_action( 'wp_enqueue_scripts', function() {


    // Bail if the parent helper isn't available (parent theme not loaded/activated).
    if ( ! function_exists( 'impactflow_get_content_from_file' ) ) {
        return;
    }

    // read manifest.json to figure out what to enqueue
	$manifest = json_decode( impactflow_get_content_from_file( VITE_BUILD_FOLDER_PATH_CHILD_THEME . '/.vite/manifest.json' ), true );

    if (is_array($manifest)) {

        if (isset($manifest['child-theme.js'])) {
	        $version = filemtime( VITE_BUILD_FOLDER_PATH_CHILD_THEME . '/.vite/manifest.json' );
            // enqueue CSS files
            $count=0;
            if(isset($manifest['child-theme.js']['css']) && count($manifest['child-theme.js']['css'])>0) {

                foreach ($manifest['child-theme.js']['css'] as $css_file) {
                    $count++;

	                wp_enqueue_style( 'app-child-style', VITE_BUILD_FOLDER_URI_CHILD_THEME . '/' . $css_file, array( 'app-parent-style-build' ), $version );
                }
            }
            // enqueue main JS file


            $js_file = $manifest['child-theme.js']['file'];
            if ( $js_file!='') {

	            wp_enqueue_script( 'app-child-script-main', VITE_BUILD_FOLDER_URI_CHILD_THEME . '/' . $js_file, array(
		            'jquery',
		            'app-parent-script-main'
	            ), $version, true );
            }

        }

    }
});
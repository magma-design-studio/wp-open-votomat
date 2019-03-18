<?php
add_action( 'wp_enqueue_scripts', 'wpov_twentyfourteen_assets' );

function wpov_twentyfourteen_assets() {
    if(is_admin_bar_showing()) {
        wp_enqueue_style( 'wpov-admin-bar', WPOV__PLUGIN_DIR_URL . '/backend_assets/css/wpov-admin-bar.css' );
    }

    wp_enqueue_style( 'normalize', WPOV__PLUGIN_THEME_DIR_URL . '/assets/css/normalize.css' );
    wp_enqueue_style( 'foundation', WPOV__PLUGIN_THEME_DIR_URL . '/assets/css/foundation.css' );

    wp_enqueue_style( 'gh-fork-ribbon', '//cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.1.1/gh-fork-ribbon.min.css' );

    wp_enqueue_script( 'modernizr', '//cdnjs.cloudflare.com/ajax/libs/modernizr/2.7.1/modernizr.min.js', array(  ), '2.7.1', true );
    wp_enqueue_script( 'wpov-jquery', '//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.0/jquery.js', array(  ), '2.1.0', true );
    wp_enqueue_script( 'foundation', '//cdnjs.cloudflare.com/ajax/libs/foundation/5.2.2/js/foundation.min.js', array(  ), '5.2.2', true );
    wp_enqueue_script( 'foundation-tooltip', '//cdnjs.cloudflare.com/ajax/libs/foundation/5.2.2/js/foundation/foundation.tooltip.js', array(  ), '5.2.2', true );

    wp_add_inline_script( 'foundation-tooltip', '<script> $(document).foundation(); </script>' );
}
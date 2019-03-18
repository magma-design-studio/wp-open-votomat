<?php
add_action( 'wp_enqueue_scripts', 'wpov_twentynineteen_assets' );

function wpov_twentynineteen_assets() {
    wp_enqueue_style( 'wpov-bootstrap', '//stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css', array(  ), '4.3.1' );

    wp_enqueue_script( 'wpov-jquery-slim', 'https://code.jquery.com/jquery-3.3.1.slim.min.js', array(  ), '3.3.1', true );
    wp_enqueue_script( 'wpov-popper.js', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js', array(  ), '1.14.7', true );
    wp_enqueue_script( 'wpov-bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', array(  ), '4.3.1', true );
    
    wp_enqueue_script( 'wpov-bootstrap-sortable-js', WPOV__PLUGIN_THEME_DIR_URL . '/bower_components/bootstrap-sortable/Scripts/bootstrap-sortable.js', array(  ), '2.0.1', true );
    
    wp_enqueue_style( 'wpov-bootstrap-sortable-css', WPOV__PLUGIN_THEME_DIR_URL . '/bower_components/bootstrap-sortable/Contents/bootstrap-sortable.css', array(  ), '2.0.1' );    
    
    
    wp_enqueue_style( 'octicons', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css', array(  ), '8.3.0', true );
    
    wp_enqueue_style( 'wpov-twentinineteen-app-css', WPOV__PLUGIN_THEME_DIR_URL . '/assets/css/app.css' );
    wp_enqueue_script( 'wpov-twentinineteen-app-js', WPOV__PLUGIN_THEME_DIR_URL . '/assets/js/app.js', array(  ), '3.3.1', true );    
}


add_filter( 'wpov_vote_button_class', 'wpov_vote_button_class' );

function wpov_vote_button_class($classes) {
    $classes = array(
        'approve' => 'btn-success',
        'neutral' => 'btn-warning',
        'disapprove' => 'btn-danger',
    );
    return $classes;
}
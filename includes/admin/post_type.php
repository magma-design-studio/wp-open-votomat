<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_post_type') ) :

abstract class wpov_admin_post_type {
    function make_post_array_select_options($posts) {
        $out = array();
        foreach($posts as $post) {
            $out[$post->get_id()] = sprintf('#%s %s', $post->get_id(), $post->title());
        }
        return $out;
    }
    
    function get_posts_options($type) {
        $out = wpov_get_posts(array(
            'post_type' => 'wpov-'.$type,
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));
        
        return $out;            
    }    

}

endif;
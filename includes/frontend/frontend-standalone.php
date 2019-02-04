<?php

if(!class_exists('wpov_frontend_standalone')) :

class wpov_frontend_standalone extends wpov_frontend {
    function __construct() {
        $this->hooks();
    }
    
    function hooks() {
        define('WPOV__HOME_URL', get_bloginfo('url'));
        
        //add_action( 'wp_enqueue_scripts', array($this, 'unregister_assets'), 50 );
        
        add_filter('template_directory', array($this, 'provide_wpov_template_directory'), 1000, 3);
        add_filter('stylesheet_directory', array($this, 'provide_wpov_stylesheet_directory'), 1000, 3);
        
        add_filter('template_directory_uri', array($this, 'provide_wpov_template_directory'), 1000, 3);
        add_filter('stylesheet_directory_uri', array($this, 'provide_wpov_stylesheet_directory_uri'), 1000, 3);
        
        //add_action('pre_get_posts', array($this, 'get_posts'));
        add_action('wpov_post_query_store_user_vote', array($this, 'post_store_user_vote'));
        

        add_filter( 'timber_context', array( $this, 'add_to_context' ) );
    }
    
    function add_to_context($context) {
		$context['wpov_footer_menu'] = new Timber\Menu('wpov_footer');
        return $context;
    }
    
    function unregister_assets() {
        global $wp_styles;
        
        $allowed_style = array('admin-bar');
        foreach($wp_styles->queue as $i => $style) {
            if(!in_array($style, $allowed_style)) {
                unset($wp_styles->queue[$i]);
            }
        }
    }
    
    function post_store_user_vote($post_query) {        
        
        $post_query = wp_parse_args( $post_query, array(
            'vote' => 'neutral',
            'voting' => 0,
            'count_twice' => 0,
            'question' => 0,
            'action' => '',
            'nonce' => ''
        ) );
        
        if( wp_verify_nonce( $post_query['nonce'], $post_query['action'].'_' . $post_query['voting'] . ':' . $post_query['question'] ) ) {
            global $post;
            
            $post_query['count_twice'] = (is_numeric($post_query['count_twice']) and $post_query['count_twice'] > 0);
            $post_query['vote'] = in_array($post_query['vote'], array('approve', 'neutral', 'disapprove')) ? $post_query['vote'] : 'neutral';
                        
            $voting = wpov_get_voting($post_query['voting']);
            
            $voter = wpov_get_current_voter();
            
            $voter->store_vote($post_query['voting'], $post_query['question'], $post_query['vote'], $post_query['count_twice']);
            
            $question = $voting->question();
            $next_question = $question->next_question();
            
            if($next_question) {
                $redirect = $next_question->link();
            } else {
                $redirect = $voting->result_link();
            }
            
            wp_redirect( $redirect, 302 );
            exit;
        } else {
            die('Hack!');
        }
    }
    
    function get_posts($query) {
        if($query->is_main_query() and $question = get_query_var( 'wpov-question' ) and get_query_var( 'post_type' ) == 'wpov-voting') {
            $query->set('post_type', 'wpov-voting');
            if(is_numeric($question)) {
                $query->set('post__in', array($question));
            } elseif(is_string($question)) {
                $query->set('post_name__in', array($question));
            } else {
                $query->set('post__in', array(0));
            }
        }
        
    }
    
    function provide_wpov_template_directory($template_dir, $template, $theme_root) {
        return WPOV__PLUGIN_THEME_DIR;
    }
    
    function provide_wpov_stylesheet_directory($template_dir, $template, $theme_root) {
        $stylesheet = get_stylesheet();
        return sprintf('%s/%s', WPOV__PLUGIN_THEME_DIR, $stylesheet);
    }    
    
    function provide_wpov_template_directory_uri($template_dir, $template, $theme_root) {
        return WPOV__PLUGIN_THEME_DIR_URL;
    } 
    
    function provide_wpov_stylesheet_directory_uri($template_dir, $template, $theme_root) {
        $stylesheet = get_stylesheet();
        return sprintf('%s/%s', WPOV__PLUGIN_THEME_DIR, $stylesheet);
    }     
}

wpov()->frontend_standalone = new wpov_frontend_standalone();

endif;
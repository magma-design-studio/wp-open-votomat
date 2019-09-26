<?php

if(!class_exists('wpov_frontend_standalone')) :

class wpov_frontend_standalone extends wpov_frontend {
    protected $default_wpov_theme_files = array();
    
    function __construct() {
        $this->default_wpov_theme_files = apply_filters('wpov_default_wpov_theme_files', array(
            'footer.php',
            'functions.php',
            'header.php',
            'home.php',
            'index.php',
            'page.php',
            'style.css',            
        ));
        
        $this->hooks();
    }
    
    function hooks() {
        define('WPOV__HOME_URL', get_bloginfo('url'));
        
        //add_action( 'wp_enqueue_scripts', array($this, 'unregister_assets'), 50 );
        
        add_filter('template_directory', array($this, 'provide_wpov_template_directory'), 1000, 3);
        add_filter('stylesheet_directory', array($this, 'provide_wpov_stylesheet_directory'), 1000, 3);
        
        add_filter('template_directory_uri', array($this, 'provide_wpov_template_directory'), 1000, 3);
        add_filter('stylesheet_directory_uri', array($this, 'provide_wpov_stylesheet_directory_uri'), 1000, 3);
        
        foreach($this->default_wpov_theme_files as $file) {
            $file = preg_replace('/(\.[^\.]+)$/', null, $file);
            add_filter(sprintf('%s_template', $file), array($this, 'provide_wpov_template_wptheme_directory'), 1000, 3);
        }
        
        add_action('setup_theme', array($this, 'prepare_provide_wpov_template_wptheme_directory_functions'));
        add_action('after_setup_theme', array($this, 'provide_wpov_template_wptheme_directory_functions'));
        
        add_action('pre_get_posts', array($this, 'get_posts'));
        //add_action('posts_clauses', array($this, 'posts_clauses'), 10, 2);
        
        add_action('wpov_post_query_store_user_vote', array($this, 'post_store_user_vote'));

        add_filter( 'timber_context', array( $this, 'add_to_context' ) );
        
        add_filter( 'wp_title', array( $this, 'wp_title' ), 10, 3 );
        
        add_filter( 'pre_option_show_on_front', array( $this, 'override_option_show_on_front' ), 10, 3 );
    }
    
    function override_option_show_on_front($pre_option, $option, $default) {
        return 'posts';
    }
    
    function wp_title($title, $sep, $seplocation) {
        
        if(in_array(get_query_var( 'post_type' ), array('wpov-voting', 'wpov-question')) and !is_home()) {
            
            if(get_query_var( 'wpov-compare' )) {
                $_title = __('Compare', WPOV__PLUGIN_NAME_SLUG); 
            } elseif(get_query_var( 'wpov-result' )) {
                $_title = __('Results', WPOV__PLUGIN_NAME_SLUG); 
            } elseif(is_page()) {
                global $post;    
                
                $voting = wpov_get_post($post);
                $question = $voting->question();
                
                $_title = sprintf('%s %d', __('Question', WPOV__PLUGIN_NAME_SLUG), $question->question_index_readable());
            }
            
            
            $title = sprintf('%s › %s', $_title, trim($title)); 
        }
        
        return $title;            
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
        $query->set('_wpov_is_frontpage', false);
        if($query->is_main_query() and is_home()) {
            $query->set('post_type', 'wpov-voting');
            //$query->set('no_found_rows', true);
            /*
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'relation' => 'AND',
                    'date_from' => array(
                        'key'     => '_voting_period_from',
                        'value'   => date('U'),
                        'compare' => '<=',
                        'type'    => 'NUMERIC'
                    ),
                    'date_to' => array(
                        'key'     => '_voting_period_to',
                        'value'   => date('U'),
                        'compare' => '>',
                        'type'    => 'NUMERIC'
                    ),                      
                ),
                array(
                    'date_to' => array(
                        'key'     => '_voting_period_to',
                        'value'   => date('U'),
                        'compare' => '=<',
                        'type'    => 'NUMERIC'
                    ),                        
                    array(
                        'key'     => '_voting_keep_online',
                        'value'   => 'on',
                        'compare' => '=',
                    ),                      
                ),
                array(
                    'date_from' => array(
                        'key'     => '_voting_period_from',
                        'value'   => date('U'),
                        'compare' => '>',
                        'type'    => 'NUMERIC'
                    ),                        
                    array(
                        'key'     => '_voting_before_live_description',
                        'value'   => '',
                        'compare' => '!=',
                    ),
                ),
                array(
                    'date_to' => array(
                        'key'     => '_voting_period_to',
                        'value'   => date('U'),
                        'compare' => '=<',
                        'type'    => 'NUMERIC'
                    ),                        
                    array(
                        'key'     => '_voting_keep_online',
                        'value'   => 'on',
                        'compare' => '!=',
                    ), 
                    array(
                        'key'     => '_voting_after_live_description',
                        'value'   => '',
                        'compare' => '!=',
                    ),                    
                ),                
            ));*/

            $query->set('orderby', array('date_from' => 'DESC', 'date_to' => 'DESC'));
            
            $query->set('_wpov_is_frontpage', true);
        }
    }
    
    // merge joins
    function posts_clauses($pieces, $query) {
        if($query->is_main_query() and is_home()) {
            global $wpdb;
            $keys = array();
            print_r($query->meta_query->queries);
            foreach ( $query->meta_query->queries as $key => $meta_query ) {
                if(!is_array($meta_query)) { continue; }
                foreach($meta_query as $query) {
                    if(!is_array($query)) { continue; }
                    $keys[] = $query['key'];
                }
            }
            
            $keys = array_unique($keys);
            $translated_keys = array();
            $index = 1;
            foreach($keys as $i => $key) {
                if(preg_match_all("/(wp_postmeta|mt)(\d+)?(\.meta_key = '$key')/", $pieces['where'], $matches, PREG_SET_ORDER)) {
                    foreach($matches as $match) {
                        if($match[1] == 'wp_postmeta' or $i == 0) {
                            $new_key = 'wp_postmeta';
                            $old_key = ($match[1] == 'wp_postmeta' ? $match[1] : 'mt' . $match[2]);
                        } else {
                            $new_key = 'mt' . $index;
                            $old_key = 'mt' . $match[2];
                            $index++;
                        }
                        $translated_keys[$old_key] = $new_key;                        
                    }
                }
            }
            
            foreach($pieces as $piece) {
                $piece = str_replace(array_keys($translated_keys), $translated_keys, $piece);
            }

        }
        return $pieces;
    }

    function provide_wpov_template_wptheme_directory($template, $type, $templates) {
        $theme = wp_get_theme();
        $_templates = array_reverse($templates);
        
        foreach($_templates as $_template) {
            $theme_wpov_dir_file = sprintf('%s/%s/%s', $theme->get_template_directory(), WPOV__PLUGIN_NAME_SLUG, $template);
            if(file_exists($theme_wpov_dir_file)) {
                return $theme_wpov_dir_file;
            }
        }
        
        return $template;
    }
    
    protected $sub_functions = false;
    function prepare_provide_wpov_template_wptheme_directory_functions() {
        if ( ! wp_installing() || 'wp-activate.php' === $pagenow ) {
            
            
            $theme = wp_get_theme();
            $sub_functions = '/wpov/functions.php';

            if ( file_exists( $theme->get_template_directory() . $sub_functions ) ) {
                wp_installing(true); // override functions.php
                $this->sub_functions = $theme->get_template_directory() . $sub_functions;
            }
        }         
    }
        
    function provide_wpov_template_wptheme_directory_functions() {
        if ( $this->sub_functions ) {
            wp_installing(false); // override functions.php
            $pagenow = $this->pagenow;
            include( $this->sub_functions );
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
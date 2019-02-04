<?php

if(!class_exists('wpov_frontend')) :

class wpov_frontend {
    function __construct() {
        add_action( 'wp_enqueue_scripts', array($this, 'assets'), 100 );
        
        add_action( 'wp', array($this, 'provide_save_action') );
        //add_action( 'wp', array($this, 'set_globals') );
        add_action( 'wp', array($this, 'maybe_complete_voter_voting') );
        
		add_action( 'admin_bar_menu', array($this, 'add_admin_bar_edit_menu'), 90 );
        
    }
    
    function assets() {
        if(is_admin_bar_showing()) {
            wp_enqueue_style( 'wpov-admin-bar', WPOV__PLUGIN_DIR_URL . '/backend_assets/css/wpov-admin-bar.css' );
        }
        
        wp_enqueue_style( 'foundation', WPOV__PLUGIN_THEME_DIR_URL . '/assets/css/foundation.css' );
        wp_enqueue_style( 'normalize', WPOV__PLUGIN_THEME_DIR_URL . '/assets/css/normalize.css' );
        
        wp_enqueue_style( 'gh-fork-ribbon', '//cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.1.1/gh-fork-ribbon.min.css' );
        
        wp_enqueue_script( 'modernizr', '//cdnjs.cloudflare.com/ajax/libs/modernizr/2.7.1/modernizr.min.js', array(  ), '2.7.1', true );
        wp_enqueue_script( 'wpov-jquery', '//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.0/jquery.js', array(  ), '2.1.0', true );
        wp_enqueue_script( 'foundation', '//cdnjs.cloudflare.com/ajax/libs/foundation/5.2.2/js/foundation.min.js', array(  ), '5.2.2', true );
        wp_enqueue_script( 'foundation-tooltip', '//cdnjs.cloudflare.com/ajax/libs/foundation/5.2.2/js/foundation/foundation.tooltip.js', array(  ), '5.2.2', true );
        
        wp_add_inline_script( 'foundation-tooltip', '<script> $(document).foundation(); </script>' );
        
    }
    
    function add_admin_bar_edit_menu($wp_admin_bar) {
        global $tag, $wp_the_query, $user_id, $wpov_post_question;
        if ( !is_admin() ) {
            if ( $wpov_post_question instanceof wpov_question 
                && ( $post_type_object = get_post_type_object( $wpov_post_question->get('post_type') ) )
                && current_user_can( 'edit_post', $wpov_post_question->get_id() )
                && $post_type_object->show_in_admin_bar
                && $edit_post_link = get_edit_post_link( $wpov_post_question->get_id() ) )
            {
                $wp_admin_bar->add_menu( array(
                    'id' => 'edit_question',
                    'title' => $post_type_object->labels->edit_item,
                    'href' => $edit_post_link,
                ) );  
            }
        }
    }
    
    
    function provide_save_action() {
        if(empty($_POST)) {
            return;
        }
        
        if(isset($_POST['action'])) {
            do_action('wpov_post_query_' . $_POST['action'], $_POST);
        }
    }
    
    function set_globals() {
        global $post, $wpov_post_voting;

        $_post = wpov_get_post($post);
        
        if($_post instanceof wpov_voting) {
            $GLOBALS['wpov_post_voting'] = $_post;
        }
    }
    
    function maybe_complete_voter_voting() {
        global $wpov_post_voting;
        $current_voter = wpov_get_current_voter();
        if($wpov_post_voting) {
            if($wpov_post_voting->count_questions() == $current_voter->count_votes($wpov_post_voting->get_id(), false)) {
                $current_voter->set_voting_completed($wpov_post_voting->get_id());
            }
        }

    }
}

wpov()->frontend = new wpov_frontend();

endif;
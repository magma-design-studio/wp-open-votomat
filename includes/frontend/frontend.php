<?php

if(!class_exists('wpov_frontend')) :

class wpov_frontend {
    function __construct() {        
        add_action( 'wp', array($this, 'provide_save_action') );
        //add_action( 'wp', array($this, 'set_globals') );        
        add_action( 'wp', array($this, 'maybe_complete_voter_voting') );
        
		add_action( 'admin_bar_menu', array($this, 'add_admin_bar_edit_menu'), 90 );
        
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
        if(!in_array(get_query_var( 'post_type' ), array('wpov-voting', 'wpov-question')) or get_query_var( '_wpov_is_frontpage' )) {
            return;
        }

        global $wpov_post_voting;
        $current_voter = wpov_get_current_voter();
        if($wpov_post_voting and $current_voter) {
            if($wpov_post_voting->count_questions() == $current_voter->count_votes($wpov_post_voting->get_id(), false)) {
                $current_voter->set_voting_completed($wpov_post_voting->get_id());
            }
        }

    }
}

wpov()->frontend = new wpov_frontend();

endif;
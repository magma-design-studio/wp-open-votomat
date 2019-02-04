<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_voter_current') ) :

class wpov_voter_current extends wpov_voter  {
    function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
                
        $this->set('user_db_id', $_SESSION['user_db_id']);
        
        //if($this->count_votes())
        
    }
    
    function set($key, $value) {
        $this->{$key} = $value;
    }
    
    function get($key, $default = null) {
        if(isset($this->{$key})) {
            return $this->{$key};
        }
        return $default;
    }    
    
    function get_session_id() {
        return session_id();
    }
    
    function unique_id() {
        return sprintf('%s:%s', $this->get_session_id(), time());
    }
    
    function wpov_uniqid_real() {
        global $wpdb;
        $uniqid = wpov_uniqid_real();
                
        if($wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$uniqid'")) {
            return wpov_uniqid_real();
        } else {
            return $uniqid;
        }
    }
    
    function create_user_db_profile() {
        $user_db_id = $this->get_user_db_id();

        if($user_db_id and wpov_get_voter($user_db_id)) {
            return $user_db_id;
        }
        
        
        $post_id = wp_insert_post( array(
            'post_type' => 'wpov-user-vote',
            'post_title' => $this->unique_id(),
            'post_status' => 'internal',
            'post_name' => $this->wpov_uniqid_real()
        ) );
        
        $_SESSION['user_db_id'] = $post_id;
        
        $this->set_user_db_id($post_id);
        return $post_id;
    }
    
    function store_vote($voting = false, $question = false, $vote = 'neutral', $count_twice = false) {
        if(!$voting or !$question) {
            return;
        }
        
        $post_id = $this->create_user_db_profile();

        $post = get_post($post_id);
        
        $votes = $this->get_votes();
        
        if(empty($votes[$voting])) $votes[$voting] = array();
        
        $votes[$voting][$question] = array(
            'vote' => $vote,
            'count_twice' => $count_twice,
            'id' => sprintf('%d_%d_%s_%s', $voting, $question, $vote, ($count_twice ? 'twice' : null))
        );

        $this->set('votes', $votes);
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => maybe_serialize($votes)
        ));
        
    }    
    
}

wpov()->current_voter = new wpov_voter_current();


endif;
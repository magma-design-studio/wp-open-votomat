<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_voter_current') ) :

class wpov_voter_current extends wpov_voter  {
    function __construct() {   
        $this->set('user_db_id', isset($_SESSION['user_db_id']) ? $_SESSION['user_db_id'] : false);
        
        if(get_post_status( $this->get('user_db_id' ) ) === false) {
            $this->set('user_db_id', false);
        }

        $this->get_db_votes();
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
            'post_status' => 'processing',
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
        );

        $this->set('votes', $votes);
        $meta_key = "_wpov_voting_{$voting}_question_{$question}";
        $meta_value = $vote;
        if($count_twice) {
            $meta_value .= ':twice';
        }
        
        $prev_vote = get_post_meta( $post_id, $meta_key, true );

        
        if ( ! add_post_meta( $post_id, $meta_key, $meta_value, true ) ) { 
           update_post_meta( $post_id, $meta_key, $meta_value );
        }       
        
        delete_transient( $this->wpov_voter_votes_transient_key );        
        
        $_voting = wpov_get_voting($voting);
        $status = $_voting->publication_status_array();
        
        // count up if online
        if($status['is_live']) {


            $meta_key = "_wpov_counter_question_{$question}_{$vote}";
            $meta_value = intval(get_post_meta($voting, $meta_key, true));
            $meta_value++;

        
            if ( ! add_post_meta( $voting, $meta_key, $meta_value, true ) ) { 
               update_post_meta( $voting, $meta_key, $meta_value );
            } 

            // count down previous vote
            if($prev_vote and $prev_vote != $meta_value) {
                $prev_meta_key = "_wpov_counter_question_{$question}_{$prev_vote}";
                $prev_meta_value = intval(get_post_meta($voting, $prev_meta_key, true));
                if($prev_meta_value > 0) {
                    
                    if ( ! add_post_meta( $voting, $prev_meta_key, ($prev_meta_value-1), true ) ) { 
                       update_post_meta( $voting, $prev_meta_key, ($prev_meta_value-1) );
                    }  
                }
            }        
        }

        wp_update_post( array(
            'ID' => $post_id,
            'post_modified' => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 )
        ) );

    }    
    
}

endif;
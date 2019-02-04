<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_voter') ) :

class wpov_voter {
    protected $_tmp_voting_votes = array();
    
    function __construct($args) {
        if($args instanceof wpov_voter) {
            return $args;
        }
        
        if(is_numeric($args)) {
            $args = array();
            $args['post__in'] = array($args);
        }
        
        $post_type = 'wpov-user-vote';
        $post_status = 'internal';
        
        $args = wp_parse_args( $args, array(
            'post_type' => $post_type,
            'post_status' => $post_status
        ) );        
        
        if($args['post_type'] != $post_type) {
            return false;
        }
        
        $post = new WP_Query($args);
        
        if(empty($post->post)) {
            return $this;
        }
                
        $post = $post->post;
        $this->set('_post', maybe_unserialize($post->post_content));
        $this->set('votes', maybe_unserialize($post->post_content));
        $this->set_user_db_id($post->post_title);
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
    
    function get_user_db_id() {
        return $this->get('user_db_id', false);
    }    
    
    function set_user_db_id($id) {
        //@todo get session
        $this->set('user_db_id', $id);
    }    
        
    function get_votes() {

        if($votes = $this->get('votes', false)) {
            $content = $votes;
        } else {
            $post = get_post($this->get_user_db_id());
            $content = maybe_unserialize($post->post_content);          
            $this->set('votes', $content);
        }

        
        if(is_array($content)) {
            return $content;
        }
        
        return array();
    }
    
    function get_vote($voting = false, $question = false) {
        $votes = $this->get_votes();
        
        if(empty($votes[$voting][$question])) {
            return array(
                'vote' => false,
                'count_twice' => false
            );
        }
        
        return $votes[$voting][$question];
    }
    
    function count_votes($voting = false, $count_double_rating=true) {
        $count = 0;
        $votes = $this->get_votes();

        if(empty($votes[$voting])) {
            return 0;
        }
                
        foreach($votes[$voting] as $vote) {
            if(!empty($vote['count_twice']) and $count_double_rating) {
                $count++;
            }
            $count++;
        }
        
        return $count;
    }
    
    function set_voting_completed($voting = false) {
        $votes = $this->get_votes();
        
        $votes[$voting]['completed'] = $voting.'_completed';
        
        wp_update_post(array(
            'ID' => $this->get_user_db_id(),
            'post_content' => maybe_serialize($votes)
        ));
    }
        
    function result_public_link($voting = false) {        
        $post = wpov_get_voter($this->get_user_db_id());
        
        global $wpov_post_voting;
                
        return sprintf('%sresult/%s', $wpov_post_voting->link(), $post->post_name);
    }    
    
}


endif;
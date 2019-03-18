<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_question') ) :

class wpov_question extends wpov_api {
    public function question() {
        return $this->content();
    }       
    
    public function votings($type = 'id') {
        global $wpdb;
        
        $posts = $wpdb->get_col("
            SELECT 
                post_id
            FROM
                $wpdb->postmeta
            WHERE
                meta_key = '_voting_questions' AND
                meta_value LIKE '%\"{$this->get_id()}\"%'
        ");
        
        if($type == 'object') {
            if(!empty($posts)) {
                $posts = wpov_get_votings(array(
                    'post__in' => $posts
                ));                
            } else {
                return array();
            }
        }
            
        return $posts;
    }
    
    public function is_in_voting($id = false) {
        return in_array($id, $this->votings());
    }
    
    public function voting($_post = false) {
        global $post;
        if($_post) {
            $post = $_post;
        } elseif($post instanceof WP_Post) {
            $post = $post;
        } else {
            //get_query_var( 'wpov-question' )) {
        }
        
        return wpov_get_voting($post);
        
    }
    
    public function link() {
        return sprintf('%squestion/%s/', $this->voting()->link(), $this->question_index_readable());
    }    
    
    public function question_index() {
        $questions = $this->siblings(true);
        
        return array_search($this->get_id(), $questions);
    }
    
    public function question_index_readable() {
        return ($this->question_index()+1);
    }
    
    public function siblings($raw = false) {
        return $this->voting()->questions($raw);
    }
    
    public function next_question($raw = false) {
        $questions = $this->siblings($raw);

        $nextIndex = $this->question_index() + 1;

        if(isset($questions[$nextIndex])) {
            return $questions[$nextIndex];
        } 
        
        return false;
    }
    
    public function previous_question($raw = false) {
        $questions = $this->siblings($raw);
        $nextIndex = $this->question_index() - 1;
        
        if(isset($questions[$nextIndex])) {
            return $questions[$nextIndex];
        } 
        
        return false;
    }    
    
    public function nonce_store_user_vote() {
        return wp_create_nonce(sprintf('store_user_vote_' . $this->voting()->get_id() . ':' . $this->get_id() ));
    }
}

endif;
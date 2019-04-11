<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_party') ) :

final class wpov_party extends wpov_api {
    public function logo($size = 'post-thumbnail') {
        return get_the_post_thumbnail_url($this->get_id(), $size);
    }
    
    public function url() {
        return $this->_get_meta('_party_url');
    }    
    
    public function description() {
        return $this->content();
    }
    
    public function the_description() {
        return $this->the_content();
    }    
    
    public function votings($type = 'id') {
        global $wpdb;
        
        $posts = $wpdb->get_col("
            SELECT 
                post_id
            FROM
                $wpdb->postmeta
            WHERE
                meta_key = '_voting_parties' AND
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
    
    public function answers($votings = array(), $questions = array()) {
        return wpov_get_party_answers($this, $votings, $questions);
    }
    
    public function count_answers($votings = array(), $questions = array()) {
        return count(wpov_get_party_answers($this, $votings, $questions));
    }    
    
    function voting_answers($voting=false) {
        $answers = $this->answers();
        $out = array();
        foreach($answers as $answer) {
            if($answer->voting()->get_id() == $voting) {
                $out[] = $answer;
            }
        }        
        return $out;
    }
    
    function voting_answer_explanation($voting=false, $question=false) {
        if(!$voting or !$question) {
            return false;
        }
        
        return get_post_meta($this->get_id(), sprintf('_party_answers_voting_'.$voting.'_question_' . $question.'_explanation') , true);
    }    
    
    function voting_answer($voting=false, $question=false) {
        $voting_answers = $this->voting_answers($voting);
        $out = array();
                
        foreach($voting_answers as $answer) {
            if($answer->question()->get_id() == $question) {
                return $answer;
            }
        }
        
        return false;
    }
    
    function party_user_consensus($voting = false, $user = false) {
        $voting_answers = $this->voting_answers($voting);
        //$user = wpov_get_current_voter();
        $rate = 0;
        foreach($voting_answers as $answer) {
            $rate += $answer->party_user_consensus($user);
        }
        
        return $rate;
    }
            
    
}

endif;
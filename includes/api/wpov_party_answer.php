<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_party_answer') ) :

class wpov_party_answer {
    function __construct($answer, $party, $voting, $question) {
        $this->set('value', $answer);
        
        $this->set('party', $party);
        $this->set('voting', $voting);
        $this->set('question', $question);
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
    
    function value() {
        return $this->get('value');
    }
    function answer() {
        return $this->value();
    }    
    
    function question() {
        return wpov_get_post($this->get('question'));
    }    
    
    function voting() {
        return wpov_get_post($this->get('voting'));
    }    
    
    function party() {
        return wpov_get_post($this->get('party'));
    }    
    
    function party_user_consensus($user = false) {
        if(!$user) {
            $user = wpov_get_current_voter();
        }
                
        $user_vote = $user->get_vote($this->voting()->get_id(), $this->question()->get_id());
        
        $value = 1;
        if($user_vote['count_twice']) {
            $value = 2;
        }
        
        if($user_vote['vote'] == $this->answer()) {
            return $value;
        } else {
            return 0;
        }
    }
            
    
}

endif;
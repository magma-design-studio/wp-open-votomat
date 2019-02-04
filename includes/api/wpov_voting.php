<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_voting') ) :

class wpov_voting extends wpov_api {
    protected $question_index = false;
        
    function result_link() {
        return sprintf('%sresult', $this->link());
    }
    
    function compare_link() {
        return sprintf('%scompare', $this->link());
    }    
    
    function questions_raw() {
        $questions = $this->_get_meta('_voting_questions');
        
        foreach($questions as &$question) {
            $question = (int) $question['question'];
        }
        
        return $questions;
    }
    
    function questions($raw = false) {
        $questions = $this->questions_raw();
        
        if($raw) {
            return $questions;
        }
        
        foreach($questions as &$question) {
            $question = wpov_get_question($question);
        }
        
        return $questions;
    }
    
    function count_questions() {
        return count($this->_get_meta('_voting_questions'));
    }    
    
    function parties() {
        $parties = $this->_get_meta('_voting_parties');
        foreach($parties as &$party) {
            $party = wpov_get_party($party['party']);
        }
        
        return $parties;
    } 
    
    function count_parties() {
        return count($this->_get_meta('_voting_parties'));
    }
    
    function publication_period() {
        $from = $this->publication_period_from();
        $to = $this->publication_period_to();
        
        if(empty($from) or empty($to)) {
            return false;
        }
                
        //http://php.net/manual/de/class.dateperiod.php
                
        $daterange = new DatePeriod(
            $from, 
            new DateInterval('P1D'),
            $to
        );        
        
        return $daterange;
    }    
    
    function publication_period_from($date_format = false) {
        $date = $this->_get_meta('_voting_period_from');
        
        return ($date_format ? date($date_format, $date) : date_create("@$date"));
    }
    
    function publication_period_to($date_format = false) {
        $date = $this->_get_meta('_voting_period_to');
        
        return ($date_format ? date($date_format, $date) : date_create("@$date"));
    }    
    
    function publication_status_array($current_time = 'now') {
        
        $current_time = date_create($current_time);
        
        $is_live = (
            $this->publication_period_from('U') <= $current_time->format('U') and
            $this->publication_period_to('U') > $current_time->format('U')
        );
                
        $u = "@{$current_time->format('U')}";
                
        return array(
            'is_live' => $is_live,
            
            'start_formated' => ($this->publication_period_from()->format('Y-m-d H:i:s')),
            'end_formated' => ($this->publication_period_to()->format('Y-m-d H:i:s')),
            
            'time_to_start' => ($this->publication_period_from()->format('U') - $current_time->format('U')),
            'time_to_end' => ($this->publication_period_to()->format('U') - $current_time->format('U')),
            'time_to_start_formated' => wpov_ago($u, $this->publication_period_from()),
            'time_to_end_formated' => wpov_ago($u, $this->publication_period_to())
        );
    }
    
    function question($_index = false) {
        if($_index !== false and is_numeric($_index)) {
            $index = $_index;
        } elseif($_index = get_query_var( 'wpov-question' ) and is_numeric($_index)) {
            $index = $_index;
        }
                      
        
        if($index == null) {
            $index = 0;
        } else {
            $index = $index-1;
        }
                
        $this->question_index = $index;
        
        $questions = $this->questions();
        
        if(empty($questions[$index])) {
            return false;
        }
        
        return $questions[$index];
    }
    
    function question_index() {
        if(!$this->question_index) {
            $this->question();
        }
        
        return ($this->question_index);
    }
    
    function question_index_readable() {
        return ($this->question_index()+1);
    }
    
        
}

endif;
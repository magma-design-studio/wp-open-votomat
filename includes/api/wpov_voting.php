<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_voting') ) :

class wpov_voting extends wpov_api {
    protected $question_index = false;
      
    function content() {
        $publication_status_array = $this->publication_status_array();
        
        //print_r($publication_status_array);
        
        if(
            $publication_status_array['is_started'] and 
            !$publication_status_array['is_not_ended'] and 
            $after_live_description = $this->after_live_description()
        ) {
            return $after_live_description;
        } elseif(
            !$publication_status_array['is_started'] and 
            $publication_status_array['is_not_ended'] and 
            $before_live_description = $this->before_live_description()
        ) {
            return $before_live_description;
        }
        
        return $this->atts['post_content'];
    } 
    
    function before_live_description() {
        return $this->_get_meta('before_live_description');
    }
    
    function after_live_description() {
        return $this->_get_meta('after_live_description');
    }
    
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
        $questions = $this->_get_meta('_voting_questions');
        return is_array($questions) ? count($questions) : 0;
    }    
    
    function parties() {
        $parties = $this->_get_meta('_voting_parties');
        $parties = is_array($parties) ? $parties : array();
        foreach($parties as &$party) {
            $party = wpov_get_party($party['party']);
        }
        
        return $parties;
    } 
    
    function count_parties() {
        $parties = $this->_get_meta('_voting_parties');
        return is_array($parties) ? count($parties) : 0;
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
    
    function get_timezone() {    
        $timezone = get_option( 'timezone_string', date_default_timezone_get() );
                
        if(!$timezone) {
            $date = new DateTime();
            $timezone = $date->getTimezone()->getName();
        }
        
        return new DateTimeZone($timezone);
    }
    
    function publication_period_from($date_format = false) {
        $date = $this->_get_meta('_voting_period_from');
        if(empty($date)) {
            return false;
        }
        
        $tz = $this->get_timezone();
        $time = new DateTime('now', $tz);
        $offset = $tz->getOffset( $time );     
        
        $_date = new DateTime();
        $_date->setTimezone($tz);
        $_date->setTimestamp($date-$offset);

        
        
        return ($date_format ? $_date->format($date_format) : $_date);
    }
    
    function publication_period_to($date_format = false) {
        $date = $this->_get_meta('_voting_period_to');
        if(empty($date)) {
            return false;
        }        
        
        $tz = $this->get_timezone();
        $time = new DateTime('now', $tz);
        $offset = $tz->getOffset( $time );             
        
        $_date = new DateTime();
        $_date->setTimezone($tz);
        $_date->setTimestamp($date-$offset);
        
        return ($date_format ? $_date->format($date_format) : $_date);
    }    
    
    protected $publication_status_array = array();
    function publication_status_array($current_time = 'now') {
        if(!empty($this->publication_status_array)) {
            return $this->publication_status_array;
        }
        
        $current_time = new DateTime($current_time, $this->get_timezone());
        
        $from = $this->publication_period_from();
        $to = $this->publication_period_to();
                
        $is_started = ($this->publication_period_from('U') <= $current_time->format('U'));
        $is_not_ended = ($this->publication_period_to('U') > $current_time->format('U'));
        $keep_online = !empty($this->_get_meta('_voting_keep_online'));
        
        $is_live = (
            $is_started and $is_not_ended
        );
        
        $is_active = (
            $is_live or (
                $is_started and 
                $keep_online
            ) or (
                !$is_started and 
                $this->before_live_description()
            ) or (
                !$is_not_ended and 
                $this->after_live_description()                
            )
        );        
                
        $u = "@{$current_time->format('U')}";
                
        return $this->publication_status_array = array(
            'is_live' => $is_live,
            'is_active' => $is_active, // active but not live
            'is_started' => $is_started,
            'is_not_ended' => $is_not_ended,
            'keep_online' => $keep_online,
            
            'current_date_formated' => $current_time->format('Y-m-d H:i:s'),
            'start_formated' => ($from ? $from->format('Y-m-d H:i:s') : false),
            'end_formated' => ($to ? $to->format('Y-m-d H:i:s') : false),
            
            'time_to_start' => ($from ? ($from->format('U') - $current_time->format('U')) : false),
            'time_to_end' => ($to ? ($to->format('U') - $current_time->format('U')) : false),
            'time_to_start_formated' => ($from ? wpov_ago($u, $from) : false),
            'time_to_end_formated' => ($to ? wpov_ago($u, $to) : false),
            
            'before_live_description' => $this->before_live_description(),
            'after_live_description' => $this->after_live_description()
        );
    }
    
    function question($_index = false) {
        $index = null;
        
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
    
    function reset_user_votings() {
        global $wpdb;
        
        $query = "
            SELECT p.ID  
            FROM $wpdb->posts as p
            JOIN $wpdb->postmeta as m ON (p.ID = m.post_id)
            WHERE 
                p.post_type = 'wpov-user-vote' AND
                m.meta_key REGEXP '^_wpov_voting_{$this->get_id()}'
            GROUP BY p.ID
        ";
        
        $post_ids = (array) $wpdb->get_col($query);
        
        $delete_query = array();
        $delete_query[] = "DELETE FROM $wpdb->postmeta WHERE post_id = {$this->get_id()} AND meta_key REGEXP '^_wpov_counter_question_'";        
        
        foreach($post_ids as $post_id) {
            $delete_query[] = "DELETE FROM $wpdb->postmeta WHERE post_id = {$post_id}";
            $delete_query[] = "DELETE FROM $wpdb->posts WHERE ID = {$post_id}";
            delete_transient(sprintf('wpov_voter_%d_votes', $post_id));
        }
        
        foreach($delete_query as $query) {
            $wpdb->query($query);
        }

        return $post_ids;
    }
    
        
}

endif;
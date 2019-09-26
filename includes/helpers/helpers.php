<?php

function wpov_get_path($path) {
    return WPOV__PLUGIN_DIR . $path;
}

function wpov_include_exists( $path ) {
    return file_exists( wpov_get_path( $path ) );
}
    
function wpov_include( $path ) {
    if( wpov_include_exists( $path ) ) {
        include_once( wpov_get_path( $path ) );
    } else {
        die( 'Can’t find file: ' . $path);
    }
}

function wpov_get_setting( $key ) {
    return wpov()->get_setting($key);
}

function wpov_get_settings( ) {
    return wpov()->get_settings();
}

function wpov_fields($attrs) {
    return new_cmb2_box($attrs);
}

function wpov_get_posts($args = array()) {
    $args = wp_parse_args( $args, array(
        'post_type' => array(
            'wpov-question', 
            'wpov-party', 
            'wpov-voting'
        )
    ) );
        
    $posts = get_posts($args);
    $posts = is_array($posts) ? $posts : array();
    
    return array_map('wpov_get_post', $posts);
}

function wpov_get_post($args = array()) {
        
    if($args instanceof WP_Post) {
        $_post = $args;
    } elseif(is_numeric($args) or is_array($args)) {
        $_post = get_post($args);
    }
    
    $_post = (object) $_post;
        
    if(!in_array(
        $_post->post_type, 
        array('wpov-question', 'wpov-party', 'wpov-voting')
    )) {
        die('wrong post type');
    }
    
    $class = str_replace('-', '_', $_post->post_type);
    return new $class($_post);    
    
}

function wpov_get_questions($args = array()) {
    $args = wp_parse_args( $args, array() );    
    $args['post_type'] = 'wpov-question';
    
    return wpov_get_posts($args);
}

function wpov_get_parties($args = array()) {
    $args = wp_parse_args( $args, array() );    
    $args['post_type'] = 'wpov-party';
    
    return wpov_get_posts($args);
}

function wpov_get_votings($args = array()) {
    $args = wp_parse_args( $args, array() );    
    $args['post_type'] = 'wpov-voting';
    
    return wpov_get_posts($args);
}

function wpov_get_party_answers($party, $votings = array(), $questions = array()) {
    global $wpdb;
    
    $do_cache = (empty($votings) and empty($questions));
    $cache_key = "wpov_party_{$party->get_id()}_answers";
    
    if($do_cache and false === ($answers = get_transient( $cache_key ))) {

        $query_where_part_voting = !empty($votings) ? '('.implode(',', $votings).')' : '[0-9]+';
        $query_where_part_questions = !empty($questions) ? '('.implode(',', $questions).')' : '[0-9]+';        

        $query = "
            SELECT
                post_id,
                meta_key,
                meta_value
            FROM
                $wpdb->postmeta
            WHERE
                post_id = '{$party->get_ID()}' AND
                meta_key REGEXP '^_party_answers_voting_{$query_where_part_voting}_question_{$query_where_part_questions}$'
        ";

        $answers = $wpdb->get_results($query);

        if($do_cache) {
            set_transient( $cache_key, $answers );
        }
    }        
    
    $out = array();
    foreach($answers as &$answer) {        
        if(preg_match('/_party_answers_voting_(?<voting_id>\d+)_question_(?<question_id>\d+)/', $answer->meta_key, $matches) and get_post_status($matches['question_id']) == 'publish') {
            $out[] = new wpov_party_answer(
                $answer->meta_value, 
                $answer->post_id,
                $matches['voting_id'],
                $matches['question_id']
            );
        }
    }
    
    return $out;
}


function wpov_get_question($args = array()) {
    return wpov_get_post($args);
}

function wpov_get_party($args = array()) {
    return wpov_get_post($args);
}

function wpov_get_voting($args = array()) {
    return wpov_get_post($args);
}

function wpov_get_current_voter() {
    if(!wpov()->current_voter) {
        wpov()->current_voter = new wpov_voter_current();
    }    
        
    return wpov()->current_voter;
}

function wpov_get_voter($args = array()) {
    return new wpov_voter($args);
}


function wpov_get_voters($voting, $vote = null) {
    $where_post_content = sprintf('%d_%d', $voting, $vote);

    $query = "
        SELECT ID 
        FROM    
            $wpdb->posts
        WHERE
            post_type = 'wpov-user-vote' AND
            post_content LIKE '%\"{$where_post_content}\"%'
    ";    
    
    global $wpdb;
    return array_map('wpov_get_voter', $wpdb->get_col($query));
    
}

function wpov_ago( $start, $end ) {
    $interval = date_create( $start )->diff( $end );
    $suffix = ( $interval->invert ? __('%s ago', WPOV__PLUGIN_NAME_SLUG) : '%s' );
    if ( $v = $interval->y >= 1 ) return sprintf( '%d %s', $interval->y, _n( 'year', 'years', $interval->y, WPOV__PLUGIN_NAME_SLUG )) . $suffix;
    if ( $v = $interval->m >= 1 ) return sprintf( '%d %s', $interval->m, _n( 'month', 'months', $interval->m, WPOV__PLUGIN_NAME_SLUG )) . $suffix;
    if ( $v = $interval->d >= 1 ) return sprintf( '%d %s', $interval->d, _n( 'day', 'days', $interval->d, WPOV__PLUGIN_NAME_SLUG )) . $suffix;
    if ( $v = $interval->h >= 1 ) return sprintf( '%d %s', $interval->h, _n( 'hour', 'hours', $interval->h, WPOV__PLUGIN_NAME_SLUG )) . $suffix;
    if ( $v = $interval->i >= 1 ) return sprintf( '%d %s', $interval->i, _n( 'minute', 'minutes', $interval->i, WPOV__PLUGIN_NAME_SLUG )) . $suffix;
    return sprintf($suffix, sprintf( '%d %s', $interval->s, _n( 'second', 'seconds', $interval->s, WPOV__PLUGIN_NAME_SLUG )));
}

function wpov_get_vote_symbol($vote) {
    if( $vote == 'approve' ) {
        return '▴';
    } elseif( $vote == 'disapprove' ) {
        return '▾';
    } 
    
    return '-';
}

function wpov_get_vote_class($vote) {
    $translations = apply_filters('wpov_vote_button_class', array(
        'approve' => 'success',
        'neutral' => '',
        'disapprove' => 'alert',
    ));
    
    return $translations[$vote];
}


if(!function_exists('wpov_get_method_object_attrs')) {
    function wpov_get_method_object_attrs($class, $method) {
        $r = new ReflectionMethod($class, $method);
        $comment = $r->getDocComment();
        
        if(preg_match_all('/@([^\s]+)\s+([^\n]+)/', $comment, $matches, PREG_SET_ORDER)) {
            $attr = array();
            foreach($matches as $match) {
                $attr[$match[1]] = $match[2];
            }
            $attr['accepted_args'] = count($r->getParameters());
            $attr['method'] = $method;
            return $attr;
        } else {
            return false;
        }
    }    
}

function wpov_uniqid_real($lenght = 13) {
    // http://php.net/manual/de/function.uniqid.php#120123
    if (function_exists("random_bytes")) {
        $bytes = random_bytes(ceil($lenght / 2));
    } elseif (function_exists("openssl_random_pseudo_bytes")) {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
    } else {
        throw new Exception("no cryptographically secure random function available");
    }
    return substr(bin2hex($bytes), 0, $lenght);
}

function wpov_fn($fn, $default) {
    $args = func_get_args();
    
    unset($args[0], $args[1]);
    $args = array_values($args);
    
    if(IS_MIGRATION_DEV) {
        if(is_array($fn)) {
            global $wpdb;
            $fn = (($fn[0] == $wpdb) ? '$wbdb->' : '$class->' ).$fn[1];
        }
        
        echo "$fn ".print_r($args, true);
    } else {
        $triggered_fn = call_user_func_array($fn, $args);
        return $triggered_fn;
    }
    return $default;
}

function wpov_wpdb_voters() {
    global $wpdb;
    return sprintf('%s%s', $wpdb->prefix, apply_filters('wpov_wpdb_voters', 'wpov_voters'));
}

function wpov_wpdb_voters_votes() {
    global $wpdb;
    return sprintf('%s%s', $wpdb->prefix, apply_filters('wpov_wpdb_voters_votes', 'wpov_voters_votes'));
}

function wpov_plugin_theme_dir() {
    return WPOV__PLUGIN_THEME_DIR;
}

function wpov_plugin_theme_dir_url() {
    return WPOV__PLUGIN_THEME_DIR_URL;
}

function wpov_crop_pagination($questions, $current_question) {
    $show_elems = 4;
    $show_elems_half = $show_elems/2;
    $placeholder = array(array('is_placeholder' => true));
    
    $_current_index = $current_question->question_index();
    $current_index = $_current_index+1;
    
    if($_current_index > $show_elems_half) {
        $first_end_index = ($_current_index-($show_elems_half+1));
    } else {
        $first_end_index = false;
    }
    
    if($first_end_index) {
        array_splice($questions, 1, $first_end_index, $placeholder);
    }
    
    $total = count($questions);
    $second_start_index = ($show_elems*2)-1;
    $second_end_index = (($total-$second_start_index)-1);
        
    if($second_start_index < ($total-1)) {    
        array_splice($questions, $second_start_index, $second_end_index, $placeholder);
    }

    return $questions;
    
}
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

function wpov_get_party_answers($parties, $votings = array(), $questions = array()) {
    global $wpdb;

    $query_where_part_voting = !empty($votings) ? '('.implode(',', $votings).')' : '[0-9]+';
    $query_where_part_questions = !empty($questions) ? '('.implode(',', $questions).')' : '[0-9]+';        

    $party_ids = array();
    if(!is_array($parties)) {
        $parties = array($parties);
    }
    
    foreach($parties as $party) {
        $party_ids[] = $party->get_id();
    }
    
    
    $query = "
        SELECT
            post_id,
            meta_key,
            meta_value
        FROM
            $wpdb->postmeta
        WHERE
            post_id IN ('".implode(',', $party_ids)."') AND
            meta_key REGEXP '^_party_answers_voting_{$query_where_part_voting}_question_{$query_where_part_questions}$'
    ";

    $answers = $wpdb->get_results($query);

    $out = array();
    foreach($answers as &$answer) {
        if(preg_match('/_party_answers_voting_(?<voting_id>\d+)_question_(?<question_id>\d+)/', $answer->meta_key, $matches)) {
            $out[] = new wpov_party_answer(
                $answer->meta_value, 
                wpov_get_post($answer->post_id),
                wpov_get_post($matches['voting_id']),
                wpov_get_post($matches['question_id'])
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
    return wpov()->current_voter;
}

function wpov_get_voter($args = array()) {
    $args = wp_parse_args( $args, array(

    ) );  
    
    $args['post_type'] = 'wpov-user-vote';
        
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
    $suffix = ( $interval->invert ? sprintf(' %s', __('ago', 'wpov')) : '' );
    if ( $v = $interval->y >= 1 ) return sprintf( '%d %s', $interval->y, _n( 'year', 'years', $interval->y, 'wpov' )) . $suffix;
    if ( $v = $interval->m >= 1 ) return sprintf( '%d %s', $interval->m, _n( 'month', 'months', $interval->m, 'wpov' )) . $suffix;
    if ( $v = $interval->d >= 1 ) return sprintf( '%d %s', $interval->d, _n( 'day', 'days', $interval->d, 'wpov' )) . $suffix;
    if ( $v = $interval->h >= 1 ) return sprintf( '%d %s', $interval->h, _n( 'hour', 'hours', $interval->h, 'wpov' )) . $suffix;
    if ( $v = $interval->i >= 1 ) return sprintf( '%d %s', $interval->i, _n( 'minute', 'minutes', $interval->i, 'wpov' )) . $suffix;
    return sprintf( '%d %s', $interval->s, _n( 'second', 'seconds', $interval->s, 'wpov' )) . $suffix;
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
    if( $vote == 'approve' ) {
        return 'success';
    } elseif( $vote == 'disapprove' ) {
        return 'alert';
    } 
    
    return '';
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

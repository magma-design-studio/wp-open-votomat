<?php
/**
 * The main template file
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

$context = Timber::get_context();
$templates = array( 'page.twig' );

if(in_array(get_query_var( 'post_type' ), array('wpov-voting', 'wpov-question'))) {
    global $wpov_post_voting;
    global $wpov_post_question;    
    
    
    $wpov_post_voting = $voting = $context['post_voting'] = $context['post'] = wpov_get_post($post);    
    
    if(get_query_var( 'wpov-result' )) {
        if($wpov_voter_result = get_query_var( 'wpov-voter-result' )) {
            if($voter = new wpov_voter(array(
                'post_name__in' => array($wpov_voter_result)
            ))) {
                $context['current_voter'] = $voter;
            }
            
            //$context['current_voter'] = 
        }
        
        array_unshift( $templates, 'page-voting-result.twig' );
    } elseif(get_query_var( 'wpov-compare' )) {
        array_unshift( $templates, 'page-voting-compare.twig' );
    } else {
        $wpov_post_question = $question = $context['post_question'] = $voting->question();
        if(!$question) {
            wp_redirect( $voting->link(), 302 );
            exit;
        } else {
            array_unshift( $templates, 'page-voting.twig' );
        }  
    }
    
} else {
    $context['post'] = new TimberPost();
}

Timber::render( $templates, $context );

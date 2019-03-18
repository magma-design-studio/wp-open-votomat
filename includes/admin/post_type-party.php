<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_post_type_party') ) :

class wpov_admin_post_type_party extends wpov_admin_post_type {
    function __construct() {
        //die('test');
        //add_action('load_edit?post_type=wpov-party', array($this, 'add_filters'));
        $this->add_filters();
    }

    function add_filters() {
        add_filter('enter_title_here', array($this, 'enter_title_here'));
        
        add_action('cmb2_admin_init', array($this, 'fields'));
        
        add_action('manage_edit-wpov-party_columns', array($this, 'admin_column_header'));
        add_action('manage_wpov-party_posts_custom_column', array($this, 'admin_column_content'));
        
        add_action('wp_insert_post', array($this, 'clear_party_answers_cache'), 10, 3);
    }
    
    function clear_party_answers_cache($post_id, $post, $update) {
        delete_transient("wpov_party_{$post_id}_answers");
    }
    
    function admin_column_header($columns) {
        $columns['votings'] = __('In Voting', WPOV__PLUGIN_NAME_SLUG);
        return $columns;
    }
    
    function admin_column_content($column) {
        global $post;
        
        $party = new wpov_party($post);
        $party_count_answers = $party->count_answers();
        switch ($column) {
            case "votings":
                ?>
                <ul>
                <?php
                foreach($party->votings('object') as $voting) :
                    ?>
                    <li><a href="<?php echo $voting->edit_link(); ?>"><?php echo $voting->title(); ?></a> (<?php echo $party_count_answers; ?> / <?php echo $voting->count_questions(); ?> <?php _e('questions answered', WPOV__PLUGIN_NAME_SLUG); ?>)</li>
                    <?php
                endforeach;
                ?>
                </ul>
                <?php
            break;
        }        
    }
    
    function enter_title_here($title) {
        return __( 'Enter party name here', WPOV__PLUGIN_NAME_SLUG );
    }
    
    function fields() {
        global $post;
        
        $prefix = '_party_';
        
        $fields = wpov_fields( array(
            'id'            => 'party_description_metabox',
            'title'         => __( 'Party Description', WPOV__PLUGIN_NAME_SLUG ),
            'object_types'  => array( 'wpov-party', ), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true,
        ) );       
        
        do_action('wpov_admin_party_before_fields', $fields);
            
        /*$fields->add_field( array(
            'name'         => __( 'Party Logo', WPOV__PLUGIN_NAME_SLUG ),
            'id'         => $prefix . 'logo',
            'type'       => 'file',
            'on_front'        => true, // Optionally designate a field to wp-admin only
        ) );*/
        
        $fields->add_field( array(
            'name'         => __( 'Party Website', WPOV__PLUGIN_NAME_SLUG ),
            'id'         => $prefix . 'url',
            'type'       => 'text_url',
            'on_front'        => true, // Optionally designate a field to wp-admin only
            'attributes'  => array(
                'placeholder' => __('e.g. https://example.com', WPOV__PLUGIN_NAME_SLUG),
            ),            
            
        ) );    
        

        if(empty($_GET['post']) and empty($_REQUEST['post_ID']) ) {
            return;
        }
        
        if(isset($_GET['post'])) {
            $post_id = $_GET['post'];
        } elseif(isset($_REQUEST['post_ID'])) {
            $post_id = $_REQUEST['post_ID'];
        }
        $party = wpov_get_post($post_id);
        

        $votings = $party->votings('object');       
        
        foreach($votings as $voting) {
            
            $fields = wpov_fields( array(
                'id'            => 'party_voting_'.$voting->get_id().'_metabox',
                'title'         => sprintf(__( 'Voting “%s”', WPOV__PLUGIN_NAME_SLUG ), $voting->title()),
                'object_types'  => array( 'wpov-party', ), // Post type
                'context'       => 'normal',
                'priority'      => 'low',
                'show_names'    => true,
            ) );   
                       
            
            $questions = $voting->questions();
            
            
            foreach($questions as $i => $question) {

                $fields->add_field( array(
                    'name'    => sprintf(__('Question #%d'), ($i+1)),
                    'id'      => $prefix . 'answers_voting_'.$voting->get_id().'_question_' . $question->get_id(),
                    'type'    => 'radio_inline',
                    'before_field' => apply_filters('the_content', $question->content()),
                    'options' => array(
                        '' => __( 'None', WPOV__PLUGIN_NAME_SLUG ),
                        'approve' => __( 'Approve', WPOV__PLUGIN_NAME_SLUG ),
                        'neutral'   => __( 'Neutral', WPOV__PLUGIN_NAME_SLUG ),
                        'disapprove' => __( 'Disapprove', WPOV__PLUGIN_NAME_SLUG ),
                    ),
                    'default' => '',        
                ) );     
                
                $fields->add_field( array(
                    'name'    => ' ',
                    'before_field' => sprintf('<p><strong>%s</strong></p>', __('Explanation', WPOV__PLUGIN_NAME_SLUG)),
                    'id'      => $prefix . 'answers_voting_'.$voting->get_id().'_question_' . $question->get_id().'_explanation',
                    'type'    => 'textarea_small',
                ) );                   
                
            }
        }


        
        
        
        do_action('wpov_admin_party_after_fields', $fields);
        
    }
    

}

new wpov_admin_post_type_party();

endif;
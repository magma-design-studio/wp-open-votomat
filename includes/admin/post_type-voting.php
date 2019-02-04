<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_post_type_voting') ) :

class wpov_admin_post_type_voting extends wpov_admin_post_type {
    public $post_type = 'wpov-voting';
    
    function __construct() {
        //die('test');
        //add_action('load_edit?post_type=wpov-party', array($this, 'add_filters'));
        $this->add_filters();
    }

    function add_filters() {
        add_filter('enter_title_here', array($this, 'enter_title_here'));
        
        add_action('cmb2_admin_init', array($this, 'fields'));
        
        add_action('manage_edit-'.$this->post_type.'_columns', array($this, 'admin_column_header'));
        add_action('manage_'.$this->post_type.'_posts_custom_column', array($this, 'admin_column_content'));        
    }
    
    function admin_column_header($columns) {
        $columns['parties'] = __('Parties', 'wpov');
        $columns['questions'] = __('Questions', 'wpov');
        $columns['period'] = __('Period', 'wpov');
        return $columns;
    }
    
    function admin_column_content($column) {
        global $post;
        
        $voting = new wpov_voting($post);
        switch ($column) {
            case "questions":
            case "parties":
                $translations = array(
                    'questions' => array(
                        'Question',
                        'Questions',
                    ),
                    'parties' => array(
                        'Party',
                        'Parties',
                    )           
                );
                
                $number = $voting->{'count_' . $column}();
                printf(
                    '<a href="%s">%d %s</a>',
                    '#',
                    $number,
                    _n( $translations[$column][0], $translations[$column][1], $number, 'wpov' )
                );
            break;
            case "period":
                $status = $voting->publication_status_array();
                
                $status_html = '<p><span style="border-radius: 50%%; width: 15px; height: 15px; display: inline-block; background-color: %s"></span> <strong>%s</strong></p>';
                if($status['is_live']) {
                    printf(
                        $status_html, 
                        'green',
                        __('Voting is live!', 'wpov')
                    );
                } elseif($status['time_to_start'] > 0) {
                    printf(
                        $status_html, 
                        'orange',
                        __('Voting is on hold!', 'wpov')
                    );
                } elseif($status['time_to_end'] < 0) {
                    printf(
                        $status_html, 
                        'red',
                        __('Voting has ended!', 'wpov')
                    );
                }
                
                printf(
                    '<p>%s: <code>%s</code></p>',
                    __('From', 'wpov'),
                    date(__('d.m.Y H:i:s', 'wpov'), $voting->publication_period_from('U'))
                );
                printf(
                    '<p>%s: <code>%s</code></p>',
                    __('To', 'wpov'),
                    date(__('d.m.Y H:i:s', 'wpov'), $voting->publication_period_to('U'))
                );                
            break;
        }        
    }    
    
    
    function enter_title_here($title) {
        return __( 'Enter voting title here', 'wpov' );
    }
    
    public $prefix = '_voting_';
    
    function fields() {
        do_action('wpov_admin_voting_before_fields', $fields);
        
        $this->fields_core_parties();
        $this->fields_core_questions();
        $this->fields_side_settings();
                
        do_action('wpov_admin_voting_after_fields', $fields);
        
    }
    
    function fields_core_parties() {
        
        $fields = wpov_fields( array(
            'id'            => 'voting_parties',
            'title'         => __( 'Parties', 'wpov' ),
            'object_types'  => array( 'wpov-voting', ), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true,
        ) );       
        
        do_action('wpov_admin_party_before_fields', $fields);
        
        $parties = $this->get_posts_options('party');        
        
        if(empty($parties)) {
            $fields->add_field( array(
                'desc' => esc_html__( 'No parties have been created so far. If you have created some, you can link them here!', 'cmb2' ),
                'id'   => $prefix . 'title',
                'type' => 'title',
            ) );                 
        }
        
        
        $group_field_id = $fields->add_field( array(
            'id'         => $this->prefix . 'parties',
            'type'        => 'group',
            'description' => __( 'Generates reusable form entries', 'wpov' ),
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __( 'Party {#}', 'wpov' ), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __( 'Add Another Party', 'wpov' ),
                'remove_button'     => __( 'Remove Party', 'wpov' ),
                'sortable'          => true, // beta
                // 'closed'         => true, // true to have the groups closed by default
                // 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
            ),
            'show_on_cb' => array($this, 'fields_cb_empty_options'),
            'wpov_setting' => array(
                'type' => 'party'
            )            
        ) );        
        

        $fields->add_group_field( $group_field_id, array(
            'name' => __('Party', 'wpov'),
            'id'   => 'party',
            'type' => 'select',
            'options' => $this->make_post_array_select_options($parties),
        ) );        
        
    }
    
    function fields_core_questions() {
        $fields = wpov_fields( array(
            'id'            => 'voting_questions',
            'title'         => __( 'Questions', 'wpov' ),
            'object_types'  => array( 'wpov-voting', ), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true,
        ) );            
        
        $questions = $this->get_posts_options('question');        
        
        if(empty($questions)) {
            $fields->add_field( array(
                'desc' => esc_html__( 'No questions have been created so far. If you have created some, you can link them here!', 'cmb2' ),
                'id'   => $prefix . 'title',
                'type' => 'title',
            ) );                 
        }
   
        
        $group_field_id = $fields->add_field( array(
            'id'         => $this->prefix . 'questions',
            'type'        => 'group',
            'description' => __( 'Generates reusable form questions', 'wpov' ),
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __( 'Question {#}', 'wpov' ), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __( 'Add Another Question', 'wpov' ),
                'remove_button'     => __( 'Remove Question', 'wpov' ),
                'sortable'          => true, // beta
                // 'closed'         => true, // true to have the groups closed by default
                // 'remove_confirm' => esc_html__( 'Are you sure you want to remove?', 'cmb2' ), // Performs confirmation before removing group.
            ),
            'show_on_cb' => array($this, 'fields_cb_empty_options'),
            'wpov_setting' => array(
                'type' => 'question'
            )
        ) );        
        

        $fields->add_group_field( $group_field_id, array(
            'name' => __('Question', 'wpov'),
            'id'   => 'question',
            'type' => 'select',
            'options' => $this->make_post_array_select_options($questions),
        ) );        
        
    }
    

    
    function fields_cb_empty_options($args) {
        $wpov_setting = $args->args('wpov_setting');
        
        return !empty($this->get_posts_options($wpov_setting['type']));
    }
    
    function fields_side_settings() {
        $fields = wpov_fields( array(
            'id'            => 'voting_settings_description_metabox',
            'title'         => __( 'Settings', 'wpov' ),
            'object_types'  => array( 'wpov-voting'), // Post type
            'context'       => 'side',
            'priority'      => 'high',
            'show_names'    => true,
        ) );        
        
        $fields->add_field( array(
            'name' => __('From', 'wpov'),
            'id'   => $this->prefix . 'period_from',
            'type' => 'text_datetime_timestamp',
        ) );        
        
        $fields->add_field( array(
            'name' => __('to', 'wpov'),
            'id'   => $this->prefix . 'period_to',
            'type' => 'text_datetime_timestamp',
        ) );            
        
        
    }
    

}

new wpov_admin_post_type_voting();

endif;
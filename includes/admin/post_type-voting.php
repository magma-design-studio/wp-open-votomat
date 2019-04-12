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
        
        add_filter( 'cmb2_input_attributes', array($this, 'set_datetime_placeholder'), 20, 4 );
                
    }
    
    function set_datetime_placeholder($args, $type_defaults, $field, $types) {
        if(in_array($args['name'], array($this->prefix.'period_from[time]', $this->prefix.'period_to[time]'))) {
            $args['placeholder'] = sprintf(__('Time (e.g. %s)', WPOV__PLUGIN_NAME_SLUG), date('H:i'));
        }
        return $args;
    }

    
    function admin_column_header($columns) {
        $columns['parties'] = __('Parties', WPOV__PLUGIN_NAME_SLUG);
        $columns['questions'] = __('Questions', WPOV__PLUGIN_NAME_SLUG);
        $columns['period'] = __('Period', WPOV__PLUGIN_NAME_SLUG);
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
                        __('Question', WPOV__PLUGIN_NAME_SLUG),
                        __('Questions', WPOV__PLUGIN_NAME_SLUG),
                    ),
                    'parties' => array(
                        __('Party', WPOV__PLUGIN_NAME_SLUG),
                        __('Parties', WPOV__PLUGIN_NAME_SLUG),
                    )           
                );
                
                $number = $voting->{'count_' . $column}();
                printf(
                    '<a href="%s">%d %s</a>',
                    '#',
                    $number,
                    _n( $translations[$column][0], $translations[$column][1], $number, WPOV__PLUGIN_NAME_SLUG )
                );
            break;
            case "period":
                $status = $voting->publication_status_array();
                
                $status_html = '<p><span style="border-radius: 50%%; width: 15px; height: 15px; display: inline-block; background-color: %s"></span> <strong>%s</strong></p>';
                if($status['is_live']) {
                    printf(
                        $status_html, 
                        'green',
                        __('Voting is live!', WPOV__PLUGIN_NAME_SLUG)
                    );
                } elseif($status['time_to_start'] > 0) {
                    printf(
                        $status_html, 
                        'orange',
                        __('Voting is on hold!', WPOV__PLUGIN_NAME_SLUG)
                    );
                    
                    printf(
                        '<p><button data-wpov-js-click-action="reset_user_votings" data-post_id="%d" class="button-secondary">%s</button></p>', 
                        $post->ID,
                        __('Reset user voting', WPOV__PLUGIN_NAME_SLUG)
                    );                    
                    
                } elseif($status['time_to_end'] < 0) {
                    printf(
                        $status_html, 
                        'red',
                        __('Voting has ended!', WPOV__PLUGIN_NAME_SLUG)
                    );
                }
                
                if($status['time_to_end'] < 0 and $status['keep_online']) {
                    printf(
                        '<p>%s</p>',
                        __('Voting has ended but is kept online!', WPOV__PLUGIN_NAME_SLUG)
                    );                    
                }
                
                printf(
                    '<p>%s: <code>%s</code></p>',
                    __('From', WPOV__PLUGIN_NAME_SLUG),
                    $voting->publication_period_from(__('d.m.Y H:i:s', WPOV__PLUGIN_NAME_SLUG))
                );
                printf(
                    '<p>%s: <code>%s</code></p>',
                    __('To', WPOV__PLUGIN_NAME_SLUG),
                    $voting->publication_period_to(__('d.m.Y H:i:s', WPOV__PLUGIN_NAME_SLUG))
                );                
            break;
        }        
    }    
    
    function enter_title_here($title) {
        return __( 'Enter voting title here', WPOV__PLUGIN_NAME_SLUG );
    }
    
    public $prefix = '_voting_';
    
    function fields() {
        do_action('wpov_admin_voting_before_fields', $fields);
        
        $this->fields_core_descriptions();
        $this->fields_core_parties();
        $this->fields_core_questions();
        $this->fields_side_settings();
                
        do_action('wpov_admin_voting_after_fields', $fields);
        
    }
    
    function fields_core_descriptions() {
        $fields = wpov_fields( array(
            'id'            => 'voting_descriptions',
            'title'         => __( 'Descriptions', WPOV__PLUGIN_NAME_SLUG ),
            'object_types'  => array( 'wpov-voting', ), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => true,
        ) );   
        
        $fields->add_field( array(
            'name' => __('Description before publication', WPOV__PLUGIN_NAME_SLUG),
            //'desc' => esc_html__( 'If this field is empty this voting will appear after expiration!', 'cmb2' ),
            'id'   => $prefix . 'before_live_description',
            'type' => 'wysiwyg',
        ) );      
        
        $fields->add_field( array(
            'name' => __('Description after expiry', WPOV__PLUGIN_NAME_SLUG),
            'desc' => esc_html__( 'If this field is empty this voting will disappear after expiration!', 'cmb2' ),
            'id'   => $prefix . 'after_live_description',
            'type' => 'wysiwyg',
        ) );           
        
    }
    
    function fields_core_parties() {
        
        $fields = wpov_fields( array(
            'id'            => 'voting_parties',
            'title'         => __( 'Parties', WPOV__PLUGIN_NAME_SLUG ),
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
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __( 'Party {#}', WPOV__PLUGIN_NAME_SLUG ), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __( 'Add Another Party', WPOV__PLUGIN_NAME_SLUG ),
                'remove_button'     => __( 'Remove Party', WPOV__PLUGIN_NAME_SLUG ),
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
            'name' => __('Party', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'party',
            'type' => 'select',
            'options' => $this->make_post_array_select_options($parties),
        ) );        
        
    }
    
    function fields_core_questions() {
        $fields = wpov_fields( array(
            'id'            => 'voting_questions',
            'title'         => __( 'Questions', WPOV__PLUGIN_NAME_SLUG ),
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
            // 'repeatable'  => false, // use false if you want non-repeatable group
            'options'     => array(
                'group_title'       => __( 'Question {#}', WPOV__PLUGIN_NAME_SLUG ), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __( 'Add Another Question', WPOV__PLUGIN_NAME_SLUG ),
                'remove_button'     => __( 'Remove Question', WPOV__PLUGIN_NAME_SLUG ),
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
            'name' => __('Question', WPOV__PLUGIN_NAME_SLUG),
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
            'title'         => __( 'Settings', WPOV__PLUGIN_NAME_SLUG ),
            'object_types'  => array( 'wpov-voting'), // Post type
            'context'       => 'side',
            'priority'      => 'high',
            'show_names'    => true,
        ) );        
        
        $fields->add_field( array(
            'name' => __('From', WPOV__PLUGIN_NAME_SLUG),
            'id'   => $this->prefix . 'period_from',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes'  => array(
                'placeholder' => sprintf(__('Date (e.g. %s)', WPOV__PLUGIN_NAME_SLUG), date('d.m.Y')),
                'autocomplete' => 'off',
                'required'    => 'required',
            )            
        ) );        
        
        $fields->add_field( array(
            'name' => __('to', WPOV__PLUGIN_NAME_SLUG),
            'id'   => $this->prefix . 'period_to',
            'type' => 'text_datetime_timestamp',
            'date_format' => 'd.m.Y',
            'time_format' => 'H:i',
            'attributes'  => array(
                'placeholder' => sprintf(__('Date (e.g. %s)', WPOV__PLUGIN_NAME_SLUG), date('d.m.Y')),
                'autocomplete' => 'off',
                'required'    => 'required',
            )               
        ) );            
        
        $fields->add_field( array(
            'name' => __('Keep online after expiration', WPOV__PLUGIN_NAME_SLUG),
            'id'   => $this->prefix . 'keep_online',
            'after_row' => sprintf('<p>%s</p>', __('If this checkbox is selected, the voting doesn’t disappear after expiration. You can still vote. Votes are not included in the statistics after expiration.', WPOV__PLUGIN_NAME_SLUG)),
            'type' => 'checkbox',
        ) );
                             
    }
    

}

new wpov_admin_post_type_voting();

endif;
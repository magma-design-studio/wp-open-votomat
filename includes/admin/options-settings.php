<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_options_settings') ) :

/**
* CMB2 Theme Options  @version 0.1.0
*/   

/*

https://github.com/CMB2/CMB2-Snippet-Library/blob/master/options-and-settings-pages/add-cmb2-settings-to-other-settings-pages.php
*/
class wpov_admin_options_settings extends wpov_admin_options {  
    public $page_slug = 'wpov-settings';  
    /**
    * Constructor
    * @since 0.1.0
    */
    public function __construct() {
    // Set our title
        $this->title = __( 'Settings', 'wpov' );
    }
    
    /**
    * Add the options metabox to the array of metaboxes
    * @since  0.1.0
    */
    function add_options_page_metabox() {

        if ( ! empty( $this->option_metabox ) ) {
            return $this->option_metabox;
        }        

        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox';
        self::register_metabox($metabox_id, $key);
        
        $this->cmb[0] = new_cmb2_box( array(
            'id'         => $metabox_id,
            'title' =>'Number Counters',
            'hookup'     => false,
            'cmb_styles' => false,
            'show_on'    => array(
                // These are important, don't remove
                'key'   => 'options-page',
                'value' => array( $key, )
            ),
        ) );
        $this->cmb[0]->add_field( array(
            'name'    => __('Role', 'wpov'),
            'id'      => 'wpov_type',
            'type'    => 'select',
            'options' => array(
                'standalone' => __('Standanlone', 'wpov'),
                'shortcode' => __('Shortcode', 'wpov'),
                'widget' => __('Widget', 'wpov')
            )
        ) );
        
        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox_2';
        self::register_metabox($metabox_id, $key);        
        
        $this->cmb[1] = new_cmb2_box( array(
            'id'         => $metabox_id,
            'title' =>'Number Counters',
            'hookup'     => false,
            'cmb_styles' => false,
            'show_on'    => array(
                // These are important, don't remove
                'key'   => 'options-page',
                'value' => array( $key, )
            ),
        ) );        
 
        $this->cmb[1]->add_field( array(
            'name' => 'Show GitHub-Link',
            'id'   => 'show_github',
            'type' => 'checkbox',
        ) );
        $this->cmb[1]->add_field( array(
            'name' => 'Show GitHub-Link',
            'id'   => 'show_github',
            'type' => 'checkbox',
        ) );   
        $this->cmb[1]->add_field( array(
            'name'    => 'Logo',
            'id'      => 'logo',
            'type'    => 'file',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            'text'    => array(
                'add_upload_file_text' => 'Add Logo' // Change upload button text. Default: "Add or Upload File"
            ),
            // query_args are passed to wp.media's library query.
            'query_args' => array(
                'type' => array(
                    'image/jpeg',
                    'image/png',
                ),
            ),
            'preview_size' => 'large',
        ) );        
        
        $this->cmb[1]->add_field( array(
            'name'    => 'Intro Text',
            'id'      => 'text_intro',
            'type'    => 'wysiwyg',
            'options' => array(),
        ) );      
        
        $this->cmb[1]->add_field( array(
            'name'    => 'Intro Text',
            'id'      => 'text_instruction',
            'type'    => 'wysiwyg',
            'options' => array(),
        ) );          
        
        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox_2';
        self::register_metabox($metabox_id, $key);            
        
        $this->cmb[2] = new_cmb2_box( array(
            'id'         => $metabox_id,
            'title' =>'Number Counters',
            'hookup'     => false,
            'cmb_styles' => false,
            'show_on'    => array(
                // These are important, don't remove
                'key'   => 'options-page',
                'value' => array( $key, )
            ),
        ) );       
        
        $group_field_id = $this->cmb[2]->add_field( array(
            'id'          => 'sponsor',
            'type'        => 'group',
            'options'     => array(
                'group_title'       => __( 'Sponsor {#}', 'cmb2' ), // since version 1.1.4, {#} gets replaced by row number
                'add_button'        => __( 'Add Another Sponsor', 'cmb2' ),
                'remove_button'     => __( 'Remove Sponsor', 'cmb2' ),
                'sortable'          => true, // beta
            ),
        ) );
        
        $this->cmb[2]->add_group_field( $group_field_id, array(
            'name' => 'Sponsor Name',
            'id'   => 'title',
            'type' => 'text',
        ) );        
              
        $this->cmb[2]->add_group_field( $group_field_id, array(
            'name' => 'Sponsor Logo',
            'id'   => 'logo',
            'type'    => 'file',
            // Optional:
            'options' => array(
                'url' => false, // Hide the text input for the url
            ),
            'text'    => array(
                'add_upload_file_text' => 'Add Logo' // Change upload button text. Default: "Add or Upload File"
            ),
            // query_args are passed to wp.media's library query.
            'query_args' => array(
                'type' => array(
                    'image/jpeg',
                    'image/png',
                ),
            ),
            'preview_size' => 'large',                        
        ) ); 
        
        $this->cmb[2]->add_group_field( $group_field_id, array(
            'name' => 'Sponsor Website',
            'id'   => 'url',
            'type' => 'text_url',
        ) );          
        

        return $this->cmb;
    }

}

// initialize
wpov()->wpov_admin_options_settings = (new wpov_admin_options_settings)->hooks();

endif; // class_exists check
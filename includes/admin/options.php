<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_options') ) :

/**
* CMB2 Theme Options  @version 0.1.0
*/   

abstract class wpov_admin_options {  
    /**
    * Option key, and option page slug
    * @var string
    */    
    public $page_slug = '';  
    /**
    * Options page metabox id
    * @var string
    */
    public $metabox_id = array(); 
    /**
    * Options Page title
    * @var string
    */
    public $title = '';
    /**
    * Options Page hook
    * @var string
    */
    public $options_page = ''; 
    /**
    * Holds an instance of the object
    *
    * @var Myprefix_Admin
    **/
    public static $instance = null; 

    /**CMB array **/
    public $cmb=array();    
    
    /**
    * Initiate our hooks
    * @since 0.1.0
    */
    public function hooks() {
        add_action( 'admin_init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'cmb2_admin_init', array( $this, 'add_options_page_metabox' ) );
        add_action( 'cmb2_admin_init', array( $this, 'add_options_page_metabox_update_notice' ) );
        
		add_action( 'current_screen', array( $this, 'maybe_save' ) );
        // add_action( 'cmb2_admin_init', array( $this, 'add_counters_metabox' ) );
    }
    
    /**
    * Add menu options page
    * @since 0.1.0
    */
    public function add_options_page() {
        $this->options_page = add_submenu_page( 'wpov-dashboard', $this->title, $this->title, 'manage_options', $this->page_slug, array( $this, 'admin_page_display' ) );  
        // Include CMB CSS in the head to avoid FOUC
        add_action( "admin_print_styles-{$this->options_page}", array( 'CMB2_hookup', 'enqueue_cmb_css' ) );
    }
    
    function add_options_page_metabox_update_notice() {
        foreach($this->metabox_id as $metabox_id) {
            add_action( "cmb2_save_options-page_fields_{$metabox_id}", array( $this, 'settings_notices' ), 10, 2 ); 
        }
    }
    
    public function maybe_save() {
        if ( empty( $_POST ) ) {
            return;
        }
        $url = wp_get_raw_referer();

        // Check if our screen id is in the referrer url.
        if ( false === strpos( $url, $this->page_slug ) ) {
            return;
        }
                
        //$metaboxes = self::add_options_page_metabox();
        foreach($this->metabox_id as $metabox_id) {
            $cmb = cmb2_get_metabox( $metabox_id, $this->page_slug );

            if(!isset($_POST[$cmb->nonce()])) continue;
            
            if ( $cmb ) {
                $hookup = new CMB2_hookup( $cmb );
                if ( $hookup->can_save( 'options-page' ) ) {
                    $cmb->save_fields( $this->page_slug, 'options-page', $_POST );
                }
            }            
        }

	}    
    
    
    

    /**
    * Admin page markup. Mostly handled by CMB2
    * @since  0.1.0
    */
    public function admin_page_display() {

        $option_tabs = $this->add_options_page_metabox(); //get all option tabs
        $tab_forms = array(); 


        ?>
        <link rel='stylesheet' id='theme_options-css'  href='<?php echo get_stylesheet_directory_uri();  ?>/css/theme_options.css' type='text/css' media='all' />

        <div class="wrap cmb2-options-page <?php echo $this->key; ?>">
        <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>          
        <!--Accordions-->
        <?php foreach($option_tabs as $option_tab) { ?>

        <div class="panel">
        <?php cmb2_metabox_form( $option_tab->meta_box['id'], $option_tab->meta_box['key'] ); ?>
        <div class="clear"></div>
        </div>

        <?php } ?>

        </div>
        <?php
    }

    function register_metabox($metabox_id, $key) {
        $metabox_ids = $this->metabox_id;
        $metabox_ids[] = $metabox_id;
        $this->metabox_id = $metabox_ids;
        return $metabox_id;
    }  
    
    function get_keys() {
        return array($this->page_slug);
    }
    

    /**
    * Register settings notices for display
    *
    * @since  0.1.0
    * @param  int   $object_id Option key
    * @param  array $updated   Array of updated fields
    * @return void
    */
    public function settings_notices( $object_id, $updated ) {
        if ( $object_id != $this->page_slug || empty( $updated ) ) {
            return;
        }

        add_settings_error( $object_id . '-notices', '', __( 'Settings updated.', WPOV__PLUGIN_NAME_SLUG ), 'updated' );
        settings_errors( $object_id . '-notices' );
    }

    /**
    * Public getter method for retrieving protected/private variables
    * @since  0.1.0
    * @param  string  $field Field to retrieve
    * @return mixed          Field value or exception is thrown
    */
    public function __get( $field ) {
        // Allowed fields to retrieve
        if ( in_array( $field, array( 'key', 'metabox_id', 'title', 'options_page' ), true ) ) {
            return $this->{$field};
        }

        throw new Exception( 'Invalid property: ' . $field );
    }


    /**
    * Register our setting to WP
    * @since  0.1.0
    */
    public function init() {
        foreach($this->get_keys() as $key) {
            register_setting( $key, $key );
        }
    }
    
    

}

endif; // class_exists check
<?php
/*
Plugin Name: WP Open Votomat
Description: This plugin allows you to use your website as a voting advice application.
Author: Sebastian Tiede @ magma
Version: 0.0.1
Author URI: https://magmadesignstudio.de
*/

/*
wpov
    dashboard (statistics)
    questions
    parties
    issues
    settings
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'PLUGIN_NAME_SLUG', 'wpov' );

define( 'WPOV_VERSION', '0.0.1' );
define( 'WPOV__MINIMUM_WP_VERSION', '4.0' );
define( 'WPOV__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPOV__PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );

$wpov_template = 'wpov_template/wpov';
define( 'WPOV__PLUGIN_THEME_DIR', WPOV__PLUGIN_DIR . $wpov_template );
define( 'WPOV__PLUGIN_THEME_DIR_URL', WPOV__PLUGIN_DIR_URL . $wpov_template );


if(!class_exists('wpov')) :

    class wpov {
        protected $settings = array(
            'capability' => 'manage_options',
            'admin_settings' => array(
                'wpov_type' => 'standalone'
            )
        );
        
        function __construct() {
            $this->set_setting('admin_settings', get_option('wpov-settings', array()));
        }
        
        function initialize() {
            
            include_once( WPOV__PLUGIN_DIR . 'includes/helpers/helpers.php');
            wpov_include('/includes/helpers/twig_extensions.php');
            
            wpov_include('/vendor/autoload.php');
            wpov_include('/vendor/webdevstudios/cmb2/init.php');
            
            wpov_include('/includes/api/api.php');
            wpov_include('/includes/api/wpov_party.php');
            wpov_include('/includes/api/wpov_party_answer.php');
            wpov_include('/includes/api/wpov_question.php');
            wpov_include('/includes/api/wpov_voting.php');
            
            add_action('init',	array($this, 'register_post_types'), 5);
            
            if(is_admin()) {
                wpov_include('/includes/admin/admin.php');
                
                wpov_include('/includes/admin/options.php');
                wpov_include('/includes/admin/options-dashboard.php');
                wpov_include('/includes/admin/options-settings.php');
                
                wpov_include('/includes/admin/post_type.php');
                
                add_filter( 'cmb2_meta_box_url', array($this, 'cmb2_meta_box_url') );
                
                if($this->get_setting('admin_settings')['wpov_type'] == 'standalone') {
                    add_action( 'after_setup_theme', array($this, 'register_menu') );
                }                    
                
            } else {
                wpov_include('/includes/voter/wpov_voter.php');
                wpov_include('/includes/voter/wpov_voter_current.php');
                
                wpov_include('/includes/frontend/frontend.php');
                                
                if($this->get_setting('admin_settings')['wpov_type'] == 'standalone') {
                    wpov_include('/includes/frontend/frontend-standalone.php');
                }        
                /*
                add_action('init', function() {
                    if(preg_match('/wp-login.php/', $_SERVER['SCRIPT_NAME'])) {
                        return;
                    }
                    
                    $posts = wpov_get_parties();
                    
                    foreach($posts as $post) {
                        $publication_period = $post->answers();
                        print_r($publication_period);
                        exit;
                    }
                    print_r($posts);
                    exit;
                });
                */
            }
            
            $this->timber = new \Timber\Timber();

            Timber::$dirname = array( 'twig' );
            Timber::$autoescape = false;

            add_filter( 'get_twig', array( $this, 'add_to_twig' ) );
            add_filter( 'timber_context', array( $this, 'add_to_context' ) );
            
            add_action('init', array($this, 'rewrite_rules'));        
        }
    
        function register_menu() {
            register_nav_menu( 'wpov_footer', __( 'WPOV Footer Menu', 'wpov' ) );
        }        
        
        function rewrite_rules() {
            switch($this->get_setting('admin_settings')['wpov_type']) {
                case 'standalone':
                    add_rewrite_rule('^voting/([^/]+)/question/([^/]+)/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-question=$matches[2]', 'top');
                    add_rewrite_rule('^voting/([^/]+)/result/([^/]+)/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-result=true&wpov-voter-result=$matches[2]', 'top');
                    add_rewrite_rule('^voting/([^/]+)/result/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-result=true', 'top');
                    add_rewrite_rule('^voting/([^/]+)/compare/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-compare=true', 'top');
                    //add_rewrite_rule('^voting/([^/]+)/?', 'index.php?wpov-voting=$matches[1]', 'top');
                    //add_rewrite_rule('^voting/?', 'index.php?wpov-voting=true', 'top');

                    //add_rewrite_tag('%wpov-voting%', '([^&]+)');
                    add_rewrite_tag('%wpov-question%', '([^&]+)');  
                    add_rewrite_tag('%wpov-result%', '([^&]+)'); 
                    add_rewrite_tag('%wpov-voter-result%', '([^&]+)');  
                    add_rewrite_tag('%wpov-compare%', '([^&]+)');  
                    break;
            }

        }        
        
        
        function add_to_twig($twig) {
            $twig->addExtension( new Twig_Extension_StringLoader() );
            $twig->addFilter( new Twig_SimpleFilter( 'myfoo', array( $this, 'myfoo' ) ) );
            return $twig;            
        }
        
        function add_to_context($context) {
            
            $context['current_voter'] = wpov_get_current_voter();
            $context['admin_settings'] = $this->get_setting('admin_settings');   
            
            return $context;
        }
        
        function cmb2_meta_box_url($cmb2_url) {
            return str_replace(WPOV__PLUGIN_DIR, WPOV__PLUGIN_DIR_URL, $cmb2_url);
        }
        
        function register_post_types() {

            // vars
            $cap = wpov_get_setting('capability');
            
            
            register_post_type('wpov-party', array(
                'labels'			=> array(
                    'name'					=> __( 'Parties', 'wpov' ),
                    'singular_name'			=> __( 'Party', 'wpov' ),
                    'add_new'				=> __( 'Add New' , 'wpov' ),
                    'add_new_item'			=> __( 'Add New Party' , 'wpov' ),
                    'edit_item'				=> __( 'Edit Party' , 'wpov' ),
                    'new_item'				=> __( 'New Party' , 'wpov' ),
                    'view_item'				=> __( 'View Party', 'wpov' ),
                    'search_items'			=> __( 'Search Parties', 'wpov' ),
                    'not_found'				=> __( 'No Parties found', 'wpov' ),
                    'not_found_in_trash'	=> __( 'No Parties found in Trash', 'wpov' ), 
                ),
                'public'			=> false,
                'show_ui'			=> true,
                '_builtin'			=> false,
                'capability_type'	=> 'post',
                'capabilities'		=> array(
                    'edit_post'			=> $cap,
                    'delete_post'		=> $cap,
                    'edit_posts'		=> $cap,
                    'delete_posts'		=> $cap,
                ),
                'hierarchical'		=> true,
                'rewrite'			=> false,
                'query_var'			=> false,
                'supports' 			=> array('title', 'thumbnail', 'revisions'),
                'show_in_menu'		=> 'wpov-dashboard'
            ));                
            

            // register post type 'acf-field-group'
            register_post_type('wpov-question', array(
                'labels'			=> array(
                    'name'					=> __( 'Questions', 'wpov' ),
                    'singular_name'			=> __( 'Question', 'wpov' ),
                    'add_new'				=> __( 'Add New' , 'wpov' ),
                    'add_new_item'			=> __( 'Add New Question' , 'wpov' ),
                    'edit_item'				=> __( 'Edit Question' , 'wpov' ),
                    'new_item'				=> __( 'New Question' , 'wpov' ),
                    'view_item'				=> __( 'View Question', 'wpov' ),
                    'search_items'			=> __( 'Search Questions', 'wpov' ),
                    'not_found'				=> __( 'No Questions found', 'wpov' ),
                    'not_found_in_trash'	=> __( 'No Questions found in Trash', 'wpov' ), 
                ),
                'public'			=> false,
                'show_ui'			=> true,
                '_builtin'			=> false,
                'capability_type'	=> 'post',
                'capabilities'		=> array(
                    'edit_post'			=> $cap,
                    'delete_post'		=> $cap,
                    'edit_posts'		=> $cap,
                    'delete_posts'		=> $cap,
                ),
                'hierarchical'		=> true,
                'rewrite'			=> false,
                'query_var'			=> false,
                'supports' 			=> array('editor', 'revisions'),
                'show_in_menu'		=> 'wpov-dashboard'             
            ));
                                
            $wpov_voting = array(
                'labels'			=> array(
                    'name'					=> __( 'Votings', 'wpov' ),
                    'singular_name'			=> __( 'Voting', 'wpov' ),
                    'add_new'				=> __( 'Add New' , 'wpov' ),
                    'add_new_item'			=> __( 'Add New Voting' , 'wpov' ),
                    'edit_item'				=> __( 'Edit Voting' , 'wpov' ),
                    'new_item'				=> __( 'New Voting' , 'wpov' ),
                    'view_item'				=> __( 'View Voting', 'wpov' ),
                    'search_items'			=> __( 'Search Votings', 'wpov' ),
                    'not_found'				=> __( 'No Votings found', 'wpov' ),
                    'not_found_in_trash'	=> __( 'No Votings found in Trash', 'wpov' ), 
                ),
                'public'			=> false,
                'show_ui'			=> true,
                '_builtin'			=> false,
                'capability_type'	=> 'post',
                'capabilities'		=> array(
                    'edit_post'			=> $cap,
                    'delete_post'		=> $cap,
                    'edit_posts'		=> $cap,
                    'delete_posts'		=> $cap,
                ),
                'hierarchical'		=> true,
                'rewrite'			=> false,
                'query_var'			=> false,
                'supports' 			=> array('title', 'revisions'),
                'show_in_menu'		=> 'wpov-dashboard',
            );
            
            if($this->get_setting('admin_settings')['wpov_type'] == 'standalone') {
                $wpov_voting['public'] = true;
                $wpov_voting['rewrite'] = array('slug' => 'voting');
            }
            
            register_post_type('wpov-voting', $wpov_voting);            
            

        }
        
        function has_setting($key) {
            return isset($this->settings[$key]);
        }        
        
        function get_setting($key) {
            if($this->has_setting($key)) {
                return $this->settings[$key];
            }
        }
        
        function set_setting($key, $value) {
            $this->settings[$key] = $value;
        }        
        
    }
    
    function wpov() {
        global $wpov;
        
        // initialize
        if( !isset($wpov) ) {
            $wpov = new wpov();
            $wpov->initialize();
        }

        // return
        return $wpov;        
    }
    
    wpov();

else:
    die(sprintf(__('Class »%s« exists!', PLUGIN_NAME_SLUG), 'wpov'));
endif;
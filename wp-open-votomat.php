<?php
/*
 * Plugin Name: WP Open Votomat
 * Description: This plugin allows you to use your website as a voting advice application.
 * Author: magma design studio
 * Version: 1.0.2
 * Author URI: https://magmadesignstudio.de
 * Text Domain: wpov
 * License:     GPL2
 
{Plugin Name} is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
any later version.
 
{Plugin Name} is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with {Plugin Name}. If not, see {License URI}. 
 
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'WPOV__PLUGIN_NAME_SLUG', 'wp-open-votomat' );

define( 'WPOV_VERSION', '1.0.2' );
define( 'WPOV__MINIMUM_WP_VERSION', '4.0' );
define( 'WPOV__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPOV__PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );

define( 'WPOV__PLUGIN_THEMES_DIR', WPOV__PLUGIN_DIR . 'wpov_template' );
define( 'WPOV__PLUGIN_THEMES_DIR_URL', WPOV__PLUGIN_DIR_URL . 'wpov_template' );

$wpov_settings = get_option('wpov-settings', array());
$theme_dir = ((isset($wpov_settings['wpov_theme']) and ($theme_dir = $wpov_settings['wpov_theme'])) ? $theme_dir : 'twentynineteen');

define( 'WPOV__PLUGIN_THEME_DIR', WPOV__PLUGIN_THEMES_DIR . '/' . $theme_dir );
define( 'WPOV__PLUGIN_THEME_DIR_URL', WPOV__PLUGIN_THEMES_DIR_URL . '/' . $theme_dir );

if(!class_exists('wpov')) :

    class wpov {
        public $current_voter = false;
        
        protected $settings = array(
            'capability' => 'manage_options',
            'admin_settings' => array(
                'wpov_type' => 'standalone',
                'wpov_css_external' => false,
                'wpov_css' => false,
            )
        );
        
        public $post_types = array();
        
        function migrate() {
            global $wpdb;
            
            ini_set('memory_limit', '1024M');
            set_time_limit(0);      
            
            //$wpdb->query("UPDATE $wpdb->postmeta SET meta_value=0 WHERE meta_key REGEXP '^_wpov_counter_question_[0-9]+_.*$' ");
            //die('reset');
            
            $_posts = $wpdb->get_results("
            SELECT *, COUNT(*) as count
            FROM $wpdb->postmeta 
            WHERE 
                meta_key REGEXP '^_wpov_voting_[0-9]+_question_[0-9]+$'
            GROUP BY meta_key, meta_value
            #LIMIT 10
            ");
            
                        
            foreach($_posts as $_post) {
                
                preg_match('/_wpov_voting_(?<voting_id>\d+)_question_(?<question_id>\d+)/', $_post->meta_key, $match);
                
                $answer = preg_replace('/^([^\:]+)(:)?(twice)$/', '$1', $_post->meta_value);
                $meta_key = "_wpov_counter_question_{$match['question_id']}_{$answer}";
                $meta_value = intval($_post->count);
                
                if($_meta_value = get_post_meta( $match['voting_id'], $meta_key, true )) {
                    $meta_value += intval($_meta_value);
                }
                
                print_r([$match['voting_id'], $meta_key, $meta_value]);
                
                if ( ! add_post_meta( $match['voting_id'], $meta_key, $meta_value, true ) ) { 
                   update_post_meta( $match['voting_id'], $meta_key, $meta_value );
                }                         


            }
            
            echo 'finished!';
            
            exit;
        }
        
        function __construct() {
            //add_action('wp_loaded', array($this, 'migrate'));
            global $wpov_settings;
            $this->set_setting('admin_settings', wp_parse_args( $wpov_settings, $this->get_setting('admin_settings')));
        }
        
        function detect_plugin_activation_process() {
            add_option( 'wpov_detect_plugin_activation_process', true );
        }
        
        function initialize() {
            register_activation_hook( __FILE__, array($this, 'detect_plugin_activation_process') );
            
            add_action( 'init', array($this, 'wpov_textdomain'), 0 );  
            add_action( 'admin_init', array($this, 'initial_plugin_setup'), 0 );  
            
            include_once( WPOV__PLUGIN_DIR . 'includes/helpers/helpers.php');
            wpov_include('/includes/helpers/twig_extensions.php');
            
            wpov_include('/vendor/autoload.php');
            wpov_include('/vendor/cmb2/init.php');
            
            wpov_include('/includes/api/api.php');
            wpov_include('/includes/api/wpov_party.php');
            wpov_include('/includes/api/wpov_party_answer.php');
            wpov_include('/includes/api/wpov_question.php');
            wpov_include('/includes/api/wpov_voting.php');
            
            
            add_action('init',	array($this, 'register_post_types'), 5);
            
            if($this->get_setting('admin_settings')['wpov_type'] == 'standalone') {
                add_action( 'widgets_init', array( $this, 'wpov_widget_home_sidebar' ) );
                add_filter( 'validate_current_theme', '__return_false' );
            }             
            
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
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }                
                
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
            
            add_action( 'init', array($this, 'rewrite_rules'));     
            
            add_action( 'wp_enqueue_scripts', array($this, 'custom_assets'), 1000); 
            add_action( 'wp_ajax_wpov_reset_user_votings', array($this, 'ajax_reset_user_votings'));    

        }

        function ajax_reset_user_votings() {
                        
            if(empty($_POST['post_id']) or !is_object($voting = wpov_get_voting($_POST['post_id']))) {
                die(__('Post not found!', WPOV__PLUGIN_NAME_SLUG));
            }
            
            if(!current_user_can(wpov_get_setting('capability'))) {
                die(__('Not permitted!', WPOV__PLUGIN_NAME_SLUG));
            }
            
            $status = $voting->publication_status_array();
            
            if($status['time_to_start'] <= 0) {
                die(__('Not allowed!', WPOV__PLUGIN_NAME_SLUG));
            }
            
            if($post_ids = $voting->reset_user_votings()) {
                die(__(sprintf('%d user votings have been deleted!', count($post_ids)), WPOV__PLUGIN_NAME_SLUG));
            } else {
                die(__(sprintf('No user voting was deleted!', count($post_ids)), WPOV__PLUGIN_NAME_SLUG));
            }
                        
            wp_die();
        }        
        
        
        function custom_assets() {

            wp_register_style( 'wpov-custom-css', false );
            wp_enqueue_style( 'wpov-custom-css' );            
            if($wpov_css_external = $this->get_setting('admin_settings')['wpov_css_external']) {
                wp_enqueue_style( 'wpov-custom-css-external', $wpov_css_external );
            }
            if($wpov_css = $this->get_setting('admin_settings')['wpov_css']) {
                wp_add_inline_style( 'wpov-custom-css', $wpov_css );
            }      
            
            wp_enqueue_script( 'wpov-base', WPOV__PLUGIN_DIR_URL . 'assets/js/wpov.js', array(  ), WPOV_VERSION, true );    
            
            $this->register_js_translation('The link was copied!');
            
            wp_add_inline_script( 'wpov-base', sprintf('var wpov = %s', json_encode(array(
                'wp' => array(
                    'WPLANG' => get_option('WPLANG'),
                ),
                'translations' => $this->get_js_translations()
            ))), 'before');  
            
        }
        
        function register_js_translation($text) {
            global $wpov_string_translations;
            
            if(empty($wpov_string_translations)) {
                $wpov_string_translations = array();
            }
            
            $wpov_string_translations[$text] = __($text, WPOV__PLUGIN_NAME_SLUG);
        }
        
        function get_js_translations() {
            global $wpov_string_translations;
            
            return $wpov_string_translations;
        }
        
        function initial_plugin_setup() {
            if(get_option( 'wpov_detect_plugin_activation_process')) {
                delete_option( 'wpov_detect_plugin_activation_process' );
                
                flush_rewrite_rules();
                //$this->register_voter_db_tables();
            }
        }
        
        function wpov_textdomain() {
            $locale = get_locale();
            $domain = WPOV__PLUGIN_NAME_SLUG;
            $locale = apply_filters( 'plugin_locale', $locale, $domain );
            $path = WPOV__PLUGIN_DIR . '/languages';
            // Load the textdomain according to the plugin first
            $mofile = sprintf('%s-%s.mo', $domain, $locale);
            $loaded = load_textdomain( $domain, $path . '/' . $mofile );

            // If not loaded, load from the languages directory
            if ( ! $loaded ) {
                $mofile = WP_LANG_DIR . '/plugins/' . $mofile;
                load_textdomain( $domain, $mofile );
            }            
            
        }
    
        function register_menu() {
            register_nav_menu( 'wpov_footer', __( 'WPOV Footer Menu', WPOV__PLUGIN_NAME_SLUG ) );
        }        
        
        function rewrite_rules() {
            switch($this->get_setting('admin_settings')['wpov_type']) {
                case 'standalone':
                    global $wp_rewrite;
                    $wp_rewrite->set_permalink_structure( '/%post_id%/' );
                    
                    add_rewrite_rule('^voting/([^/]+)/question/([^/]+)/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-question=$matches[2]', 'top');
                    add_rewrite_rule('^voting/([^/]+)/result/([^/]+)/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-result=true&wpov-voter-result=$matches[2]', 'top');
                    add_rewrite_rule('^voting/([^/]+)/result/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-result=true', 'top');
                    add_rewrite_rule('^voting/([^/]+)/compare/?', 'index.php?post_type=wpov-voting&pagename=$matches[1]&wpov-compare=true', 'top');
                    add_rewrite_rule('voting/(.+?)(?:/([0-9]+))?/?$', 'index.php?post_type=wpov-voting&pagename=$matches[1]&page=$matches[2]', 'top');
                    //add_rewrite_rule('^voting/?', 'index.php?wpov-voting=true', 'top');

                    //add_rewrite_tag('%wpov-voting%', '([^&]+)');
                    add_rewrite_tag('%wpov-voting%', '([^&]+)');  
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
            
            //$context['current_voter'] = wpov_get_current_voter();
            $context['admin_settings'] = $this->get_setting('admin_settings');   
            
            $context['WPOV__PLUGIN_NAME_SLUG'] = WPOV__PLUGIN_NAME_SLUG;
            $context['site'] = new Timber\Site;
                           
            return $context;
        }
        
        function cmb2_meta_box_url($cmb2_url) {
            return str_replace(WPOV__PLUGIN_DIR, WPOV__PLUGIN_DIR_URL, $cmb2_url);
        }
        
        function register_post_types() {

            // vars
            $cap = wpov_get_setting('capability');
            
            $this->post_types[] = register_post_type('wpov-party', array(
                'labels'			=> array(
                    'name'					=> __( 'Parties', WPOV__PLUGIN_NAME_SLUG ),
                    'singular_name'			=> __( 'Party', WPOV__PLUGIN_NAME_SLUG ),
                    'add_new'				=> __( 'Add New' , WPOV__PLUGIN_NAME_SLUG ),
                    'add_new_item'			=> __( 'Add New Party' , WPOV__PLUGIN_NAME_SLUG ),
                    'edit_item'				=> __( 'Edit Party' , WPOV__PLUGIN_NAME_SLUG ),
                    'new_item'				=> __( 'New Party' , WPOV__PLUGIN_NAME_SLUG ),
                    'view_item'				=> __( 'View Party', WPOV__PLUGIN_NAME_SLUG ),
                    'search_items'			=> __( 'Search Parties', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found'				=> __( 'No Parties found', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found_in_trash'	=> __( 'No Parties found in Trash', WPOV__PLUGIN_NAME_SLUG ), 
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
                'supports' 			=> array('title', 'thumbnail', 'revisions', 'editor'),
                'show_in_menu'		=> 'wpov-dashboard',
                'menu_position'     => 50
            ));                
            

            // register post type 'acf-field-group'
            $this->post_types[] = register_post_type('wpov-question', array(
                'labels'			=> array(
                    'name'					=> __( 'Questions', WPOV__PLUGIN_NAME_SLUG ),
                    'singular_name'			=> __( 'Question', WPOV__PLUGIN_NAME_SLUG ),
                    'add_new'				=> __( 'Add New' , WPOV__PLUGIN_NAME_SLUG ),
                    'add_new_item'			=> __( 'Add New Question' , WPOV__PLUGIN_NAME_SLUG ),
                    'edit_item'				=> __( 'Edit Question' , WPOV__PLUGIN_NAME_SLUG ),
                    'new_item'				=> __( 'New Question' , WPOV__PLUGIN_NAME_SLUG ),
                    'view_item'				=> __( 'View Question', WPOV__PLUGIN_NAME_SLUG ),
                    'search_items'			=> __( 'Search Questions', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found'				=> __( 'No Questions found', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found_in_trash'	=> __( 'No Questions found in Trash', WPOV__PLUGIN_NAME_SLUG ), 
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
                'show_in_menu'		=> 'wpov-dashboard',
                'menu_position'     => 100
            ));
                                
            $wpov_voting = array(
                'labels'			=> array(
                    'name'					=> __( 'Votings', WPOV__PLUGIN_NAME_SLUG ),
                    'singular_name'			=> __( 'Voting', WPOV__PLUGIN_NAME_SLUG ),
                    'add_new'				=> __( 'Add New' , WPOV__PLUGIN_NAME_SLUG ),
                    'add_new_item'			=> __( 'Add New Voting' , WPOV__PLUGIN_NAME_SLUG ),
                    'edit_item'				=> __( 'Edit Voting' , WPOV__PLUGIN_NAME_SLUG ),
                    'new_item'				=> __( 'New Voting' , WPOV__PLUGIN_NAME_SLUG ),
                    'view_item'				=> __( 'View Voting', WPOV__PLUGIN_NAME_SLUG ),
                    'search_items'			=> __( 'Search Votings', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found'				=> __( 'No Votings found', WPOV__PLUGIN_NAME_SLUG ),
                    'not_found_in_trash'	=> __( 'No Votings found in Trash', WPOV__PLUGIN_NAME_SLUG ), 
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
                'supports' 			=> array('title', 'revisions', 'editor'),
                'show_in_menu'		=> 'wpov-dashboard',
                'menu_position'     => 150
            );
            
            if($this->get_setting('admin_settings')['wpov_type'] == 'standalone') {
                $wpov_voting['public'] = true;
                $wpov_voting['rewrite'] = array('slug' => 'voting' );
                $wpov_voting['query_var'] = '/?wpov-voting={single_post_slug}';
            }
            
            $this->post_types[] = register_post_type('wpov-voting', $wpov_voting);            
            

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
        
        function get_settings() {
            return $this->settings;
        }
        
        function wpov_widget_home_sidebar() {
            register_sidebar( array(
                'name'          => __('WP Open Votomat Sidebar', WPOV__PLUGIN_NAME_SLUG),
                'id'            => 'wpov_home_sidebar',
                'before_widget' => '<div>',
                'after_widget'  => '</div>',
                'before_title'  => '<h3>',
                'after_title'   => '</h3>',
            ) );            
        }
        
        function register_voter_db_tables() {
            /*global $wpdb;
            
            $charset_collate = $wpdb->get_charset_collate();
            $table_name_voters = wpov_wpdb_voters();
            $table_name_voters_vote = wpov_wpdb_voters_votes();
            
            $sql_table_creation = array();
            $sql_table_creation[] = "CREATE TABLE $table_name_voters (
              voter_id mediumint(9) NOT NULL AUTO_INCREMENT,
              voter_session datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
              voter_ tinytext NOT NULL,
              text text NOT NULL,
              url varchar(55) DEFAULT '' NOT NULL,
              PRIMARY KEY  (voter_id)
            ) $charset_collate;";            
            
            
            $sql_table_creation[] 
            */
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
    die(sprintf(__('Class »%s« exists!', WPOV__PLUGIN_NAME_SLUG), WPOV__PLUGIN_NAME_SLUG));
endif;
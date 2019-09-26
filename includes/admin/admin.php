<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin') ) :

class wpov_admin {
	
	// vars
	var $notices = array();
	
	
	/*
	*  __construct
	*
	*  Initialize filters, action, variables and includes
	*
	*  @type	function
	*  @date	23/06/12
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function __construct() {
	
		// actions
        
		add_action('admin_menu', 			array($this, 'admin_menu'));
		add_action('admin_menu', 			array($this, 'register_settings_pages'));
        
        
        add_action('init',        array($this, 'admin_post_type_filters'));
        add_action('init',        array($this, 'admin_settings_filters'));
        
        //$this->admin_post_type_filters();
                
		add_action('admin_enqueue_scripts',	array($this, 'admin_enqueue_scripts'), 0);
		//add_action('admin_notices', 		array($this, 'admin_notices'));
		
        //add_filter('submenu_file', array($this, 'submenu_file'));
        //add_filter('parent_file', array($this, 'submenu_file'));
        
        add_action('add_meta_boxes', array($this, 'metabox_rename_thumbnail'));
        
        
        add_action('restrict_manage_posts', array($this, 'admin_column_filters'));
        add_action('posts_join', array($this, 'admin_column_filters_query_join'));
        add_action('posts_where', array($this, 'admin_column_filters_query_where'));
        
        add_filter( 'admin_menu', array( $this, 'submenu_order' ) );
        
        if(wpov_get_setting('admin_settings')['wpov_type'] == 'standalone') {
            add_action( 'admin_notices', array($this, 'show_override_notes') );
        }

	}
    
    function show_override_notes() {
        $screen = get_current_screen();
        $message = false;
        switch($screen->base) {
            case 'options-reading':
                $message = __('Your homepage display settings will be overriden by the WP-Open-Votomat Settings!', WPOV__PLUGIN_NAME_SLUG);
                break;
            case 'options-permalink':
                $message = __('Your Permalink Settings will be overriden by the WP-Open-Votomat Settings!', WPOV__PLUGIN_NAME_SLUG);
                break;                
        }
        
        if($message) {
            printf( '<div class="%1$s"><p><strong>WP-Open-Votomat:</strong> <code>%2$s</code></p></div>', esc_attr( 'notice notice-warning' ), esc_html( $message ) ); 
        }
    }
    
    function submenu_order($menu_order) {
        global $submenu;
        
        $order = array(
            'wpov-dashboard',
            'edit.php?post_type=wpov-party',
            'edit.php?post_type=wpov-question',
            'edit.php?post_type=wpov-voting',
            'wpov-settings'
        );
        
        $wpov_submenu = $submenu['wpov-dashboard'];
        $_wpov_submenu = array();
        foreach($wpov_submenu as $item) {
            $_wpov_submenu[array_search($item[2], $order)] = $item;
        }
        ksort( $_wpov_submenu );
        $submenu['wpov-dashboard'] = $_wpov_submenu;
        
        return $menu_order;        
    }
    
    function admin_column_filters() {
        if(
            empty($_GET['post_type']) or 
            !in_array($_GET['post_type'], array('wpov-question', 'wpov-party'))
        ) {
            return;
        }
        $votings = wpov_get_votings();
        ?>
        <select name="wpov-voting">
            <option value=""><?php _e('All Votings', WPOV__PLUGIN_NAME_SLUG); ?></option>
            <?php foreach($votings as $voting) : ?>
            <option <?php selected( $this->admin_column_filters_query_permission(), $voting->get_id()) ?> value="<?php echo $voting->get_id(); ?>"><?php echo $voting->title(); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }
    
    function admin_column_filters_query_permission() {
        global $pagenow;
        
        $types = array(
            'wpov-question' => 'questions', 
            'wpov-party' => 'parties'
        );        
        
        if(
            $pagenow=='edit.php' && (
                isset($_GET['post_type']) and 
                in_array($_GET['post_type'], array_keys($types))
            ) and (
                isset($_GET['wpov-voting']) and
                is_numeric($_GET['wpov-voting'])
            )    
        ) {
            return $_GET['wpov-voting'];
        } else {
            return false;
        }
    }
    
    function admin_column_filters_query_join($join) {
        global $pagenow;
        
        $types = array(
            'wpov-question' => 'questions', 
            'wpov-party' => 'parties'
        );
        
        if (
            $wpov_voting = $this->admin_column_filters_query_permission()
        ) {
            $type = $types[$_GET['post_type']];
            
            global $wpdb;
            $join .= "JOIN {$wpdb->postmeta} as wpov_pm ON ( 
                wpov_pm.meta_key = '_voting_{$type}' 
                #AND
                #wpov_pm.meta_value LIKE '%\"{$wpov_voting}\"%' AND
                #wpov_pm.post_id = {$wpdb->posts}.ID
            )";

        }   
        return $join;
    }
    
    function admin_column_filters_query_where($where) {
        
        if (
            $wpov_voting = $this->admin_column_filters_query_permission()
        ) {            
            global $wpdb;
            $where .= " AND wpov_pm.meta_value LIKE CONCAT('%\"', {$wpdb->posts}.ID, '\"%') AND
            wpov_pm.post_id = {$wpov_voting}
            ";
        }   
        return $where;        
    }
    
    function metabox_rename_thumbnail($wp_meta_boxes) {
        global $wp_meta_boxes; 
                
        $wp_meta_boxes['wpov-party']['side']['low']['postimagediv']['title'] = __('Party Logo', WPOV__PLUGIN_NAME_SLUG);
    }
    
    function set_current_menu( $parent_file ) {
        global $submenu_file, $current_screen, $pagenow;

        # Set the submenu as active/current while anywhere in your Custom Post Type (nwcm_news)
        if ( in_array($current_screen->post_type, array('wpov-question', 'wpov-party'))) {

            if ( $pagenow == 'post.php' ) {
                $submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
            }

            if ( $pagenow == 'edit-tags.php' ) {
                $submenu_file = 'edit-tags.php?taxonomy=wpov-issue&post_type=' . $current_screen->post_type;
            }

            $parent_file = 'wpov-dashboard';

        }

        return $parent_file;

    }
	
	/*
	*  add_notice
	*
	*  This function will add the notice data to a setting in the acf object for the admin_notices action to use
	*
	*  @type	function
	*  @date	17/10/13
	*  @since	5.0.0
	*
	*  @param	$text (string)
	*  @param	$class (string)
	*  @param	wrap (string)
	*  @return	n/a
	*/
	
	function add_notice( $text = '', $class = '', $wrap = 'p' ) {
		
		// append
		$this->notices[] = array(
			'text'	=> $text,
			'class'	=> 'updated ' . $class,
			'wrap'	=> $wrap
		);
		
	}
	
	
	/*
	*  get_notices
	*
	*  This function will return an array of admin notices
	*
	*  @type	function
	*  @date	17/10/13
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	(array)
	*/
	
	function get_notices() {
		
		// bail early if no notices
		if( empty($this->notices) ) return false;
		
		
		// return
		return $this->notices;
		
	}
	
	
	/*
	*  admin_menu
	*
	*  This function will add the ACF menu item to the WP admin
	*
	*  @type	action (admin_menu)
	*  @date	28/09/13
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function admin_menu() {
		
		
		// vars
		$slug = 'wpov-dashboard';
		$cap = wpov_get_setting('capability');
		        
		
		// add parent
		add_menu_page(
            __("Open Votomat",WPOV__PLUGIN_NAME_SLUG), 
            __("Open Votomat",WPOV__PLUGIN_NAME_SLUG), 
            $cap, 
            $slug, 
            array('wpov_admin_options_dashboard', 'add_page'), 
            'dashicons-welcome-widgets-menus', 
            '80.025'
        );
        
        /*
    dashboard (statistics)
    questions
    parties
    issues
    settings        
        */
		// add children
        
		add_submenu_page($slug, __('Dashboard',WPOV__PLUGIN_NAME_SLUG), __('Dashboard',WPOV__PLUGIN_NAME_SLUG), $cap, $slug );
		//add_submenu_page($slug, __('Questions',WPOV__PLUGIN_NAME_SLUG), __('Questions',WPOV__PLUGIN_NAME_SLUG), $cap, 'edit.php?post_type=wpov-question' );
		//add_submenu_page($slug, __('Parties',WPOV__PLUGIN_NAME_SLUG), __('Parties',WPOV__PLUGIN_NAME_SLUG), $cap, 'edit.php?post_type=wpov-party' );
                
		//add_submenu_page($slug, __('Issues',WPOV__PLUGIN_NAME_SLUG), __('Issues',WPOV__PLUGIN_NAME_SLUG), $cap, 'edit.php?post_type=wpov-issue' );
		//add_submenu_page($slug, __('Settings',WPOV__PLUGIN_NAME_SLUG), __('Settings',WPOV__PLUGIN_NAME_SLUG), $cap, 'wpov-settings', array($this, 'option_page') ); //array($this, 'option_page')
        //add_options_page( __('Settings',WPOV__PLUGIN_NAME_SLUG), __('Settings',WPOV__PLUGIN_NAME_SLUG), $cap, 'wpov-settings', array($this, 'option_page'));

        //add_options_page( __('Settings',WPOV__PLUGIN_NAME_SLUG), __('Settings',WPOV__PLUGIN_NAME_SLUG), $cap, 'wpov-settings', array('wpov_admin_options_settings', 'add_page'));
        
		//add_submenu_page($slug, __('Add New',WPOV__PLUGIN_NAME_SLUG), __('Add New',WPOV__PLUGIN_NAME_SLUG), $cap, 'post-new.php?post_type=acf-field-group' );
		
        
	}
    
    function admin_post_type_filters() {
        $post_type = $_REQUEST['post_type'];
        
        if($_REQUEST['post'] and ($post = get_post($_REQUEST['post']))) {
            $post_type = $post->post_type;
        }
        
        if(empty($post_type)) return;

        
        $id = preg_replace('/^wpov-/', null, $post_type);
        
        if(wpov_include_exists("/includes/admin/post_type-{$id}.php")) {
            wpov_include("/includes/admin/post_type-{$id}.php");
        }
    }
    
    function admin_settings_filters() {
        return;
        global $pagenow;
        
        if($pagenow == 'admin.php' or $pagenow == 'options-general.php') {
            if($page = $_REQUEST['page']) {
                
                $class = 'wpov_admin_options_'.preg_replace('/^wpov-/', null, $page);
                if(class_exists($class)) {
                    new $class();
                }
            }
        }
        
    }
    
    function option_page() {
        return;
        $page = $_REQUEST['page'];        
        $class = 'wpov_admin_options_'.preg_replace('/^wpov-/', null, $page);
        if(class_exists($class)) {
            $page = new $class();
            $page->add_page();
        }        
    }
	
    function register_settings_pages() {
        register_setting( 'myoption-group', 'new_option_name' );
    }
	
	/*
	*  admin_enqueue_scripts
	*
	*  This function will add the already registered css
	*
	*  @type	function
	*  @date	28/09/13
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function admin_enqueue_scripts() {
        wp_register_script( 'wpov_admin_chart-js', WPOV__PLUGIN_DIR_URL . '/bower_components/chart.js/dist/Chart.bundle.js', array(), false, true );
        
        wp_register_script( 'wpov_admin', WPOV__PLUGIN_DIR_URL . '/backend_assets/js/wpov-admin.js', array(), false, true );
        wp_register_script( 'wpov_admin_dashboard', WPOV__PLUGIN_DIR_URL . '/backend_assets/js/wpov-admin-dashboard.js', array( 'wpov_admin_chart-js' ), false, true );
                
        wp_enqueue_style( 'wpov-admin', WPOV__PLUGIN_DIR_URL . '/backend_assets/css/wpov-admin.css' );
        
        wp_enqueue_script( 'wpov_admin' );
        
        if(isset($_GET['page']) and $_GET['page'] == 'wpov-dashboard') {
            wp_enqueue_script( 'wpov_admin_dashboard' );
        }
	}
	
	
	/*
	*  admin_notices
	*
	*  This function will render any admin notices
	*
	*  @type	function
	*  @date	17/10/13
	*  @since	5.0.0
	*
	*  @param	n/a
	*  @return	n/a
	*/
	
	function admin_notices() {
		
		// vars
		$notices = $this->get_notices();
		
		
		// bail early if no notices
		if( !$notices ) return;
		
		
		// loop
		foreach( $notices as $notice ) {
			
			$open = '';
			$close = '';
				
			if( $notice['wrap'] ) {
				
				$open = "<{$notice['wrap']}>";
				$close = "</{$notice['wrap']}>";
				
			}
				
			?>
			<div class="acf-admin-notice notice is-dismissible <?php echo esc_attr($notice['class']); ?>"><?php echo $open . $notice['text'] . $close; ?></div>
			<?php
				
		}
		
	}
	
}

// initialize
wpov()->admin = new wpov_admin();

endif; // class_exists check
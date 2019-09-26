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
        if(empty(session_id())) {
            session_start();
        }
        
        $this->title = __( 'Settings', WPOV__PLUGIN_NAME_SLUG );
        
        add_action( "cmb2_save_field_migration_symfony_path", array($this, 'start_migrate_symfony_sql_dump'), 10, 3 );
        
        if(isset($_GET['wpov-migration'])) {
            add_action('admin_init', array($this, 'migrate_symfony_sql_dump'));
        }
        
        
        add_action( 'admin_print_scripts', array($this, 'admin_js_print_wpov_settings') );

    }
    
    function admin_js_print_wpov_settings() {
        ?>
        <script type="text/javascript">
            var wpov_settings = <?php echo json_encode(wpov_get_settings()); ?>;
        </script>
        <?php
    }
    
    function migrate_symfony_translate_table_fields($dataset = array(), $translations = array()) {
        $out = array();
        foreach($dataset as $key => $value) {
            if(isset($translations[$key]) and $translations[$key]) {
                $value = trim($value, '\'');
                $value = preg_replace(array('/\\\r/', '/\\\n/'), array("\r", "\n"), $value);                                  

                if(preg_match('/^postmeta(.*)$/', $translations[$key], $match)) {
                    if(empty($out['meta_input'])) $out['meta_input'] = array();

                    $out['meta_input'][$match[1]] = $value;
                } else {
                    $out[$translations[$key]] = $value;
                }
            } elseif(!isset($translations[$key])) {
                $out[$translations[$key]] = $value;
            }
        }      
        return $out;
        
    }
    
    function set_session_data($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    function get_session_data($key, $default = false) {
        if(empty($_SESSION[$key])) {
            return $default;
        } else {
            return $_SESSION[$key];
        }
    }    
    
    function migrate_symfony_sql_dump() {
        global $wpdb;
        $wp_get_upload_dir = wp_get_upload_dir();
        $wpdb2 = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        
        $wpdb->show_errors();
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        
        define('IS_MIGRATION_DEV', false);

        $vote_translation = array(
            'approve',
            'neutral',
            'disapprove',
        );            

        echo '<code style="white-space: pre-wrap;">';
        $migration_settings = $this->get_session_data('migration_symfony');
        
        if(!$this->get_session_data('voting_id', false)) {
            $this->set_session_data('voting_id', wpov_fn('wp_insert_post', 1, array(
                'post_title' => 'Kommunalwahl 2014',
                'post_type' => 'wpov-voting',
                'post_status' => 'publish'
            )));                
        }       
        
        $voting_id = $this->get_session_data('voting_id');


        $file = $migration_settings['files'][0];
        $path = $migration_settings['path'];
        preg_match('/^(?<basename>.*)(?<suffix>.sql)$/', $path, $path);
        
        
        unset($migration_settings['files'][0]);
        $migration_settings['files'] = array_values($migration_settings['files']);
        $this->set_session_data('migration_symfony', $migration_settings);
        
        echo 'Upcoming actions'."\n";
        print_r($migration_settings['files']);
        echo "\n";
        
        $_path = sprintf('%s_%s%s', $path['basename'], $file, $path['suffix']);
        $sql = file_get_contents(sprintf('%s/%s', $wp_get_upload_dir['basedir'], $_path));

        $table = preg_replace('/^table_/', null, $file);

        echo "Crawl table $table"."\n\n";

        if($result = $wpdb2->multi_query($sql)) {
            do {
                /* store first result set */
                if ($result = $wpdb2->store_result()) {
                    while ($row = $result->fetch_row()) {
                        printf("%s\n", $row[0]);
                    }
                    $result->free();
                }

            } while ($wpdb2->next_result());                         

        }

        $query = "SELECT * FROM {$table}";
        
        if(IS_MIGRATION_DEV) {
            $query .= " LIMIT 0,50";
        }        
        
        $values = $wpdb->get_results($query, ARRAY_A);
        
        echo "Fetch {$table}"."\n";
  
        $convertions = $this->get_session_data('convertions', array());
        $this->set_session_data('convertions', $convertions);
        
        $voting_parties = array();
        $voting_questions = array();
        
        if(!is_array($convertions[$file])) $convertions[$file] = array();
        
        //set_session_data($file, get_session_data($file, array()));

        switch($file) {
            case 'table_kommunalomat_user_answer':
                $out = array();
                $convertions = $this->get_session_data('convertions');

                foreach($values as $item) {
                    $question_id = $this->get_session_data('convertions')['table_kommunalomat_question'][$item['question_id']];

                    //if(empty($out[$voting_id])) $out[$voting_id] = array();
                    //if(empty($out[$voting_id][$question_id])) $out[$voting_id][$question_id] = array();

                    if(empty($out[$item['user_id']])) $out[$item['user_id']] = array();
                    
                    $out[$item['user_id']][] = array(
                        'vote' => $vote_translation[$item['answer']],
                        'count_twice' => ($item['count_double'] == 1),
                        'id' => sprintf('%d_%d_%s_%s', $voting_id, $question_id, $vote_translation[$item['answer']], ($item['count_double'] ? 'twice' : null))
                    );

                    
                    
                }
                
                foreach($out as $user_id => $values) {
                    $voter_id = $this->get_session_data('convertions')['table_kommunalomat_user'][$user_id];
                    if(wpov_fn('wp_update_post', 1, array(
                        'ID' => $voter_id,
                        'post_content' => maybe_serialize($values),
                    ))) {
                        echo "user #$voter_id voting has been imported!"."\n";
                    } else {
                        echo "user #$voter_id voting has not been imported!"."\n";
                    }
                }
                break;
            case 'table_kommunalomat_user':
                foreach($values as $item) {

                    $post_time = strtotime($item['time_first_online']);
                    $post_date = date('Y-m-d H:i:s', $post_time);

                    $out = array(
                        'post_title' => sprintf('%s:%s', $item['session'], $post_time),
                        'post_type' => 'wpov-user-vote',
                        'post_status' => 'internal',
                        'post_date' => $post_date,
                        'post_modified' => $post_date,
                    );

                    $post_id = wpov_fn('wp_insert_post', $item['id'], $out);
                    if(!is_wp_error($post_id)) {
                        $convertions[$file][$item['id']] = $post_id;
                        $this->set_session_data('convertions', $convertions);
                        echo "voter $post_id has been imported!"."\n";
                    } else {
                        echo "ERROR:"."\n";
                        print_r($result->get_error_message());
                        exit;
                    }                            
                }

                break;

            case 'table_kommunalomat_question':


                foreach($values as $item) {
                    $_out = $this->migrate_symfony_translate_table_fields($item, array(
                        'id' => '_id',
                        'title' => 'post_content',
                        'explanation' => 'postmeta_question_explanation',
                        'weight' => 'menu_order',
                    ));       

                    $out = $_out;
                    $out['post_title'] = wp_trim_words(sanitize_text_field($out['post_content']), 20, '[…]');                            
                    $out['post_type'] = 'wpov-question';
                    $out['post_status'] = 'publish';

                    unset($out['_id']);

                    $post_id = wpov_fn('wp_insert_post', $_out['_id'], $out);
                    if(!is_wp_error($post_id)) {
                        $convertions[$file][$_out['_id']] = $post_id;
                        $this->set_session_data('convertions', $convertions);
                        
                        $voting_questions[] = array( 'question' => (string) $post_id );
                        echo "question $post_id has been imported!"."\n";
                    } else {
                        echo "ERROR:"."\n";
                        print_r($result->get_error_message());
                        exit;
                    }
                }

                break;                        
            case 'table_kommunalomat_party_answer':
                foreach($values as $item) {
                    $out = $this->migrate_symfony_translate_table_fields($item, array(
                        'party_id' => '_party_id',
                        'question_id' => '_question_id',
                        'answer' => 'answer',
                        'explanation' => 'explanation'
                    )); 

                    $translation = $vote_translation;

                    $wp_party_id = $convertions['table_kommunalomat_party'][$item['party_id']];
                    $wp_question_id = $convertions['table_kommunalomat_question'][$item['question_id']];                            

                    $key = sprintf('_party_answers_voting_%d_question_%d', $voting_id, $wp_question_id);
                    if ( ! wpov_fn('add_post_meta', true, $wp_party_id, $key, $translation[$out['answer']], true ) ) { 
                       wpov_fn('update_post_meta', true, $wp_party_id, $key, $translation[$out['answer']] );
                    }   

                    $key = $key . '_explanation';
                    if ( ! wpov_fn('add_post_meta', true, $wp_party_id, $key, $out['explanation'], true ) ) { 
                       wpov_fn('update_post_meta', true, $wp_party_id, $key, $out['explanation'] );
                    }                              

                    echo "party #$wp_party_id answer has been imported!"."\n";

                }                        

                break;
            case 'table_kommunalomat_party':
                foreach($values as $item) {
                    $out = $this->migrate_symfony_translate_table_fields($item, array(
                        'id' => '_id',
                        'name' => 'post_title',
                        'description' => 'post_content',
                        'image' => false,
                        'url' => 'postmeta_party_url',
                    ));

                    foreach($out as $key => &$value) {
                        switch($key) {
                            case 'meta_input':
                                foreach($value as $k => &$val) {
                                    switch($k) {
                                        case 'postmeta_party_url':
                                            $data['base_expr'] = 'http://' . $data['base_expr'];
                                            break;
                                    }                                                 
                                }
                                break;
                        }                                    
                    }

                    $out['post_type'] = 'wpov-party';
                    $out['post_status'] = 'publish';

                    echo 'Fetch table_kommunalomat_party'."\n";

                    $post_id = wpov_fn('wp_insert_post', $out['_id'], $out);
                    if(!is_wp_error($post_id)) {
                        $convertions[$file][$out['_id']] = $post_id;
                        $this->set_session_data('convertions', $convertions);
                        
                        $voting_parties[] = array( 'party' => (string) $post_id );
                        echo "party #$post_id has been imported!"."\n";
                    } else {
                        echo "ERROR:"."\n";
                        print_r($result->get_error_message());
                        exit;                                
                    }

                }
                break;                        
        }

        if ( !empty($voting_parties) and ! wpov_fn('add_post_meta', true, $voting_id, '_voting_parties', $voting_parties, true ) ) { 
            wpov_fn('update_post_meta', true, $voting_id, '_voting_parties', $voting_parties );
        }   
        
        if ( !empty($voting_questions) and  ! wpov_fn('add_post_meta', true, $voting_id, '_voting_questions', $voting_questions, true ) ) { 
            wpov_fn('update_post_meta', true, $voting_id, '_voting_questions', $voting_questions );
        }         
        
        if(!isset($wpdb->{$table})) {
            $result = $wpdb->query("DROP TABLE {$table}");
        }

        echo '</code>';
        
        if(!empty($_SESSION['migration_symfony']['files'])) {
            $redirect_url = admin_url('admin.php?page=wpov-settings&wpov-migration');
            $redirect_label = $_SESSION['migration_symfony']['files'][0];
        } else {
            $redirect_url = admin_url('admin.php?page=wpov-settings');
            $redirect_label = 'Settings';
        }
        printf('<a href="%1$s">Redirect to %2$s …</a>', $redirect_url, $redirect_label);
        //printf("<script>window.location.href = '%s';</script>", $redirect_url);
        
        exit;
    }
    
    function start_migrate_symfony_sql_dump($updated, $action, $_this) {
        
        if(isset($_this->data_to_save['migration_symfony_path']) and ($path = $_this->data_to_save['migration_symfony_path'])) {
            $this->set_session_data('voting_id', false);
            $this->set_session_data('convertions', array());
            
            $this->set_session_data('migration_symfony', array(
                'files' => array(
                    'table_kommunalomat_party',
                    'table_kommunalomat_question',
                    'table_kommunalomat_party_answer',
                    'table_kommunalomat_user',      
                    'table_kommunalomat_user_answer',                
                ),
                'path' => $path,
                'admin_migration_url' => admin_url('admin.php?page=wpov-settings&wpov-migration')
            ));
            
            header('Location: ' . admin_url('admin.php?page=wpov-settings&wpov-migration'));
            exit;
        }
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
            'id'      => '_wp_http_referer',
            'type'    => 'hidden',
            'default' => esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) )
        ) );          
        
        $themes = array(__('Please choose', WPOV__PLUGIN_NAME_SLUG));        
        foreach(scandir(WPOV__PLUGIN_THEMES_DIR) as $dir) {
            if(!is_dir(WPOV__PLUGIN_THEMES_DIR.'/'.$dir) or in_array($dir, array('.', '..'))) { continue; }
            $themes[$dir] = $dir;
        }

        $this->cmb[0]->add_field( array(
            'name'    => __('Theme', WPOV__PLUGIN_NAME_SLUG),
            'id'      => 'wpov_theme',
            'type'    => 'select',
            'options' => $themes
        ) );        
        
        $this->cmb[0]->add_field( array(
            'name'    => __('Role', WPOV__PLUGIN_NAME_SLUG),
            'id'      => 'wpov_type',
            'type'    => 'select',
            'options' => array(
                'standalone' => __('Standanlone', WPOV__PLUGIN_NAME_SLUG),
                //'shortcode' => __('Shortcode', WPOV__PLUGIN_NAME_SLUG),
                //'widget' => __('Widget', WPOV__PLUGIN_NAME_SLUG)
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
            'name' => __('App name', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'app_name',
            'type' => 'text',
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
             
        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox_3';
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
        
        $this->cmb[2]->add_field( array(
            'name' => 'Sponsors Section Title',
            'id'   => 'sponsors_section_title',
            'type' => 'text',
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
            'name' => __('Line break before this sponsor', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'seperator',
            'type' => 'checkbox',
        ) );         
        
        $this->cmb[2]->add_group_field( $group_field_id, array(
            'name' => __('Headline before this sponsor', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'column_headline',
            'type' => 'text',
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
        
        $this->cmb[2]->add_group_field( $group_field_id, array(
            'name' => 'Sponsor Website',
            'id'   => 'column_width',
            'type' => 'select',
            'options' => array(
                1 => __('1 column', WPOV__PLUGIN_NAME_SLUG),
                2 => __('2 columns', WPOV__PLUGIN_NAME_SLUG),
                3 => __('3 columns', WPOV__PLUGIN_NAME_SLUG),
            )
        ) );      
        
        
        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox_4';        
        
        self::register_metabox($metabox_id, $key);            
        
        $this->cmb[3] = new_cmb2_box( array(
            'id'         => $metabox_id,
            'title' =>  __('Custom style', WPOV__PLUGIN_NAME_SLUG),
            'hookup'     => false,
            'cmb_styles' => true,
            'show_on'    => array(
                // These are important, don't remove
                'key'   => 'options-page',
                'value' => array( $key, )
            ),
        ) );         
        
        $this->cmb[3]->add_field( array(
            'name' => __('External CSS', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'wpov_css_external',
            'type'    => 'text_url',
        ) );  
        
        $this->cmb[3]->add_field( array(
            'name' => __('Custom CSS', WPOV__PLUGIN_NAME_SLUG),
            'id'   => 'wpov_css',
            'type'    => 'textarea_code',
            'attributes' => array(
                'data-codeeditor' => json_encode( array(
                    'codemirror' => array(
                        'mode' => 'css',
                    ),
                ) ),
            ),            
            
        ) );         
        
        $key = 'wpov-settings';
        $metabox_id = 'wpov-setting_metabox_5';        
        
        self::register_metabox($metabox_id, $key);            
        
        $this->cmb[4] = new_cmb2_box( array(
            'id'         => $metabox_id,
            'title' =>  __('Migration', WPOV__PLUGIN_NAME_SLUG),
            'hookup'     => false,
            'cmb_styles' => true,
            'show_on'    => array(
                // These are important, don't remove
                'key'   => 'options-page',
                'value' => array( $key, )
            ),
        ) );         
        
        $this->cmb[4]->add_field( array(
            'name' => 'Symfony SQL-Dump',
            'id'   => 'migration_symfony_path',
            'type'    => 'text',
            'save_field' => false,
            'before' => '<code>/wp-content/uploads/</code>',
        ) );          

        return $this->cmb;
    }

}

// initialize
wpov()->wpov_admin_options_settings = (new wpov_admin_options_settings)->hooks();

endif; // class_exists check
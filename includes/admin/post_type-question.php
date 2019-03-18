<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_post_type_question') ) :

class wpov_admin_post_type_question extends wpov_admin_post_type {
    public $post_type = 'wpov-question';
    function __construct() {
        
        //die('test');
        //add_action('load_edit?post_type=wpov-party', array($this, 'add_filters'));
        $this->add_filters();
    }

    function add_filters() {
        //add_filter('enter_title_here', array($this, 'enter_title_here'));
        
        //add_action('cmb2_admin_init', array($this, 'fields'));
        
        add_action('manage_edit-'.$this->post_type.'_columns', array($this, 'admin_column_header'));
        add_action('manage_'.$this->post_type.'_posts_custom_column', array($this, 'admin_column_content'));
        
        
        add_action( 'save_post', array($this, 'update_question_title'), 10, 3 );
    }
    
    function update_question_title($post_id) {
        $post = get_post( $post_id );
        $question_content = $post->post_content;
        remove_action('save_post', array($this, 'update_question_title'));
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => wp_trim_words(sanitize_text_field($question_content), 20, '[…]')
        ));
        add_action('save_post', array($this, 'update_question_title'));
    }
    
    function admin_column_header($columns) {
        $columns['votings'] = __('In Voting', WPOV__PLUGIN_NAME_SLUG);
        return $columns;
    }
    
    function admin_column_content($column) {
        global $post;
        
        $party = new wpov_question($post);
        switch ($column) {
            case "votings":
                $votings = $party->votings('object');
                if(empty($votings)) :
                    ?>–<?php
                endif;
                ?>
                <ul>
                <?php
                foreach($votings as $voting) :
                    ?>
                    <li><a href="<?php echo $voting->edit_link(); ?>"><?php echo $voting->title(); ?></a></li>
                    <?php
                endforeach;
                ?>
                </ul>
                <?php
            break;
        }        
    }
    

    function fields() {
        $prefix = '_question_';
        
        $fields = wpov_fields( array(
            'id'            => 'question_description_metabox',
            'title'         => __( 'Question', WPOV__PLUGIN_NAME_SLUG ),
            'object_types'  => array( $this->post_type ), // Post type
            'context'       => 'normal',
            'priority'      => 'high',
            'show_names'    => false,
        ) );       
        
        do_action('wpov_admin_question_before_fields', $fields);
        
        $fields->add_field( array(
            'name'         => __( 'Question', WPOV__PLUGIN_NAME_SLUG ),
            'id'         => $prefix . 'content',
            'type'       => 'wysiwyg',
            // 'sanitization_cb' => 'my_custom_sanitization', // custom sanitization callback parameter
            // 'escape_cb'       => 'my_custom_escaping',  // custom escaping callback parameter
            'on_front'        => true, // Optionally designate a field to wp-admin only
            // 'repeatable'      => true,
        ) ); 
            
        /*$fields->add_field( array(
            'name'         => __( 'Party Logo', WPOV__PLUGIN_NAME_SLUG ),
            'id'         => $prefix . 'logo',
            'type'       => 'file',
            'on_front'        => true, // Optionally designate a field to wp-admin only
        ) );*/
        
        
        
        do_action('wpov_admin_question_after_fields', $fields);
        
    }
}

new wpov_admin_post_type_question();

endif;
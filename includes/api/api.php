<?php
abstract class wpov_api
{
    protected $atts = array(
        'ID' => 0,
        'post_author' => '',
        'post_date' => '',
        'post_date_gmt' => '',
        'post_content' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'post_status' => '',
        'comment_status' => '',
        'ping_status' => '',
        'post_password' => '',
        'post_name' => '',
        'to_ping' => '',
        'pinged' => '',
        'post_modified' => '',
        'post_modified_gmt' => '',
        'post_content_filtered' => '',
        'post_parent' => '',
        'guid' => '',
        'menu_order' => '',
        'post_type' => '',
        'post_mime_type' => '',
        'comment_count' => '',
        'filter' => '' 
    );
    
    function __construct($post = null) {
		foreach ( get_object_vars( $post ) as $key => $value )
			$this->atts[$key] = $value;        
    }
    
    function _get_meta($key = false, $single = true) {
        return get_post_meta($this->get_id(), $key, $single);
    }
    
    function set($key, $value = null) {
        $this->atts[$key] = $value;
    }
    
    function get($key, $default = null) {
        if(!empty($this->atts[$key])) {
            return $this->atts[$key];
        } 
        return $default;
    } 
    
    function get_id() {
        return $this->atts['ID'];
    }
    
    function title() {
        return $this->atts['post_title'];
    }
    
    function content() {
        return $this->atts['post_content'];
    } 
    
    function the_content() {
        return apply_filters('the_content', $this->content());
    }     
        
    function link() {
        return get_permalink($this->get_id());
    }
    
    function edit_link() {
        return get_edit_post_link($this->get_id());
    }   
    
    function html($id, $_context = array()) {
        $context = Timber::get_context();
        
        $context['this'] = $this;
        
        $context = array_merge($context, $_context);
        
        return Timber::compile( array($id), $context );
    }
    
}
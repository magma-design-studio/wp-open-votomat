<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('WPOV_Twig_Extensions') ) :

class WPOV_Twig_Extensions {
    function __construct() {
        add_filter('get_twig', array($this, 'init'));
    }
    
	function init($twig) {
        
        foreach(get_class_methods($this) as $fn) {
            if(!preg_match('/(?<type>filter|function|var)__(?<fn>.*)/', $fn, $match)) {
                continue;
            }
            
            $config = wpov_get_method_object_attrs($this, $fn);
                        
            $trigger = isset($config['trigger'])?$config['trigger']:$match['fn'];
            
            switch($match['type']) {
                case 'filter':
                    $twig->addFilter(new Twig_Filter($trigger, array($this, $fn)));        
                    break;
                case 'function':
                    $twig->addFunction(new Twig_Function($trigger, array($this, $fn)));        
                    break;          
                case 'var':
                    $twig->addGlobal($trigger, array($this, $fn));        
                    break;                          
            }                
        }            
        
        return $twig;
	}
    
    function function__is_current_voter() {
        return wpov()->current_voter;
    }    
    
    function function__current_voter() {
        return wpov_get_current_voter();
    }
    
    function function__wpov_home_url() {
        $settings = wpov_get_setting('admin_settings');
        
        switch($settings['wpov_type']){
            case 'standalone':
                return get_bloginfo('url');
                break;
            case 'shortcode':
                return '';
                break; 
            case 'widget':
                return '';
                break;                   
        }
    }
    
    function filter__var_dump($data) {
        var_dump($data);
    }
    
    function function__exit() {
        exit;
    }
}

new WPOV_Twig_Extensions();

endif;
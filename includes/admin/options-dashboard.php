<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('wpov_admin_options_dashboard') ) :

class wpov_admin_options_dashboard {
	
	public function __construct()
    {
        //add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        
    }

    public function admin_enqueue_scripts() {
    }
    
    function get_total_results_per_question($voting) {
        global $wpdb;
        $out = array();
        foreach($voting->questions() as $question) {

            $out[$question->get_id()] = array();
            foreach(array('approve', 'neutral', 'disapprove') as $status) {
                $id = sprintf('%d_%d_%s', $voting->get_id(), $question->get_id(), $status);
                
                $query_where = "
                    SELECT 
                        COUNT(ID) as count
                    FROM $wpdb->posts
                    WHERE 
                        post_content LIKE '%\"{$id}%'
                ";
                                
                $out[$question->get_id()][$status] = $wpdb->get_var($query_where);
            }
                 
        }

        return $out;
    }
    
    function get_total_results($voting) {
        global $wpdb;
        
        $party_scores = array();
        $results_per_question = array();
        
        foreach($voting->parties() as $party) {

            $id_twice = '%d_%d_%s_twice';
            
            $query = "
            SELECT 
                ID as voter_id,
            ";
            
            $query_count_sum = $query_count = array();
            foreach($party->voting_answers($voting->get_id()) as $answer) {
                
                $id = sprintf('%d_%d_%s', $voting->get_id(), $answer->question()->get_id(), $answer->answer());
                $id_twice = sprintf('%s_twice', $id);
                
                $query_count[] = "
                    ROUND (   
                        (
                            LENGTH(post_content)
                            - LENGTH( REPLACE ( post_content, \"{$id}\", \"\") ) 
                        ) / LENGTH(\"{$id}\")        
                    ) AS count_{$answer->question()->get_id()},
                    ROUND (   
                        (
                            LENGTH(post_content)
                            - LENGTH( REPLACE ( post_content, \"{$id_twice}\", \"\") ) 
                        ) / LENGTH(\"{$id_twice}\")        
                    ) AS count_{$answer->question()->get_id()}_twice                    
                ";    
                
                //$query_set_vars[] = "SET @var_count_{$answer->question()->get_id()} = 1";
                $query_count_sum[] = "count_{$answer->question()->get_id()}";
                $query_count_sum[] = "count_{$answer->question()->get_id()}_twice";
                //$id_twice = sprintf($id, $voting->get_id(), $answer->question()->get_id(), $answer->answer());
            }
            
            
            $query .= implode(', ', $query_count)."
            FROM 
                $wpdb->posts as p    
                
            WHERE post_type = 'wpov-user-vote' 
            ORDER BY
                (".implode("+", $query_count_sum).") DESC
            "
                ;
            
            //print_r($query);
            
            $voters = $wpdb->get_results($query, ARRAY_A);
            $party_voter_score = 0;
            $votes = 0;
            
            foreach($voters as $i => &$voter) {
                //$votes[$i]
                $voter['sum'] = 0;                
                foreach($voter as $type => $vote) {
                    if(!preg_match('/^count_/', $type)) continue;
                    $voter['sum'] += $vote;
                    if(!preg_match('/twice$/', $type) or $vote) {
                        $votes++;
                    }
                }
                
                $voter['party_title'] = $party->title();
                $party_voter_score += $voter['sum'];
            }
                        
            $party_scores[$party->title()] = ($party_voter_score/$votes)*100;
        }
        
        return array(
            'labels' => array_keys($party_scores),
            'data' => array_values($party_scores),
            'count_voters' => count($voters),
        );
    }    
    
    /**
     * Add options page
     */
    public function add_page() {
        $voting = wpov_get_voting(90);
        
        $total_results = self::get_total_results($voting);
        
        $total_results_per_question = self::get_total_results_per_question($voting);
        ?>
        <div class="wrap">
            <h1><?php _e('Dashboard', 'wpov'); ?></h1>
            <div id="welcome-panel" class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>Sonntagsfrage</h2>
                    <p class="about-description">Wenn am nächsten Sonntag wirklich Wahl wäre …</p>
                    <canvas id="wpov_opinion_poll_chart" data-chart="opinion_poll" data-set="#wpov_opinion_poll_chart_data"></canvas>
                    <script id="wpov_opinion_poll_chart_data" type="text/template">
                        <?php
                        echo json_encode($total_results);
                        ?>
                    </script>
                </div>
            </div>
            <div class="welcome-panel">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Frage</th>
                            <th>Umfrage</th>
                        </tr>
                    </thead>
                    <?php foreach($voting->questions() as $question) : ?>
                    <tr>
                        <td>
                            #<?php //echo $question->question_index_readable(); ?>
                        </td>
                        <td>
                            <?php echo $question->question(); ?>
                        </td>
                        <td>
                            <canvas data-chart="opinion_poll_questions"></canvas>
                            <script type="text/template">
                                <?php
                                echo json_encode(array_values($total_results_per_question[$question->get_id()]));
                                ?>
                            </script>                            
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'my_option_group', // Option group
            'my_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'My Custom Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'my-setting-admin' // Page
        );  

        add_settings_field(
            'id_number', // ID
            'ID Number', // Title 
            array( $this, 'id_number_callback' ), // Callback
            'my-setting-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'title', 
            'Title', 
            array( $this, 'title_callback' ), 
            'my-setting-admin', 
            'setting_section_id'
        );      
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

        if( isset( $input['title'] ) )
            $new_input['title'] = sanitize_text_field( $input['title'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {
        printf(
            '<input type="text" id="id_number" name="my_option_name[id_number]" value="%s" />',
            isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function title_callback()
    {
        printf(
            '<input type="text" id="title" name="my_option_name[title]" value="%s" />',
            isset( $this->options['title'] ) ? esc_attr( $this->options['title']) : ''
        );
    }
	
}

// initialize
//wpov()->admin_settings_dashboard = new wpov_admin_settings_dashboard();

endif; // class_exists check
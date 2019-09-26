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
    
    function get_total_results($voting) {
        global $wpdb;
        
        $party_scores = array();
        $results_per_question = array();
        $total_voters = 0;

        $voters = $wpdb->get_results("
            SELECT post_id, meta_key, meta_value
            FROM 
                $wpdb->postmeta
            WHERE 
                post_id = {$voting->get_id()} AND
                meta_key LIKE '%_wpov_counter_question_%'
        ");
        
        $total_voters = 0;
        $votings_per_question = array();

        foreach($voters as $voter) {
            //$total_voters += $voter->meta_value;
            preg_match('/_wpov_counter_question_(?<question_id>\d+)_(?<vote>.*)/', $voter->meta_key, $match);

            $question_id = intval($match['question_id']);
            $vote = $match['vote'];
            
            if(empty($votings_per_question[$question_id])) $votings_per_question[$question_id] = array(
                'approve' => 0,
                'neutral' => 0,
                'disapprove' => 0,
            );
            
            $votings_per_question[$question_id][$vote] += $voter->meta_value;
        }
        
        $total_votings_per_question = count($votings_per_question);
        
        foreach($votings_per_question as $voting_per_question) {
            $voting_per_question_sum = array_sum($voting_per_question);
            
            $total_voters += $voting_per_question_sum;
        }
        
        $total_voters = $total_voters ? round($total_voters / count($votings_per_question)) : 0;
        
        foreach($voting->parties() as $party) {
            $party_answers = $party->answers();
            $total_question_votes = 0;
            $party_voter_score = 0;
            
            foreach($party_answers as $answer) {
                if(!empty($votings_per_question[$answer->get('question')])) {
                    $total_question_votes += array_sum($votings_per_question[$answer->get('question')]);
                }
                if(!empty($votings_per_question[$answer->get('question')][$answer->value()])) {
                    $party_voter_score += $votings_per_question[$answer->get('question')][$answer->value()];
                }
                
            }
                        
            $party_scores[$party->title()] = ($party_voter_score and $total_question_votes) ? ($party_voter_score/$total_question_votes)*100 : 0; 
            
        }
        
        $total_party_scores = array_sum($party_scores);
        
        foreach($party_scores as &$party_score) {
            $party_score = $total_party_scores ? (($party_score/$total_party_scores)*100) : 0;
        }
        
        return array(
            'votings_per_question' => $votings_per_question,
            'labels' => array_keys($party_scores),
            'data' => array_values($party_scores),
            'count_voters' => $total_voters,
        );
    }    
    
    /**
     * Add options page
     */
    public function add_page() {
        $screen = get_current_screen();

        $votings = wpov_get_votings();
        
        $current_voting = (isset($votings[0]) ? $votings[0] : false);
        if(!empty($_GET['wpov-voting'])) {
            foreach($votings as $voting) {
                if($voting->get_id() == $_GET['wpov-voting']) {
                    $current_voting = $voting;
                }
            }
        }
        
        if($current_voting) {
            //$voting = wpov_get_voting($current_voting->get_id());

            $total_results = self::get_total_results($current_voting);

            $total_results_per_question = $total_results['votings_per_question'];
        }
        
        ?>
        <div class="wrap">
            <div class="dashboard-hgroup">
                <h1>
                    <?php _e('Dashboard', WPOV__PLUGIN_NAME_SLUG); ?>
                </h1>       
                <?php if(count($votings)) : ?>
                <form action="<?php echo admin_url('admin.php?page=wpov-dashboard') ?>" method="get">
                    <select name="wpov-voting">
                        <?php foreach($votings as $voting) : ?>
                        <option value="<?php echo $voting->get_id(); ?>" <?php selected($current_voting->get_id(), $voting->get_id()) ?>><?php echo $voting->title(); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="page" value="wpov-dashboard" />
                    <button class="button button-primary" type="submit"><?php _e('Select', WPOV__PLUGIN_NAME_SLUG) ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php if(count($votings)) : ?>
                <?php if($total_results['count_voters']) : ?>
                <div id="welcome-panel" class="welcome-panel">
                    <div class="welcome-panel-content">
                        <h2><?php _e('Opinion poll', WPOV__PLUGIN_NAME_SLUG); ?></h2>
                        <p class="about-description"><?php printf(__('If next Sunday really was election, %s voters would choose the following…', WPOV__PLUGIN_NAME_SLUG), $total_results['count_voters'] ); ?></p>
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
                                <th><?php _e('Question', WPOV__PLUGIN_NAME_SLUG); ?></th>
                                <th><?php _e('Poll', WPOV__PLUGIN_NAME_SLUG); ?></th>
                                <th><?php _e('Participants', WPOV__PLUGIN_NAME_SLUG); ?></th>
                            </tr>
                        </thead>
                        <?php foreach($current_voting->questions() as $i => $question) : ?>
                        <tr>
                            <td>
                                #<?php echo ($i+1); //echo $question->question_index_readable(); ?>
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
                            <td><?php echo array_sum($total_results_per_question[$question->get_id()]); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php else: ?>
                <div class="welcome-panel">
                    <h2><?php _e('Too few data recorded!', WPOV__PLUGIN_NAME_SLUG) ?></h2>
                    <p class="about-description"><?php _e('Wait a few more days until enough data is collected for a meaningful survey.', WPOV__PLUGIN_NAME_SLUG) ?></p>              
                </div>            
                <?php endif; ?>
            <?php else: ?>
            <div class="welcome-panel">
                <h2><?php _e('You must first create elections, questions and parties.', WPOV__PLUGIN_NAME_SLUG) ?></h2>
                <p class="about-description"><?php _e('The following page types are available.', WPOV__PLUGIN_NAME_SLUG); ?></p>
                <ul>
                    <?php foreach(wpov()->post_types as $post_type) : ?>
                    <li><a href="<?php echo admin_url(sprintf('edit.php?post_type=%s', $post_type->name)); ?>"><?php echo $post_type->labels->name ?></a></li>
                    <?php endforeach; ?>
                </ul>                     
            </div>            
            <?php endif; ?>
            
            
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
<?php
$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'NorseCardModule';
reason_include_once( 'minisite_templates/modules/default.php' );
include_once(DISCO_INC.'disco.php');

// define ('MAX_RESULTS', 100);

class NorseCardModule extends DefaultMinisiteModule {
    var $cleanup_rules = array(
            'username' => array('function' => 'turn_into_string'),
            'start' => array('function' => 'turn_into_string'),
            'end' => array('function' => 'turn_into_string'),
            'lost' => array('function' => 'turn_into_string'),
    );
    var $form;

    var $elements = array(
        'account' => 'radio_no_sort', 
        'start_date' => 'textdate',
        'end_date' => 'textdate');

    function init( $args = array() ) //{{{
    {
        // force_secure();
        // If the IP address isn't local and there's no user, then we get the
        // restricted off-campus view.
        // changed carleton 137.22. to luther 192.203. - burkaa
        if ($this->user_netid = reason_check_authentication()) $this->context = 'internal';
        if (isset($this->request['context']) && THIS_IS_A_DEVELOPMENT_REASON_INSTANCE) $this->context = $this->request['context'];

        // if (isset($this->request['view'])) $this->view = $this->request['view'];
        // if (in_array($_SERVER['REMOTE_ADDR'],$this->po) && ($this->view <> 'pdf')) $this->view = 'po';


        parent::init( $args );
        if($head_items =& $this->get_head_items()) {
            // add our own js and css
            // $head_items->add_javascript( '/javascripts/jquery-1.6.1.min.js');
        }

        // Allow any of the form elements to be set from the URL or POST, and look like a submission
        foreach ($_REQUEST as $key => $val) {
            if (isset($this->elements[$key])) {
                $_REQUEST['submitted'] = true;
            }
            else if (isset($this->cleanup_rules[$key])) {
                $_REQUEST['submitted'] = true;
            }
        }

        $this->form = new disco();
        $this->form->elements = $this->elements;
        $this->form->actions = array('Submit');
        $this->form->error_header_text = 'Search error';
        $this->form->add_callback(array(&$this, 'show_results'),'process');
        // $this->form->add_callback(array(&$this, 'display_form_help'),'post_show_form');
        $this->form->add_callback(array(&$this, 'run_error_checks'),'run_error_checks');
        $this->form->add_callback(array(&$this, 'on_first_time'),'on_first_time');
        $this->form->add_callback(array(&$this, 'on_every_time'),'on_every_time');
        $this->form->init();
        
        $url_parts = parse_url( get_current_url() );
        $this->search_url = $url_parts['path'];

    }//}}}

    function on_first_time(&$form){
        echo REASON_INC;
        $today = date("Y-m-d");
        $two_weeks_ago = date("Y-m-d", strtotime('-14 days'));
        $form->set_value('end_date', $today);
        $form->set_value('start_date', $two_weeks_ago);
    }

    function on_every_time(&$form){
        // $accts = $this->get_accounts($this->reason_check_authentication());

        // if (){
        //     $form->change_element_type('account', 'solid_text', array('display_name' => ''));
        // } else {
        //     $form->change_element_type('account', 'radio_button', array('options', FILL WITH THE ACCOUNTS));
        // }
    }
    
    function run(){
        if ($logged_user = reason_check_authentication()) {
            echo "<p class='norsecard_head'>";
    //      echo "Logged in as <b>" . $logged_user . "</b> ::  ";
            echo "Logged in as <b>" . $logged_user . "</b> ::  ";
            echo "<a href='/login/?logout=1'>Logout</a>";
            echo "</p>";
        
            $this->display_form();
        } else {
            reason_require_authentication();
        }
    }

    function run_error_checks(&$form){
        if ($form->get_value('start_date') > $form->get_value('end_date')){
            $form->set_error('start_date', 'Please ensure the start date is prior to the end date.');
        }
    }

    function display_form(){
        echo '<div id="norseCardForm">';
        $this->form->run();
        echo '</div>';
    }

    function show_results(&$form){
        echo $form->get_value('start_date');
        echo '<br />';
        echo $form->get_value('end_date');
        // Assemble all the data that's come in via the form or the URL into $q
        $elements = $form->get_element_names();
        foreach ($elements as $element) {
            if ($form->get_value($element))
                $q[$element] = $form->get_value($element);
        }
        foreach ($this->cleanup_rules as $name => $rule) {
            if (isset($this->request[$name]))
                $q[$name] = $this->request[$name];
        }
        $query_parts = $this->build_query($q);
        if (!$query_parts) {
            $form->set_error('start_date', 'You do not appear to be searching for anything.  Please try again.');
            return;
        }

        // Build and execute an LDAP query
        list($query, $query_desc) = $query_parts;
        $entries = $this->get_search_results($query);
        //echo "\n Start of entries: "; // - burkaa
        //pray($entries);
        //echo " End of entries \n"; // - burkaa

        // If there aren't any results, try again with similarity searching
        if (!count($entries)) {
            list($query, $query_desc) = $this->build_query($q, 'approx');
            $entries = $this->get_search_results($query);
            $this->result_comment = '<p></p><div style="color:red"><strong>Note:</strong> No exact matches were found; these are entries similar to what you searched for.</div><p></p>';
        }

        // If we have some results, call the appropriate display method
        if (count($entries) ) {
            //$this->scrub_results($entries);
            //commenting out list to use only book
            switch ($this->view) {
                case 'pdf':
                    if ($form->get_value('display_as') == 'book')
                        $this->pdf_export_photobook($entries);
                    else
                        $this->pdf_export_list($entries);
                    break;
                case 'export':
                    $this->export_tab_results($entries);
                    break;
                case 'xml':
                    $this->export_xml_results($entries);
                    break;
                default:
                    if ($form->get_value('display_as') == 'book' && count($entries) > 1)
                        $this->display_results_photobook($entries, $query_desc);
                    else
                        $this->display_results($entries, $query_desc, $telecomm);
            }
            $form->show_form = false;
        } else {
            $form->set_error('first_name', 'Your search for '.$query_desc.' did not find any matches.  Please try again.');
        }
    }

    function get_accounts($username){
        // get the accounts associated with the username
        return; 
    }

}
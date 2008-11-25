<?php

reason_include_once( 'minisite_templates/modules/form/models/default.php' );
reason_include_once( 'classes/object_cache.php' );
include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
include_once(TYR_INC.'tyr.php');
include_once( CARL_UTIL_INC . 'db/connectDB.php');

$GLOBALS[ '_form_model_class_names' ][ basename( __FILE__, '.php') ] = 'DBFormModel';


/**
 * Reason Form module DB Form Model
 *
 * 1. Answers information requests from controller and view(s)
 * 2. Handle initial transformations of a database structure into a disco form (like DiscoDB)
 * 3. Processes database saves
 * 4. E-mails values using Tyr to submitter and recipients
 * 5. Saves data to session to assist with the display of the submitted data
 *
 * By default, administrative access to a db form is available to all administrators of the site where the page appears.
 * 
 * @todo support allows_multiple, is_editable, custom_admin_form, and admin_access_group page type parameters
 * @todo support summary object
 * @todo test magic autofill support
 * @todo lots more testing
 * @todo abstract parts of this and thor model to the default model
 *
 * @version beta1 - this is not yet fully implemented, but is serving as the model for the work transfer form at carleton 
 *
 * @author Nathan White
 */
class DBFormModel extends DefaultFormModel
{
	var $_form_id; // active form id
	var $_admin_obj;
	var $_summary_obj;
	var $_is_usable;
	var $_form_view;
	var $_form_admin_view;
	
	var $_user_has_administrative_access;	
	var $_user_requested_admin = false;
	var $_form_submission_key;
	
	var $magic_transform_attributes = array('ds_firstname',
								   		 	'ds_lastname',
								   			'ds_fullname',
								   			'ds_phone',
								   			'ds_email',
								   			'ou',
								   			'title',
								   			'homephone',
								   			'telephonenumber');
								   			
	var $magic_transform_methods = array('your_full_name' => 'get_full_name',
								 		 'your_name' => 'get_full_name',
					 	   	     		 'your_first_name' => 'get_first_name',
							     		 'your_last_name' => 'get_last_name',
								 		 'your_email' => 'get_email',
								 		 'your_department' => 'get_department',
								 		 'your_title' => 'get_title',
								 		 'your_home_phone' => 'get_home_phone',
								 		 'your_work_phone' => 'get_work_phone');
								 		 
	var $submitted_data_hidden_fields_submitter_view = array('id', 'submitted_by', 'submitter_ip', 'date_created', 'date_modified', 'required_text');
	
	var $submitted_data_hidden_fields = array('id');
	
	var $show_clear_button = false;
	
	/**
	 * Make sure the model can get its connection params, otherwise return false
	 */
	function is_usable()
	{
		if (!isset($this->_is_usable))
		{
			$params = $this->get_connection_params();
			if (empty($params['db_conn']) || empty($params['table']))
			{
				trigger_error('The DB form model is not usable because it has not been provided with a valid DB connection and table.');
				$this->_is_usable = false;
			}
			else $this->_is_usable = true;
		}
		return $this->_is_usable;
	}
	
	function &get_connection_params()
	{
		if (!isset($this->_connection_params))
		{
			$params =& $this->get_params();
			$this->_connection_params['db_conn'] = (isset($params['form_model']['db_conn'])) ? $params['form_model']['db_conn'] : false;
			$this->_connection_params['table'] = (isset($params['form_model']['table'])) ? $params['form_model']['table'] : false;
		}
		return $this->_connection_params;
	}
	
	function get_db_conn()
	{
		$params =& $this->get_connection_params();
		return $params['db_conn'];
	}
	
	function get_table_name()
	{
		$params =& $this->get_connection_params();
		return $params['table'];
	}
	
	/**
	 * Process the request vars that are passed from the controller
	 */
	function handle_request_vars(&$request_vars)
	{
		if (isset($request_vars['netid']) && !empty($request_vars['netid'])) $this->set_spoofed_netid_if_allowed($request_vars['netid']);
		if (isset($request_vars['form_admin_view']) && ($request_vars['form_admin_view'] == 'true')) $this->set_user_requested_admin(true);
		if (isset($request_vars['submission_key']) && !empty($request_vars['submission_key'])) $this->set_form_submission_key($request_vars['submission_key']);
		if (isset($request_vars['form_id'])) $this->set_form_id_if_valid($request_vars['form_id']);
		else $this->set_form_id_if_valid(NULL);
	}
	
	function is_editable()
	{
		return false;
	}

	function is_admin()
	{
		return ($this->user_requested_admin() && $this->user_has_administrative_access() && $this->admin_view_is_available());
	}
		
	function is_form_complete()
	{
		return ($this->get_form_submission_key() && $this->form_submission_is_complete() && !$this->is_admin());
	}
		
	function is_summary()
	{
		return ($this->form_allows_multiple() && $this->user_has_submitted() && !$this->get_form_id() && ($this->get_form_id() !== 0));
	}
		
	function is_form()
	{
		return ($this->user_has_access_to_fill_out_form() && !$this->is_admin() && !$this->is_form_complete() && !$this->is_summary());
	}
		
	function is_unauthorized()
	{
		return (!$this->user_has_access_to_fill_out_form());
	}
	
	function get_email_of_recipient()
	{
		return false;
	}
	
	function get_email_of_submitter()
	{
		return $this->get_user_netid();
	}
	
	function get_form_name()
	{
		return '';
	}
	
	/**
	 * THIS SHOULD STUFF NEEDS THINKING ABOUT...
	 */
	function should_email_form_data()
	{
		return false;
	}
	
	function should_email_form_data_to_submitter()
	{
		return false;
	}
	
	function should_save_form_data()
	{
		return true;
	}

	function should_save_submitted_data_to_session()
	{
		return true;
	}
	
	function should_email_link()
	{
		return false;
	}

	function should_email_empty_fields()
	{
		return false;
	}
	
	function should_show_submitted_data()
	{
		return true;
	}

	function should_display_return_link()
	{
		return true;
	}
	
	function should_show_login_logout()
	{
		return true;
	}
	
	function should_show_submission_list_link()
	{
		return false;
	}
	
	function should_show_thank_you_message()
	{
		return true;
	}
	
	function set_form_id_if_valid($form_id)
	{
		if ($this->form_id_is_valid($form_id)) $this->set_form_id($form_id);
	}
	
	function set_spoofed_netid_if_allowed($requested_netid = false)
	{
		$netid = reason_check_authentication();
		if (!empty($requested_netid) && !empty($netid) && ($requested_netid != $netid))
		{
			$user_id = get_user_id($netid);
			if (reason_user_has_privs($user_id, 'pose_as_other_user'))
			{
				$this->set_user_netid($requested_netid);
			}
		}
	}
	

	/**
	 * Does the following:
	 *
	 * If a form_id has been provided, return true if the user has access to it.
	 *
	 * @todo how are forms that do not require login handled?
	 */
	function form_id_is_valid($form_id)
	{
		$user_netid = $this->get_user_netid();
		if ($form_id && $user_netid) // only attempt retrieval if user is logged in!
		{
			$qry = $this->get_select_by_key_sql($form_id, 'id');
			$result = $this->perform_query($qry);
			return true;
		}
		elseif ($form_id && !$user_netid && $this->is_editable()) reason_require_authentication();
		elseif ($form_id == "0") // a form_id of 0 is valid if the user is allowed to create new entries
		{
			if ($this->form_allows_multiple()) return true;
		}
		return false;
		// consider redirect cases
		$user_netid = $this->get_user_netid();
		$user_submissions = (!empty($user_netid)) ? $this->get_values_for_user($user_netid) : false;
		
		// redirect case 1 - user logged in, editable form, multiples not allowed, valid row exists
		if ($this->is_editable() && !$this->form_allows_multiple() && !empty($user_submissions))
		{
			$redirect_form_id = max(array_keys($user_submissions)); // highest id in the user submissions array
		}
		elseif ($form_id) // we have a form id but it was invalid
		{
			$redirect_form_id = '';
		}
		if (isset($redirect_form_id))
		{
			$redirect = carl_make_redirect(array('form_id' => $redirect_form_id));
			header("Location: " . $redirect);
			exit;
		}
	}
	
	/**
	 * If the form submission was just completed - we should have a valid submission_key passed in the request
	 */
	function form_submission_is_complete()
	{
		if (!isset($this->_form_submission_is_complete))
		{
			$submission_key = $this->get_form_submission_key();
			$sk_cache = new ReasonObjectCache($submission_key);
			$this->_form_submission_is_complete = ($sk_cache->fetch());
			if ($sk_cache->fetch()) $sk_cache->clear();
		}
		return $this->_form_submission_is_complete;
	}
	
	/**
	 * Create and return a submission key generated from the current submission
	 * @param object disco_obj
	 */
	function create_form_submission_key()
	{
		$values = $this->get_values_for_save();
		$str = get_mysql_datetime();
		foreach ($values as $val) $str = $str . $val;
		$key = md5($str);
		$sk = new ReasonObjectCache($key, '60'); // only last for a minute
		$sk->set(true);
		return $key;
	}
	
	function form_allows_multiple()
	{
		return false;
	}
	
	function form_requires_authentication()
	{
		return true;
	}
	
	/**
	 * @return boolean whether or not there is a submission saved in the database for the user
	 */
	function user_has_submitted()
	{
		$rows =& $this->get_values_for_user();
		return ($rows) ? (count($rows) > 0) : false; // count(false) returns 1, so we check to see if rows is false first
	}
	
	/**
	 * Return editable or not_editable is magic string autofill is enabled, otherwise return false
	 */
	function get_magic_string_autofill()
	{
		return false;
	}
	
	function &get_top_links()
	{
		if ($this->admin_view_is_available())
		{
			if (!$this->user_requested_admin() && $this->user_has_administrative_access())
			{
				$link['Enter administrative view'] = carl_construct_link(array('form_admin_view' => 'true'), array('textonly', 'netid'));
			}
			elseif ($this->user_requested_admin() && $this->user_has_administrative_access())
			{
				$link['Exit administrative view'] = carl_construct_link(array('form_admin_view' => ''), array('textonly', 'netid'));
				
				// add summary view link if we have an action id selected
				$admin_obj =& $this->get_admin_object();
				$action_id = $admin_obj->get_table_action_id();
				if ($action_id) $link['Show summary view'] = carl_construct_link(array('form_admin_view' => 'true'), array('textonly', 'netid'));
			}	
		}
		else $link = array();
		return $link;
	}
	
	function admin_view_is_available()
	{
		return true;	
	}
	
	/**
	 * Get all the values from the form that correspond to values in the database
	 */
	function &get_values_for_save()
	{
		$disco_obj =& $this->get_view();
		$values = array_merge($disco_obj->get_values(), $this->get_values_for_save_extra_fields());
		$columns =& $this->get_database_columns();
		foreach (array_keys($columns) as $col_name)
		{
			if (isset($values[$col_name])) $valid_values[$col_name] = $values[$col_name];
		}
		return $valid_values;
	}
	
	function &get_values_for_email()
	{
		$disco_obj =& $this->get_view();
		$values = $disco_obj->get_values();
		$fields_to_hide =& $this->get_submitted_data_hidden_fields($disco_obj);
		if (!empty($fields_to_hide)) $this->_hide_fields($values, $fields_to_hide);
		return $values;
	}
	
	function &get_values_for_email_submitter_view()
	{
		$values =& $this->get_values_for_submitter_view();
		return $values;
	}
	
	function &get_values_for_submitter_view()
	{
		if (!isset($this->_values_for_submitter_view))
		{
			$disco_obj =& $this->get_view();
			$values = $disco_obj->get_values();
			$disco_hidden_fields =& $this->_get_disco_hidden_fields($disco_obj);
			$fields_to_hide =& $this->get_submitted_data_hidden_fields_submitter_view($disco_obj);
			if (!empty($fields_to_hide)) $this->_hide_fields($values, $fields_to_hide);
			if (!empty($disco_hidden_fields)) $this->_hide_fields($values, $disco_hidden_fields);
			
			$this->_values_for_submitter_view = $values;
		}
		return $this->_values_for_submitter_view;
	}
	
	function &get_values_for_show_submitted_data()
	{
		if (!isset($this->_values_for_show_submitted_data))
		{
			$session =& get_reason_session();
			if (!$session->has_started()) $session->start();
			$values = $session->get('form_confirm');
			$session->set('form_confirm', '');
			$this->_values_for_show_submitted_data = (!empty($values)) ? $values : '';
		}
		return $this->_values_for_show_submitted_data;
	}
	
	function get_values_for_save_extra_fields()
	{
		$submitted_by = $this->get_user_netid();
		$submitter_ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
		return array('submitted_by' => $submitted_by, 'submitter_ip' => $submitter_ip);
	}
	
	function get_values_for_email_extra_fields()
	{
		$submitted_by = $this->get_user_netid();
		$submitter_ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
		return array('submitted_by' => $submitted_by, 'submitter_ip' => $submitter_ip, 'submission_time' => get_mysql_datetime());
	}

	function &_get_values_and_extra_email_fields()
	{
		$disco_obj =& $this->get_view();
		$values = array_merge($disco_obj->get_values(), $this->get_values_for_email_extra_fields());
		return $values;
	}
	
	function &_get_disco_hidden_fields()
	{
		$disco_obj =& $this->get_view();
		if (!isset($this->_disco_hidden_fields))
		{
			$elements = $disco_obj->get_element_names();
			foreach ($elements as $elm)
			{
				$type = $disco_obj->get_element_property($elm, 'type');
				if ($type == 'hidden') $hidden[] = $elm;
			}
			$this->_disco_hidden_fields = (isset($hidden)) ? $hidden : array();
		}
		return $this->_disco_hidden_fields;
	}

	/**
	 * The recipients have administrative access to the data. We provide a link if the form is saved to a database.
	 */
	function get_link_for_email()
	{
		if ($this->should_email_link() && $this->should_save_form_data())
		{
			$link = carl_construct_link(array('form_admin_view' => 'true', 'table_row_action' => 'view', 'table_action_id' => $this->get_form_id()));
			return $link;
		}
		else return '';
	}
	
	function get_redirect_url()
	{
		$form_id = ($this->is_editable() && $this->get_user_netid()) ? $this->get_form_id() : '';
		$redirect = carl_make_redirect(array('submission_key' => $this->create_form_submission_key(), 'form_id' => $form_id));
		return $redirect;
	}
	
	/**
	 * We can only provide a link if the form saves to a database and is editable by the submitter
	 */
	function get_link_for_email_submitter_view()
	{
		if ($this->should_email_link() && $this->should_save_form_data() && $this->is_editable())
		{
			$link = carl_construct_link(array('form_id' => $this->get_form_id()));
			return $link;
		}
		else return '';
	}
	
	function _hide_fields(&$values, $fields_to_hide)
	{
		foreach ($fields_to_hide as $key)
		{
			if (array_key_exists($key, $values))
			{
				unset ($values[$key]);
			}
		}
	}

	/**
	 * @todo the db.php model should probably support custom table admin forms, but maybe not - this is pretty easy.
	 */
	function init_admin()
	{
		$head_items =& $this->get_head_items();
		$head_items->add_stylesheet(REASON_HTTP_BASE_PATH.'css/forms/form_data.css');
		$head_items->add_stylesheet(REASON_HTTP_BASE_PATH.'css/hide_nav.css');
		$admin_obj =& $this->get_admin_object();
		$admin_view = $this->get_admin_view();
		$admin_obj->set_admin_form($admin_view);
		$admin_obj->set_privileges_from_admin_form();
		$admin_obj->init($this->get_db_conn(), $this->get_table_name());
	}
	
	function &get_admin_object()
	{
		if (!isset($this->_admin_obj))
		{
			include_once( CARL_UTIL_INC . 'db/table_admin.php');
			$this->_admin_obj = new TableAdmin();
		}
		return $this->_admin_obj;
	}

	function init_summary_object()
	{
		$summary_obj =& $this->get_summary_object();
		$summary_obj->init_from_array($this->get_values_for_user());
	}
	
	function &get_summary_object()
	{
		if (!isset($this->_summary_obj))
		{
			include_once( CARL_UTIL_INC . 'db/table_admin.php');
			// we want to entity convert all the fields that occur naturally 
			$this->_summary_obj = new TableAdmin();
			$this->_summary_obj->init_from_array($this->get_values_for_user());
			//$this->_summary_obj->set_fields_to_entity_convert(array_keys($display_values));
		 	$this->set_sort_field($this->_summary_obj->get_sort_field());
		 	$this->set_sort_order($this->_summary_obj->get_sort_order());
		}
		return $this->_summary_obj;
	}
	
	function &get_values_for_user()
	{
		if (!isset($this->_values_for_user))
		{
			$netid = $this->get_user_netid();
			if ($netid)
			{
				$qry = $this->get_select_by_key_sql($netid, 'submitted_by');
				$result = $this->perform_query($qry);
				if ($result)
				{
					while ($row = mysql_fetch_assoc($result))
					{
						$this->_values_for_user[] = $row;
					}
				}
			}
			if (!isset($this->_values_for_user)) $this->_values_for_user = false;
		}
		return $this->_values_for_user;
	}
	
	function &get_values_for_primary_key()
	{
		if (!isset($this->_values_for_user))
		{
			$netid = $this->get_user_netid();
			if ($netid)
			{
				$qry = $this->get_select_by_key_sql($netid, 'submitted_by');
				$result = $this->perform_query($qry);
				if ($result)
				{
					while ($row = mysql_fetch_assoc($result))
					{
						$this->_values_for_user[] = $row;
					}
				}
			}
			if (!isset($this->_values_for_user)) $this->_values_for_user = false;
		}
		return $this->_values_for_user;
	}
	
	/**
	 * @todo implement me
	 */
	function &get_sorted_values_for_user($sort_field, $sort_order)
	{
		if (!isset($this->_sorted_values_for_user[$sort_field][$sort_order]))
		{
			return false;
		}
		return $this->_sorted_values_for_user[$sort_field][$sort_order];
	}
	
	/**
	 * Check the label and normalized label
	 */
	function get_disco_field_name($label)
	{
		if (!isset($this->_disco_field_name[$label]))
		{
			$view =& $this->get_view();
			$elements = $view->get_element_names();
			$normalized_label = strtolower(str_replace(" ", "_", $label));
			foreach ($elements as $element)
			{
				
				$display_name = $view->get_element_property($element, 'display_name');
				$this->_disco_field_name[$label] = ( ($display_name == $label) || 
													 (strtolower(str_replace(" ", "_", $display_name)) == $normalized_label) )
											     ? $element : false;
				if ($this->_disco_field_name[$label] != false) break;
			}
		}
		return $this->_disco_field_name[$label];
	}
	
	function set_sort_field($field)
	{
		$this->_sort_field = $field;
	}
	
	function set_sort_order($order)
	{
		$this->_sort_order = $order;
	}
	
	function get_sort_field()
	{
		return (isset($this->_sort_field)) ? $this->_sort_field : '';
	}
	
	function get_sort_order()
	{
		return (isset($this->_sort_order)) ? $this->_sort_order : '';
	}
	
	/**
	 * Uses Tyr to send an e-mail, given an array of key value pairs where the key is the item display name, and the value is the value
	 *
	 * The options array can define any of the following keys - if none are defined default behaviors will be used
	 * 
	 * - to: netid, array of netids, or comma separated string indicating who the e-mails should go to
	 * - from: netid or full e-mail address for the from fields
	 * - subject: string indicating the subject line
	 * - header: string containing header line for the first line of the e-mail
	 * - dislaimer: boolean indicating whether or not to add a dislaimer - defaults to true
	 * - origin_link: link indicating where the URL where the form was filled out
	 * - access_link: link indicating where the form can be accessed for view/edit
	 *
	 * @param array data - key/value pairs of the data to e-mail
	 * @param array options - allows various parameters to be optionally passed
	 * @todo remove this weird mini system and the tyr "message" framework and use e-mail class directly
	 */
	function send_email(&$data, $options = array())
	{
		$to = (isset($options['to'])) ? $options['to'] : $this->get_email_of_recipient();
		$to = (is_array($to)) ? implode(",", $to) : $to;
		if (strlen(trim($to)) > 0)
		{
			if (!$this->should_email_empty_fields()) $messages['all']['hide_empty_values'] = true;
			if (isset($options['origin_link'])) $messages['all']['form_origin_link'] = $options['origin_link'];
			if (isset($options['access_link'])) $messages['all']['form_access_link'] = $options['access_link'];
			$messages['all']['form_title'] = (isset($options['header'])) ? $options['header'] : $this->get_form_name();
			$messages[0]['to'] = $to;
			$messages[0]['subject'] = (isset($options['subject'])) ? $options['subject'] : 'Response to Form: ' . $this->get_form_name();	
			$tyr = new Tyr($messages, $data);
			$tyr->add_disclaimer = (isset($options['disclaimer']) && ($options['disclaimer'] == false) ) ? false : true;
			$tyr->run();
		}
	}
	
	// do we need an options array? 
	/**
	 * Saves the data in the appropriate way. Assumes should_save_form_data has been checked.
	 *
	 * Considerations include:
	 *
	 * - Is this form one that allows multiple submissions?
	 * - If so, is this an edit or a new submission?
	 * - Is this an edit of an existing submission
	 */
	function save_data(&$data, $options = array())
	{
		$form_id = $this->get_form_id();
		
		if ( $form_id && $this->is_editable() ) // update
		{
			$result = $this->perform_update($form_id, $data);
		}
		else // insert
		{
			$columns = $this->get_database_columns();
			if (isset($columns['date_created']) && !isset($data['date_created'])) $data['date_created'] = get_mysql_datetime();
			$result = $this->perform_insert($data);
			if ($result) $this->set_form_id($result);
		}
	}
	
	function save_submitted_data_to_session()
	{
		$values =& $this->get_values_for_submitter_view();
		$session =& get_reason_session();
		if (!$session->has_started()) $session->start();
		$session->set('form_confirm', $values);
	}
	
	function user_requested_admin()
	{
		return $this->_user_requested_admin;
	}
	
	/**
	 * @param object disco form
	 */
	function transform_form()
	{
		$disco =& $this->get_view();
		if (!$this->get_show_clear_button())
		{
			if (isset($disco->actions['reset'])) unset ($disco->actions['reset']);
		}
		$this->_setup_elements($disco);
		$editable = true;
		$methods =& $this->get_magic_transform_methods($disco);
		$attribute_values =& $this->get_magic_transform_values($disco);
		if (!empty($methods) && !empty($attribute_values))
		{
			foreach ($methods as $k => $v)
			{
				if (!empty($v)) // a method that maps to an empty string will not be run (allows one to turn off magic transform for a field)
				{
					if (method_exists($disco, $v)) $transform_array[$k] = $disco->$v();
					elseif (method_exists($this, $v)) $transform_array[$k] = $this->$v();
					else trigger_error('The magic transform method ' . $v . ' was not defined in the thor model or view - will not transform the field ' . $k);
				}
			}
		}
		if (!empty($transform_array)) $this->apply_magic_transform_to_form($disco, $transform_array, $editable); // zap this!
		return true;
	}
	
	function _setup_elements(&$disco)
	{
		$column_types =& $this->get_database_columns();
		foreach ($column_types as $name=>$db_type)
		{
			$result = $disco->plasmature_type_from_db_type( $name, $db_type, array('find_maxlength_of_text_field' => true, 'do_not_sort_enums' => true));
			$plas_type = (reset($result)) ? reset($result) : 'text';
			$args = (next($result));
			if(($plas_type == 'textDate' || $plas_type == 'textDateTime')) $args['prepopulate'] = true;
			$disco->add_element( $name, $plas_type, $args );
		}
	}
	
	function apply_magic_transform_to_form(&$disco_obj, $transform_array, $editable = true)
	{
		$display_values = $disco_obj->get_element_names();
		foreach ($display_values as $key => $label)
		{
			if (isset($transform_array[$label]))
			{
				$value = (isset($transform_array[$label])) ? $transform_array[$label] : $transform_array[$normalized_label];
				$disco_obj->set_value($key, $value);
				if (!$editable) $disco_obj->change_element_type($key, 'solidtext');
			}
		}
	}
	
	/**
	 * If the admin form provides an authenticate method, that method is used. Otherwise, admin access
	 * privileges are determined by checking (in order) if any of the following are true:
	 *
	 * 1. The person has access to administer the site (and has editing privileges)
	 * 2. The person is in the group that can see all the form results
	 * 3. The person is in the list of usernames that receive form submissions
	 *
	 * @return boolean
	 */		
	function user_has_administrative_access()
	{
		if (!isset($this->_user_has_administrative_access))
		{
			$netid = $this->get_user_netid();
			$user_id = ($netid) ? get_user_id($netid) : false;
			if ( ($user_id) && reason_username_has_access_to_site($netid, $this->get_site_id()) && reason_user_has_privs($user_id, 'edit')) $access = true;
			else $access = false;
			$this->_user_has_administrative_access = $access;
		}
		return $this->_user_has_administrative_access;
	}
	
	/**
	 * This returns true in all cases except for the case where there is an admin access group and it does not require login
	 */
	function admin_requires_login()
	{
		return true;
	}
	
	function user_has_access_to_fill_out_form()
	{
		return true;
	}
	
	/**
	 * Gets the user_netid - sets it to the logged in users netid if it has not been set
	 */
	function get_user_netid()
	{
		if (!isset($this->_user_netid))
		{
			$this->set_user_netid(reason_check_authentication());
		}
		return $this->_user_netid;
	}
	
	/**
	 * Sets the working user_netid
	 */
	function set_user_netid($netid = false)
	{
		$this->_user_netid = $netid;
	}
	
	function &get_directory_info($attributes = false)
	{
		if (!isset($this->_directory_info))
		{
			$netid = $this->get_user_netid();	
	    	$dir = new directory_service();
			if ($attributes) $dir->search_by_attribute('ds_username', $netid, $attributes);
			else $dir->search_by_attribute('ds_username', $netid);
			$this->_directory_info = $dir->get_first_record();
		}
		return $this->_directory_info;
	}
		
	/**
	 * @access private
	 */
	function _user_receives_email_results()
	{
		return false;
	}
	
	function set_user_requested_admin($boolean)
	{
		if ($boolean == true)
		{
			if ($this->admin_requires_login()) reason_require_authentication();
		}
		$this->_user_requested_admin = $boolean;
	}
	
	// magic string autofill methods
	function get_first_name()
	{
		return ($this->_get_simple_field_from_key('edupersonnickname')) 
			   ? $this->_get_simple_field_from_key('edupersonnickname') 
			   : $this->_get_simple_field_from_key('ds_firstname');
	}

	function get_full_name()
	{
		return $this->_get_simple_field_from_key('ds_fullname');
	}
		
	function get_last_name()
	{
		return $this->_get_simple_field_from_key('ds_lastname');
	}
	
	function get_email()
	{
		return $this->_get_simple_field_from_key('ds_email');
	}
	
	function get_department()
	{
		return $this->_get_multiple_fields_from_key('ou');
		
	}
	
	function get_title()
	{
		return $this->_get_multiple_fields_from_key('title');
	}
	
	function get_home_phone()
	{
		return $this->_get_clean_phone_number('homephone');
	}
	
	function get_work_phone()
	{
		return $this->_get_clean_phone_number('telephonenumber');
	}
	
	function _get_clean_phone_number($key)
	{
		$dir_info =& $this->get_magic_transform_values();
		if (!empty($dir_info[$key]))
		{
			$clean_phone = (is_array($dir_info[$key])) ? $dir_info[$key][0] : $dir_info[$key];
			$clean_phone = (substr($clean_phone, 0, 3) == '+1 ') ? str_replace(' ', '-', substr($clean_phone, 3)) : $clean_phone;
			return $clean_phone;
		}
	}
	
	function _get_simple_field_from_key($key)
	{
		$dir_info =& $this->get_magic_transform_values();
		return (!empty($dir_info[$key])) ? $dir_info[$key][0] : '';
	}
	
	function _get_multiple_fields_from_key($key)
	{
		$dir_info =& $this->get_magic_transform_values();
		if (!empty($dir_info[$key]))
		{
			if (count($dir_info[$key]) > 1)
			{
				$str = '';
				foreach ($dir_info[$key] as $k=>$v)
				{
					$str .= $v . '; ';
				}
				return substr($str, 0, -2);
			}
			else
			{
				return $dir_info[$key][0];
			}
		}
	}

	function &get_submitted_data_hidden_fields($disco_obj = NULL)
	{
		if (!isset($this->_submitted_data_hidden_fields))
		{
			if ($disco_obj != NULL)
			{
				$this->_submitted_data_hidden_fields = ($disco_obj->get_submitted_data_hidden_fields()) 
													 ? $disco_obj->get_submitted_data_hidden_fields() 
													 : $this->submitted_data_hidden_fields;
			}
			else $this->_submitted_data_hidden_fields = $this->submitted_data_hidden_fields;
		}
		return $this->_submitted_data_hidden_fields;
	}
	
	function &get_submitted_data_hidden_fields_submitter_view($disco_obj = NULL)
	{
		if (!isset($this->_submitted_data_hidden_fields_submitter_view))
		{
			if ($disco_obj != NULL)
			{
				$this->_submitted_data_hidden_fields_submitter_view = ($disco_obj->get_submitted_data_hidden_fields_submitter_view()) 
													 ? $disco_obj->get_submitted_data_hidden_fields_submitter_view() 
													 : $this->submitted_data_hidden_fields_submitter_view;
			}
			else $this->_submitted_data_hidden_fields_submitter_view = $this->submitted_data_hidden_fields_submitter_view;
		}
		return $this->_submitted_data_hidden_fields_submitter_view;
	}

 	/**
 	 * @return array magic_transform_methods from model (and possibly view)
 	 * @todo change paramater to &$disco_obj=NULL when php 4 is no longer supported
 	 */
 	function &get_magic_transform_methods($disco_obj = NULL)
 	{
 		if (!isset($this->_magic_transform_methods)) // if internal build hasn't been done then do it - merge methods from view
 		{
 			if ($disco_obj != NULL)
 			{
 				$magic_transform_methods = ($disco_obj->get_magic_transform_methods()) 
 										 ? $disco_obj->get_magic_transform_methods() 
 										 : $this->magic_transform_methods;
 				$this->_magic_transform_methods = ($disco_obj->get_custom_magic_transform_methods()) 
 											    ? array_merge($magic_transform_methods, $disco_obj->get_custom_magic_transform_methods())
 											    : $magic_transform_methods;
 			}
 			else $this->_magic_transform_methods = $this->magic_transform_methods;
 		}
 		return $this->_magic_transform_methods;
 	}
 	
 	/**
 	 * @return array magic_transform_attributes from model (and possibly view)
 	 * @todo change paramater to &$disco_obj=NULL when php 4 is no longer supported
 	 */
	function &get_magic_transform_attributes($disco_obj = NULL)
	{
		if (!isset($this->_magic_transform_attributes)) // if internal build hasn't been done then do it - merge methods from view
 		{
 			if ($disco_obj != NULL)
 			{
 				$magic_transform_attributes = ($disco_obj->get_magic_transform_attributes()) 
 											? $disco_obj->get_magic_transform_attributes() 
 											: $this->magic_transform_attributes;
 				$this->_magic_transform_attributes = ($disco_obj->get_custom_magic_transform_attributes()) 
 												   ? array_merge($magic_transform_attributes, $disco_obj->get_custom_magic_transform_attributes())
 												   : $magic_transform_attributes;
 			}
 			else $this->_magic_transform_attributes = $this->magic_transform_attributes;
 		}
 		return $this->_magic_transform_attributes;
	}

 	/**
 	 * @return array magic_transform_values
 	 * @todo change paramater to &$disco_obj=NULL when php 4 is no longer supported
 	 */	
	function &get_magic_transform_values($disco_obj = NULL)
	{
		if (!isset($this->_magic_transform_values)) // build internally if necessary
 		{
 			$attributes =& $this->get_magic_transform_attributes($disco_obj);
 			$this->_magic_transform_values =& $this->get_directory_info($attributes);
 		}
 		return $this->_magic_transform_values;
	}
	
	function get_show_clear_button($disco_obj = NULL)
	{
		if (!isset($this->_show_clear_button))
		{
			if ($disco_obj != NULL) $this->_show_clear_button = $disco_obj->get_show_clear_button();
			else $this->_show_clear_button = $this->show_clear_button;
		}
		return $this->_show_clear_button;
	}
	
	// SQL METHODS - These should really be abstracted out of this
	/**
	 * Maybe limit to one???
	 */	
	function get_select_by_key_sql($key, $key_column, $sort_field = '', $sort_order = '')
	{
		$str = 'SELECT * FROM '.$this->get_thor_table().' WHERE '.$key_column.' = "'.$key.'"';
		if (!empty($sort_field) && !empty($sort_order))
		{
			$str .= ' ORDER BY "' . $sort_field . '" ' . $sort_order; 
		}
		return $str;
	}
	
	function &get_database_column_names()
	{
		$columns =& $this->get_database_columns();
		$names = array_keys($columns);
		return $names;
	}
	
	/**
	 * @return array column_name=>column_type
	 * @todo this is old code pulled form discodb and should be reviewed for efficiency
	 */
	function &get_database_columns()
	{
		if (!isset($this->_database_columns))
		{
			$table = $this->get_table_name();
			connectDB($this->get_db_conn());
			$types = mysql_query( "show fields from $table" ) OR $this->_internal_error( 'Could not retrieve types from DB' );
			$fields = mysql_list_fields( $this->get_db_conn(), $table ) OR $this->_internal_error( 'Could not retrieve fields from DB: '.mysql_error() );
			$columns = mysql_num_fields( $fields );
			connectDB(REASON_DB);
			
			for ($i = 0; $i < $columns; $i++)
			{
				$f = mysql_field_name($fields, $i) OR $this->_internal_error( 'um ... something is wrong. you should check me. and someone should write a more descriptive error message.' );
				$db_type = mysql_result($types, $i,'Type' )  OR $this->_internal_error( 'um ... something is wrong. you should check me. and someone should write a more descriptive error message.' );
				$this->_database_columns[$f] = $db_type;
			}
		}
		return $this->_database_columns;
	}
	
	/**
	 * Wrapper for generic query to makes sure we connect, disconnect from the database as appropriate
	 */
	function perform_query($qry)
	{
		$restore_conn = ($this->get_db_conn() != get_current_db_connection_name()) ? get_current_db_connection_name() : false;
		if ($restore_conn) connectDB($this->get_db_conn());
		$result = db_query($qry);
		if ($restore_conn) connectDB($restore_conn);
		return $result;
	}
	
	function perform_insert($values)
	{
		$restore_conn = ($this->get_db_conn() != get_current_db_connection_name()) ? get_current_db_connection_name() : false;
		if ($restore_conn) connectDB($this->get_db_conn());
		$result = $GLOBALS['sqler']->insert( $this->get_table_name(), $values );
		if ($restore_conn) connectDB($restore_conn);
		return ($result) ? mysql_insert_id() : false;
	}
	
	function perform_update($id, $values)
	{
		$restore_conn = ($this->get_db_conn() != get_current_db_connection_name()) ? get_current_db_connection_name() : false;
		if ($restore_conn) connectDB($this->get_db_conn());
		$result = $GLOBALS['sqler']->update( $this->get_table_name(), $values, $id );
		if ($restore_conn) connectDB($restore_conn);
		return $result;
	}
}
?>

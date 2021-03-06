<?php
/**
 * Import Data into ExpressionEngine
 *
 * ### EE 1.6 version ###
 *
 *
 * FIELD TYPES
 *  - Generates correct data structure for field types POST data
 *
 * Supported Types:
 * text, textarea, select, post_data_select, date, rel, ngen_file_field, playa, ff_checkbox, wygwam
 *
 *
 * Created by Front
 * Useful, memorable and satisfying things for the web.
 * We create amazing online experiences that delight users and help our clients grow.
 *
 * Support
 * Please use the issues page on GitHub: http://github.com/designbyfront/Import-Data-into-ExpressionEngine/issues
 * or email us: info@designbyfront.com
 *
 * License and Attribution
 * This work is licensed under the Creative Commons Attribution-Share Alike 3.0 Unported.
 * As this program is licensed free of charge, there is no warranty for the program, to the extent permitted by applicable law.
 * For more details, please see: http://github.com/designbyfront/Import-Data-into-ExpressionEngine/#readme
 *
 *
 * @package DesignByFront
 * @author  Alistair Brown
 * @author  Shann McNicholl
 * @link    http://github.com/designbyfront/Import-Data-into-ExpressionEngine
 * 
 */

class Field_type {

	private $order;
	private $entry_number;
	private $column_index;
	private $site_id;
	private $weblog_id;
	private $field;
	private $value;
	private $existing;
	private $added_ids;
	private $relationships;
	private $addition_override;

// --- SETTINGS ---------------

	// Fields which support input from a multi-select
	public static $multi_field_types = array(
		'playa'
	);

	// Fields which support input from a multi-select
	public static $delimiter_field_types = array(
		'playa'
	);

	// Fields which can be used as unique
	public static $unique_field_types = array(
		'text',
		'textarea',
		'select',
		'date'
	);

	// Fields which support addition behaviour
	public static $addition_field_types = array(
		'playa'
	);


	function Field_type ()
	{
		$this->__construct();
	}


	/*
	 * @params order             - int corresponding to the number of previous fields executed [Incremental] (not including EE specific fields)
	 * @params row_number        - int corresponding to the line of the input file currently being processed
	 * @params column_index      - int corresponding to index for the column of current row in the input file
	 *                             -or- array of ints if type in 'multi_field_types' array
	 * @params site_id           - int specifying ExpressionEngine site
	 * @params weblog_id         - int specifying ExpressionEngine weblog
	 * @params field             - associative array containing details about the field
	 * @params value             - string value being put into the field
	 *                             -or- array of strings if type in 'multi_field_types' array
	 *                             -or- array of array of strings if type in 'delimiter_field_types' array
	 * @params existing          - ID of existing full entry if found in database (empty if not found)
	 * @params added_ids         - array of ints which correspond to the entry_id's of recently entered entries (or empty if first entry published)
	 * @params relationships     - associative array  of user defined data relationships [{column_index} => {some_weblog_id}#{some_field_id}] (or empty if not defined in stage 2)
	 * @params addition_override - boolean whether to override default behaviour of overwriting and add to field [only applicable for field types in addition_field_types] (defined in stage 3)
	 */
	function __construct ($order, $row_number, $column_index, $site_id, $weblog_id, $field, $value, $existing, $added_ids, $relationships, $addition_override)
	{
		//echo 'Input:<pre>'.print_r(array($order, $site_id, $weblog_id, $field, $value, $existing, $added_ids, $relationships), true).'</pre>';

		$this->order             = $order;
		$this->row_number        = $row_number;
		$this->column_index      = $column_index;
		$this->site_id           = $site_id;
		$this->weblog_id         = $weblog_id;
		$this->field             = $field;
		$this->value             = $value;
		$this->existing          = $existing;
		$this->added_ids         = $added_ids;
		$this->relationships     = $relationships;
		$this->addition_override = $addition_override;
	}

	/*
	 * @return associative array containing:
	 *           - mapping from 'post' to formatted array for post data
	 *           - mapping from 'notification' to string or array of strings
	 *         array can be empty
	 */
	public function post_value ()
	{
		if (method_exists($this, 'post_data_'.$this->field['field_type']))
			return $this->{'post_data_'.$this->field['field_type']}();
		return FALSE;
	}


// ---- Supported Field Type Functions ------

	private function post_data_text ()
	{
		// If value is empty, default to existing if possible
		if ($this->value === NULL || $this->value === '')
			if (isset($this->existing['field_id_'.$this->field['field_id']]))
				$this->value = $this->existing['field_id_'.$this->field['field_id']];
			else
				$this->value = '';
		// Return formatted data
		return array('post' => array('field_id_'.$this->field['field_id'] => $this->value, 'field_ft_'.$this->field['field_id'] => $this->field['field_fmt']));
	}



	private function post_data_textarea ()
	{
		// Format same as text
		return $this->post_data_text();
	}



	private function post_data_select ()
	{
		// Format same as text
		return $this->post_data_text();
	}



	private function post_data_date ()
	{
		// If value is empty, default to existing if possible
		if ($this->value === NULL || $this->value === '')
			if (isset($this->existing['field_id_'.$this->field['field_id']]))
				$this->value = $this->existing['field_id_'.$this->field['field_id']];
			else
				$this->value = '';
		// Return formatted data, interpretting value as date
		return array('post' => array('field_id_'.$this->field['field_id'] => date("Y-m-d H:i A", strtotime($this->value))));
	}



	private function post_data_rel ()
	{
		global $DB, $LANG;

		// If value is empty, default to existing if possible
		if ($this->value === NULL || $this->value === '')
			return array('post' => array('field_id_'.$this->field['field_id'] => (isset($this->existing['field_id_'.$this->field['field_id']]) ? $this->existing['field_id_'.$this->field['field_id']] : '')));

		// If no relationships set, return empty
		if (!isset($this->relationships[$this->column_index]))
			return array();

		$pieces = explode('#', $this->relationships[$this->column_index]);
		// If no second value, match with title
		if (empty($pieces[1])) {
			$query = 'SELECT entry_id
			          FROM exp_weblog_titles
			          WHERE site_id = '.$DB->escape_str($this->site_id).'
			            AND weblog_id = '.$DB->escape_str($pieces[0]).'
			            AND title = \''.$DB->escape_str($this->value).'\'
			          LIMIT 1';
		// If second value, match with field
		} else {
			$query = 'SELECT entry_id
			          FROM exp_weblog_data
			          WHERE site_id = '.$DB->escape_str($this->site_id).'
			            AND weblog_id = '.$DB->escape_str($pieces[0]).'
			            AND field_id_'.$DB->escape_str($pieces[1]).' = \''.$DB->escape_str($this->value).'\'
			          LIMIT 1';
		}

		$query = $DB->query($query);
		$existing_entry = $query->result;
		if (empty($existing_entry))
			return array('notification' => $this->format_notification($LANG->line('import_data_stage4_notification_rel_1').(empty($pieces[1]) ? 'title' : 'field_id_'.$pieces[1]).$LANG->line('import_data_stage4_notification_equals_quote').$this->value.$LANG->line('import_data_stage4_notification_rel_2')));
		$existing_entry = $existing_entry[0];
		return array('post' => array('field_id_'.$this->field['field_id'] => $existing_entry['entry_id']));
	}



	private function post_data_ngen_file_field ()
	{
		global $DB, $LANG;
		$notification = array();
		$uploaded_file_name = '';
		// If value is empty, default to existing if possible
		if ($this->value === NULL || $this->value === '' || !isset($this->field['ff_settings'])) {
			if (isset($this->existing['field_id_'.$this->field['field_id']]))
				$uploaded_file_name = $this->existing['field_id_'.$this->field['field_id']];
		} else {
			// Get upload location set by user
			$upload_settings = unserialize($this->field['ff_settings']);
			$query = 'SELECT name, server_path
			          FROM exp_upload_prefs
			          WHERE id = '.$DB->escape_str($upload_settings['options']).'
			          AND site_id = '.$DB->escape_str($this->site_id);
			$query = $DB->query($query);
			$upload_location = $query->result;
			// If unable to get upload location
			if (empty($upload_location)) {
				$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_ngen_file_field_upload_location_1').$upload_settings['options'].$LANG->line('import_data_stage4_notification_ngen_file_field_upload_location_2'), TRUE);
			// If file location provided is invalid
			} else if (!file_exists($this->value)) {
				$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_ngen_file_field_file_missing').$this->value.$LANG->line('import_data_stage4_notification_quote'));
			// Else move file from location provided to upload location
			} else {
				$uploaded_file_name = basename($this->value);
				$upload_location = $upload_location[0];
				$destination = $upload_location['server_path'].$uploaded_file_name;
				$successful_move = rename($this->value, $destination);
				if ($successful_move) {
					$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_ngen_file_field_file_moved').$upload_location['name'].$LANG->line('import_data_stage4_notification_quote_left_square_bracket').$upload_location['server_path'].$LANG->line('import_data_stage4_notification_ngen_file_field_file_specified_by').$this->field['field_name'].$LANG->line('import_data_stage4_notification_ngen_file_field_file_field'), TRUE);
				} else {
					$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_ngen_file_field_file_not_moved').$uploaded_file_name.$LANG->line('import_data_stage4_notification_ngen_file_field_file_to').$upload_location['name'].$LANG->line('import_data_stage4_notification_quote_left_square_bracket').$upload_location['server_path'].$LANG->line('import_data_stage4_notification_ngen_file_field_file_specified_by').$this->field['field_name'].$LANG->line('import_data_stage4_notification_ngen_file_field_file_field'));
					$uploaded_file_name = '';
				}
			}
		}

		return array('post' => array('field_id_'.$this->field['field_id'] => array('file_name' => $uploaded_file_name, 'delete' => '', 'existing' => '')), 'notification' => $notification);
	}



	private function post_data_playa ()
	{
		global $DB, $LANG;

/*
 - If given no relationship, send empty
 - If given no value, send existing
 - If given value and not already updated this time, overwrite
 - If given value and already updated this time, keep existing
*/

		$notification = array();

		$previous_entries = array(0 => '');
		preg_match_all('/\[([0-9]+?)\]/', (isset($this->existing['field_id_'.$this->field['field_id']]) ? $this->existing['field_id_'.$this->field['field_id']] : ''), $matches);
		if (!empty($matches[1])) {
			$current_relations = $matches[1];
			// Convert relations into entry IDs
			$query = 'SELECT rel_child_id
			          FROM exp_relationships
			          WHERE rel_parent_id = '.$this->existing['entry_id'].'
			            AND rel_id IN ('.implode(',', $current_relations).')';
			$query = $DB->query($query);
			$get_previous_entries = $query->result;
			foreach ($get_previous_entries as $get_previous_entry)
				$previous_entries[] = $get_previous_entry['rel_child_id'];
		}

		// If given no relationship, send empty
		if (is_array($this->column_index)) {
			foreach ($this->column_index as $key => $index) {
				if (!isset($this->relationships[$index])) {
					$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_playa_defined_1').($index+1).$LANG->line('import_data_stage4_notification_playa_defined_2'), TRUE);
					unset($this->column_index[$key]);
					unset($this->value[$key]);
				}
			}
			if (empty($this->column_index))
				return array('post' => array('field_id_'.$this->field['field_id'] => array('old' => '', 'selections' => $previous_entries)), 'notification' => (empty($notification) ? '' : $notification));
		} else {
			if (!isset($this->relationships[$this->column_index]))
				return array('post' => array('field_id_'.$this->field['field_id'] => array('old' => '', 'selections' => $previous_entries)), 'notification' => (empty($notification) ? '' : $notification));
			$this->column_index = array($this->column_index);
		}

		// If given no value, send existing
		$return_existing = TRUE;
		foreach($this->value as $given_value)
			if (!empty($given_value) || $given_value == 0)
				$return_existing = FALSE;
		if ($return_existing)
			return array('post' => array('field_id_'.$this->field['field_id'] => array('old' => '', 'selections' => $previous_entries)), 'notification' => (empty($notification) ? '' : $notification));

		// If given value and not already updated, overwrite previous_entries
		if (isset($this->existing['entry_id']) && !in_array($this->existing['entry_id'], $this->added_ids) && !$this->addition_override)
			$previous_entries = array(0 => '');

		$i = 0;
		foreach ($this->column_index as $key => $index) {
			if (empty($this->value[$i])) {
				$i++;
				continue;
			}

			$pieces = explode('#', $this->relationships[$index]);

			if (!is_array($this->value[$i]))
				$this->value[$i] = array($this->value[$i]);

			$j = 0;
			foreach ($this->value[$i] as $given_value) {
				// If $pieces[1] is 0, we have selected a title
				if ($pieces[1] == 0) {
					$query = 'SELECT entry_id, title as field_id_'.$DB->escape_str($this->field['field_id']).'
			                FROM exp_weblog_titles
			                WHERE site_id = '.$DB->escape_str($this->site_id).'
			                  AND weblog_id = '.$DB->escape_str($pieces[0]).'
			                  AND title = \''.$DB->escape_str($given_value).'\'
			                LIMIT 1';
				} else {
					$query = 'SELECT entry_id, field_id_'.$DB->escape_str($this->field['field_id']).'
			                FROM exp_weblog_data
			                WHERE site_id = '.$DB->escape_str($this->site_id).'
			                  AND weblog_id = '.$DB->escape_str($pieces[0]).'
			                  AND field_id_'.$DB->escape_str($pieces[1]).' = \''.$DB->escape_str($given_value).'\'
			                LIMIT 1';
				}
				//echo "<br />\n".$query."<br /><br />\n";
				$query = $DB->query($query);
				$existing_entry = $query->result;
				if (isset($existing_entry[0]['entry_id']))
					$previous_entries[] = $existing_entry[0]['entry_id'];
				else
					$notification[] = $this->format_notification($LANG->line('import_data_stage4_notification_playa_missing_1').(($pieces[1] == 0) ? 'title' : 'field_id_'.$pieces[1]).$LANG->line('import_data_stage4_notification_equals_quote').$given_value.$LANG->line('import_data_stage4_notification_playa_missing_2'));
				$j++;
			}

			$i++;
		}
		$previous_entries = array_unique($previous_entries);

		return array('post' => array('field_id_'.$this->field['field_id'] => array('old' => '', 'selections' => $previous_entries)), 'notification' => $notification);
	}



	private function post_data_ff_checkbox ()
	{
		if ($this->value === NULL || $this->value === '')
			if (isset($this->existing['field_id_'.$this->field['field_id']]))
				$this->value = $this->existing['field_id_'.$this->field['field_id']];
			else
				$this->value = '';

		// Convert possible input to boolean
		$positive = array('yes', 'y', 'true',  'on');
		$negative = array('no',  'n', 'false', 'off');
		if (in_array($this->value, $positive))
			$this->value = TRUE;
		else if (in_array($this->value, $negative))
			$this->value = FALSE;
		else
			$this->value = (bool) $this->value;

		return array('post' => array('field_id_'.$this->field['field_id'] => ($this->value ? 'y' : 'n')));
	}



	private function post_data_wygwam ()
	{
		if ($this->value === NULL || $this->value === '')
			$this->value = (isset($this->existing['field_id_'.$this->field['field_id']]) ? $this->existing['field_id_'.$this->field['field_id']] : '');
		$data_array = array('old' => $this->existing['field_id_'.$this->field['field_id']],
												 'new' => $this->value);
		return array('post' => array('field_id_'.$this->field['field_id'] => $data_array));
	}



	// ---- USEFUL FUNCTIONS ---------------------


	private function format_notification ($notification, $global = FALSE)
	{
		global $LANG;
		return ($global ? '' : $LANG->line('import_data_stage4_notification_row_1').$this->row_number.$LANG->line('import_data_stage4_notification_row_2')).$notification;
	}


}


/* End of file file_type.class.php */
/* Location: ./system/modules/import_data/files/classes/supporting/file_type.class.php */
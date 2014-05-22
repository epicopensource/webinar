<?php

require_once "$CFG->dirroot/lib/formslib.php";
require_once 'lib.php';

$PAGE->set_url($CFG->wwwroot.$SCRIPT);

class mod_webinar_session_form extends moodleform {

    function definition()
    {
        global $CFG, $DB; 

        $mform =& $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->addElement('hidden', 'f', $this->_customdata['f']);
        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->addElement('hidden', 'c', $this->_customdata['c']);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Show all custom fields
        $customfields = $this->_customdata['customfields'];
        webinar_add_customfields_to_form($mform, $customfields);

		/*
		// Build dropdown of users from which a presenter for the session will be selected
		// Populate with users who have been assigned a host/presenter role
		// Host/Presenter role takes Moodle users who have been assigned a teacher or non-editing teacher role
		// NOTE: for single host license Adobe Connect accounts, the selected host must be the Adobe Connect account holder 
		// (and be registered under the same email address)
		*/

		$hosts_limit = 0;
		
		if($this->_customdata['f']) {

			$webinar = $DB->get_record('webinar', array('id' => $this->_customdata['f']));

			/* JoeB 10/03/2014 - AC9 approach below */

			$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword;
			
			$ch=curl_init($url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			$response = curl_exec($ch);
			curl_close($ch);
			

			//$response = file_get_contents($url);
			//$response = new SimpleXMLElement($response);
			//print_r($response);
			
			$breeze_session_first_strip = strstr($response, 'BREEZESESSION');
			$breeze_session_second_strip = strstr($breeze_session_first_strip, ';', true);
			$breeze_session = str_replace('BREEZESESSION=', '', $breeze_session_second_strip);

			// Create a stream for HTTP headers, including the BREEZESESSION cookie
			$opts = array(
			  'http'=>array(
			    'method'=>"GET",
			    'header'=>"Cookie: " . $breeze_session_second_strip . "\r\n"
			  )
			);

			$context = stream_context_create($opts);

			// capture the number of hosts allowed for this Adobe Connect account 
			$url = $webinar->sitexmlapiurl . "?action=principal-list"; 

			$xmlstr = file_get_contents($url, false, $context);
			$xml = new SimpleXMLElement($xmlstr);

			$p_array_count = 0;
			foreach($xml->{'principal-list'}->principal as $principal_array) {

				$principal = $principal_array; //$principal_array[$p_array_count];
				
				foreach($principal->name as $key => $val) {
					if ($val == 'Meeting Hosts') {
						foreach($principal->attributes() as $akey => $aval) {

							if($akey == 'principal-id') {
								$principal_id = $aval;
							}
						}
					}
				}
				$p_array_count++;
			}

			
			$url = $webinar->sitexmlapiurl . "?action=report-quotas";
			$xmlstr = file_get_contents($url, false, $context);
			$xml = new SimpleXMLElement($xmlstr);
			
			$q_array_count = 0;
			$found_limit = false;
			foreach($xml->{'report-quotas'}->quota as $quota_array) {
			
				$quota = $quota_array; //$quota_array[$q_array_count];
			
				foreach($quota->attributes() as $key => $val) {
					if($key == 'acl-id') {
						
						if((int)$val == (int)$principal_id) {
							$found_limit = true;
						}
					}
					if($found_limit) {
						if($key == 'limit') {
							$hosts_limit = $val;
							break;
						}
					}
				}
				$q_array_count++;
			}
		}
		
		if ($hosts_limit == 1) {
			//the only presenter that can be selected is a user with the same email address as the one registered on the Adobe Connect account
			$presenters = $DB->get_records_sql("SELECT
							u.id,
							u.firstname,
							u.lastname
						FROM 
							{$CFG->prefix}user u
						WHERE 
							u.email = '" . $webinar->adminemail . "'
						ORDER BY u.firstname ASC, u.lastname ASC");
		}
		else {
			$presenters = $DB->get_records_sql("SELECT
							u.id,
							u.firstname,
							u.lastname
						FROM 
							{$CFG->prefix}user u, 
							{$CFG->prefix}role r, 
							{$CFG->prefix}role_assignments ra
						WHERE 
							(r.archetype = 'teacher' OR r.archetype = 'editingteacher')
						AND
							ra.roleid = r.id
						AND
							ra.userid = u.id
						ORDER BY u.firstname ASC, u.lastname ASC");
		}

		$presenters_select = array();		
		$presenters_select[] = get_string('selecthost', 'webinar'); 		
				
		if($presenters) {
			foreach($presenters as $presenter) {
				$presenters_select[$presenter->id] = $presenter->firstname . " " . $presenter->lastname;
			}
		}

		$mform->addElement('select', 'presenter', get_string('presenter', 'webinar'), $presenters_select);
		$mform->addRule('presenter', null, 'required', null, 'client');
		$mform->setDefault('presenter', '');
		
		$mform->addElement('text', 'capacity', get_string('capacity', 'webinar'), 'size="5"');
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 10);
		
		$mform->addElement('date_time_selector', 'timestart', get_string('startdatetime', 'webinar'));
		$mform->setType('timestart', PARAM_INT);
		$mform->setDefault('timestart', time() + 3600 * 24);
		
		$mform->addElement('date_time_selector', 'timefinish', get_string('finishdatetime', 'webinar'));
        $mform->setType('timefinish', PARAM_INT);
		$mform->setDefault('timefinish', time() + 3600 * 25);

        $this->add_action_buttons();
    }

    function validation($data, $files)
    {

		$errors = parent::validation($data, $files);

		if($data['presenter'] == 0) {
			$errors['presenter'] = get_string('selecthosterror', 'webinar'); 
		}

        return $errors;
    }
}

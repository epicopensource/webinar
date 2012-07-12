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

		//Build dropdown of users from which a presenter for the session will be selected
		//Populate with users who have been assigned a host/presenter role
		//Host/Presenter role takes Moodle users who have been assigned a teacher or non-editing teacher role
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
        //$mform->setHelpButton('capacity', array('capacity', get_string('capacity', 'webinar'), 'webinar'));
		
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

<?php

require_once "$CFG->dirroot/course/moodleform_mod.php";

class mod_webinar_mod_form extends moodleform_mod {

    function definition()
    {
        global $CFG;

		$cfg_webinar = get_config('webinar');
		//print_r($cfg_webinar);
		
        $mform =& $this->_form;

        // GENERAL
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('htmleditor', 'description', get_string('description'), array('rows'  => 10, 'cols'  => 64));
        $mform->setType('description', PARAM_RAW);
        //$mform->setHelpButton('description', array('description', get_string('description'), 'webinar'));
        $mform->disabledIf('description', 'showoncalendar');

        $mform->addElement('htmleditor', 'agenda', 'Agenda', array('rows'  => 10, 'cols'  => 64));
        $mform->setType('agenda', PARAM_RAW);
        //$mform->setHelpButton('agenda', array('agenda', get_string('agenda'), 'webinar'));
        $mform->disabledIf('agenda', 'showoncalendar');

		//-------------------------------------------------------------------------------
		// Other Settings
        //$mform->addElement('header', 'advanced', get_string('othersettings', 'form'));
		
		$mform->addElement('hidden', 'sitexmlapiurl', $cfg_webinar->sitexmlapiurl);
		$mform->addElement('hidden', 'adminpassword', $cfg_webinar->adminpassword);
		$mform->addElement('hidden', 'adminemail', $cfg_webinar->adminemail);

		$this->standard_coursemodule_elements();
		
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values)
    {
        // Fix manager emails
        if (empty($default_values['confirmationinstrmngr'])) {
            $default_values['confirmationinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagerconfirmation'] = 1;
        }

        if (empty($default_values['reminderinstrmngr'])) {
            $default_values['reminderinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagerreminder'] = 1;
        }

        if (empty($default_values['cancellationinstrmngr'])) {
            $default_values['cancellationinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagercancellation'] = 1;
        }
    }
}

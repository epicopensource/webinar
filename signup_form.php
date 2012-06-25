<?php

require_once "$CFG->dirroot/lib/formslib.php";

class mod_webinar_signup_form extends moodleform {

    function definition()
    {
		global $CFG;
		
        $mform =& $this->_form;
        $manageremail = $this->_customdata['manageremail'];

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->addElement('hidden', 'backtoallsessions', $this->_customdata['backtoallsessions']);

        if ($manageremail === false) {
            $mform->addElement('hidden', 'manageremail', '');
        }
        else {
            $mform->addElement('html', get_string('manageremailinstructionconfirm', 'webinar')); // instructions

            $mform->addElement('text', 'manageremail', get_string('manageremail', 'webinar'), 'size="35"');
            $mform->addRule('manageremail', null, 'required', null, 'client');
            $mform->addRule('manageremail', null, 'email', null, 'client');
            $mform->setType('manageremail', PARAM_TEXT);
        }

        $this->add_action_buttons(true, get_string('signup', 'webinar'));
    }

    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        $manageremail = $data['manageremail'];
        if (!empty($manageremail)) {
            if (!webinar_check_manageremail($manageremail)) {
                $errors['manageremail'] = webinar_get_manageremailformat();
            }
        }

        return $errors;
    }
}

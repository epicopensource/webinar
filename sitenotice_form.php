<?php

require_once "$CFG->dirroot/lib/formslib.php";
require_once "$CFG->dirroot/mod/webinar/lib.php";

class mod_webinar_sitenotice_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('hidden', 'id', $this->_customdata['id']);

        $mform->addElement('text', 'name', get_string('name'), 'maxlength="255" size="50"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_MULTILANG);

        $mform->addElement('htmleditor', 'text', get_string('noticetext', 'webinar'), array('rows'  => 10, 'cols'  => 64));
        $mform->setType('text', PARAM_RAW);
        $mform->addRule('text', null, 'required', null, 'client');

        $mform->addElement('header', 'conditions', get_string('conditions', 'webinar'));
        $mform->addElement('html', get_string('conditionsexplanation', 'webinar'));

        // Show all custom fields
        $customfields = $this->_customdata['customfields'];
        webinar_add_customfields_to_form($mform, $customfields, true);

        $this->add_action_buttons();
    }
}

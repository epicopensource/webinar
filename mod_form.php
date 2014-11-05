<?php

require_once "$CFG->dirroot/course/moodleform_mod.php";

class mod_webinar_mod_form extends moodleform_mod {

    function definition()
    {
        global $CFG;

        $cfg_webinar = get_config('webinar');
        //print_r($cfg_webinar);

        $mform =&$this->_form;

        // GENERAL
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }

        $mform->addRule('name', null, 'required', null, 'client');


        /*
         * Panos Paralakis - 3/11/2014
         * Update WYSIWYG editor renders so to support file browsing
         */

        // Old
        //$mform->addElement('htmleditor', 'description', get_string('description'), array('rows'  => 10, 'cols'  => 64));

        // Update 3/11/2014
        $editoroptions = webinar_get_editor_options($this->context);

        $mform->addElement('editor', 'description', get_string('description'), null, $editoroptions);

        $mform->setType('description', PARAM_RAW);

        //$mform->setHelpButton('description', array('description', get_string('description'), 'webinar'));
        $mform->disabledIf('description', 'showoncalendar');

        // Added on update 3/11/2014
        if ($required) {
            $mform->addRule('description', get_string('required'), 'required', null, 'client');
        }

        // Old
        //$mform->addElement('htmleditor', 'agenda', 'Agenda', array('rows'  => 10, 'cols'  => 64));

        // Update 3/11/2014
        $mform->addElement('editor', 'agenda', 'Agenda', null, $editoroptions);
        $mform->setType('agenda', PARAM_RAW);

        //$mform->setHelpButton('agenda', array('agenda', get_string('agenda'), 'webinar'));
        $mform->disabledIf('agenda', 'showoncalendar');

        // Added on update 3/11/2014
        if ($required) {
            $mform->addRule('agenda', get_string('required'), 'required', null, 'client');
        }

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
        $editoroptions = webinar_get_editor_options($this->context);

        if ($this->current->instance) {
            // editing an existing webinar - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('description');
            $currenttext = file_prepare_draft_area($draftitemid,
                $this->context->id,
                'mod_webinar',
                'description',
                false,
                $editoroptions,
                $default_values['description']);
            $default_values['description'] =  array('text'=>$currenttext, 'format'=>FORMAT_HTML, 'itemid'=>$draftitemid);

            $draftitemid = file_get_submitted_draft_itemid('agenda');
            $currenttext = file_prepare_draft_area($draftitemid,
                $this->context->id,
                'mod_webinar',
                'agenda',
                false,
                $editoroptions,
                $default_values['agenda']);
            $default_values['agenda'] =  array('text'=>$currenttext, 'format'=>FORMAT_HTML, 'itemid'=>$draftitemid);

        }


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
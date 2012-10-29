<?php

/**
 * Structure step to restore one webinar activity
 */
class restore_webinar_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('webinar', '/activity/webinar');
        $paths[] = new restore_path_element('webinar_session', '/activity/webinar/sessions/session');
        $paths[] = new restore_path_element('webinar_sessions_dates', '/activity/webinar/sessions/session/sessions_dates/sessions_date');
        $paths[] = new restore_path_element('webinar_session_data', '/activity/webinar/sessions/session/session_data/session_data_element');
        $paths[] = new restore_path_element('webinar_session_field', '/activity/webinar/sessions/session/session_field/session_field_element');
        if ($userinfo) {
            $paths[] = new restore_path_element('webinar_signup', '/activity/webinar/sessions/session/signups/signup');
            $paths[] = new restore_path_element('webinar_signups_status', '/activity/webinar/sessions/session/signups/signup/signups_status/signup_status');
            $paths[] = new restore_path_element('webinar_session_roles', '/activity/webinar/sessions/session/session_roles/session_role');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_webinar($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
		
		$data->name = $data->name;
		$data->description = $data->description;
		$data->agenda = $data->agenda;
		$data->sitexmlapiurl = $data->sitexmlapiurl;
		$data->adminpassword = $data->adminpassword;
		$data->adminemail = $data->adminemail;

        // insert the webinar record
        $newitemid = $DB->insert_record('webinar', $data);
        $this->apply_activity_instance($newitemid);
    }


    protected function process_webinar_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->webinar = $this->get_new_parentid('webinar');

		$data->capacity = $data->capacity;
		$data->presenter = $data->presenter;
		$data->scoid = $data->scoid;
		$data->urlpath = $data->urlpath;
		
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_sessions', $data);
        $this->set_mapping('webinar_session', $oldid, $newitemid, true); // childs and files by itemname
    }


    protected function process_webinar_signup($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('webinar_session');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_signups', $data);
        $this->set_mapping('webinar_signup', $oldid, $newitemid, true); // childs and files by itemname
    }


    protected function process_webinar_signups_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->signupid = $this->get_new_parentid('webinar_signup');

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_signups_status', $data);
    }


    protected function process_webinar_session_roles($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('webinar_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->roleid = $this->get_mappingid('role', $data->roleid);

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_session_roles', $data);
    }


    protected function process_webinar_session_data($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('webinar_session');
        $data->fieldid = $this->get_mappingid('webinar_session_field');

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_session_data', $data);
        $this->set_mapping('webinar_session_data', $oldid, $newitemid, true); // childs and files by itemname
    }


    protected function process_webinar_session_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_session_field', $data);
    }


    protected function process_webinar_sessions_dates($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('webinar_session');

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);

        // insert the entry record
        $newitemid = $DB->insert_record('webinar_sessions_dates', $data);
    }

    protected function after_execute() {
        
    }
}
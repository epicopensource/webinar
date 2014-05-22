<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'session_form.php';
require_once 'createmeeting.php';
require_once 'updatemeeting.php';
require_once 'sendemailsessionupdated.php';
require_once 'deletemeeting.php';
require_once 'sendemailsessiondeleted.php';

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // webinar Module ID
$s = optional_param('s', 0, PARAM_INT); // webinar session ID
$c = optional_param('c', 0, PARAM_INT); // copy session
$d = optional_param('d', 0, PARAM_INT); // delete session
$confirm = optional_param('confirm', false, PARAM_BOOL); // delete confirmation

$nbdays = 1; // default number to show

$session = null;
if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        error(get_string('error:incorrectcoursemoduleid', 'webinar'));
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        error(get_string('error:coursemisconfigured', 'webinar'));
    }
    if (!$webinar = $DB->get_record('webinar', array('id' => $cm->instance))) {
        error(get_string('error:incorrectcoursemodule', 'webinar'));
    }
}
elseif ($s) {

     if (!$session = webinar_get_session($s)) {
         error(get_string('error:incorrectcoursemodulesession', 'webinar'));
     }
     if (!$webinar = $DB->get_record('webinar', array('id' => $session->webinar))) {
         error(get_string('error:incorrectwebinarid', 'webinar'));
     }
     if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
         error(get_string('error:coursemisconfigured', 'webinar'));
     }
     if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
         error(get_string('error:incorrectcoursemoduleid', 'webinar'));
     }

     $nbdays = count($session->sessiondates);
	 
}
else {
    if (!$webinar = $DB->get_record('webinar', array('id' => $f))) {
        error(get_string('error:incorrectwebinarid', 'webinar'));
    }
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        error(get_string('error:coursemisconfigured', 'webinar'));
    }
    if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
        error(get_string('error:incorrectcoursemoduleid', 'webinar'));
    }
}

require_course_login($course);
$errorstr = '';
$context = context_course::instance($course->id, CONTEXT_COURSE); //get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('mod/webinar:editsessions', $context);

$returnurl = "view.php?f=$webinar->id";

// Handle deletions
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

	/* Delete meeting through Adobe Connect API call  */
	delete_meeting($webinar, $session);
	
	//Send email to all registered attendees when session is deleted
	send_email_sessiondeleted($webinar, $session);
	
    if (webinar_delete_session($session)) {
        add_to_log($course->id, 'webinar', 'delete session', 'sessions.php?s='.$session->id, $webinar->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'webinar', 'delete session (FAILED)', 'sessions.php?s='.$session->id, $webinar->id, $cm->id);
        print_error('error:couldnotdeletesession', 'webinar', $returnurl);
    }
    redirect($returnurl);
}

$customfields = webinar_get_session_customfields();

$mform = new mod_webinar_session_form(null, compact('id', 'f', 's', 'c', 'nbdays', 'customfields', 'course'));
//$mform = new mod_webinar_session_form(null, array('webinar'=> $webinar), compact('id', 'f', 's', 'c', 'nbdays', 'customfields', 'course'));

if ($mform->is_cancelled()){
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'webinar', $returnurl);
    }
	
    $date = new object();
    $date->timestart = $fromform->timestart;
    $date->timefinish = $fromform->timefinish;
    $sessiondates[] = $date;
	
    $todb = new object();
    $todb->webinar = $webinar->id;
    $todb->capacity = $fromform->capacity;
	$todb->presenter = $fromform->presenter;
	
	$presenter_details = array();
	
	$presenters = $DB->get_records_sql("SELECT
                        u.firstname,
                        u.lastname, 
						u.email 
                    FROM 
						{$CFG->prefix}user u 
                    WHERE 
						u.id = $todb->presenter LIMIT 1");
				

	if($presenters) {
		foreach($presenters as $presenter) {
			$presenter_details = $presenter;
		}
	}

    $sessionid = null;
   // begin_sql();

    $update = false;

    if (!$c and $session != null) {
	
        $update = true;
		
		/* Update meeting details through Adobe Connect API call  */
		update_meeting($webinar, $session, $date, $presenter_details);
		
		//Send email to all registered attendees when session/meeting details are changed
		send_email_sessionupdated($webinar, $session, $cm, $date);
		
        $sessionid = $session->id;

        $todb->id = $session->id;
        if (!webinar_update_session($todb, $sessiondates)) {
            rollback_sql();
            add_to_log($course->id, 'webinar', 'update session (FAILED)', "sessions.php?s=$session->id", $webinar->id, $cm->id);
            print_error('error:couldnotupdatesession', 'webinar', $returnurl);
        }

        // Remove old site-wide calendar entry
        if (!webinar_remove_session_from_site_calendar($session)) {
            rollback_sql();
            print_error('error:couldnotupdatecalendar', 'webinar', $returnurl);
        }
    }
    else {
	
		//Create meeting through Adobe Connect API call
		$webinardetails = create_meeting($webinar, $fromform, $date, $presenter_details);

		$todb->scoid = $webinardetails->scoid;
		$todb->urlpath = $webinardetails->urlpath;

        if (!$sessionid = webinar_add_session($todb, $sessiondates)) {
            rollback_sql();
            add_to_log($course->id, 'webinar', 'add session (FAILED)', 'sessions.php?f='.$webinar->id, $webinar->id, $cm->id);
            print_error('error:couldnotaddsession', 'webinar', $returnurl);
        }
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        if (!isset($fromform->$fieldname)) {
            $fromform->$fieldname = ''; // need to be able to clear fields
        }

        if (!webinar_save_customfield_value($field->id, $fromform->$fieldname, $sessionid, 'session')) {
            rollback_sql();
            print_error('error:couldnotsavecustomfield', 'webinar', $returnurl);
        }
    }

    // Retrieve record that was just inserted/updated
    if (!$session = webinar_get_session($sessionid)) {
        rollback_sql();
        print_error('error:couldnotfindsession', 'webinar', $returnurl);
    }

    // Put the session in the site-wide calendar (needs customfields to be up to date)
    if (!webinar_add_session_to_site_calendar($session, $webinar)) {
        rollback_sql();
        print_error('error:couldnotupdatecalendar', 'webinar', $returnurl);
    }

    if ($update) {
        add_to_log($course->id, 'webinar', 'update session', "sessions.php?s=$session->id", $webinar->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'webinar', 'add session', 'webinar', 'sessions.php?f='.$webinar->id, $webinar->id, $cm->id);
    }

    //commit_sql();
    redirect($returnurl);
}
elseif ($session != null) { // Edit mode
    
	// Set values for the form
	
	$date = new object();
	$date->timestart = $session->sessiondates[0]->timestart;
	$date->timefinish = $session->sessiondates[0]->timefinish;
    $sessiondates[] = $date;

    $toform = new object();
	$toform->timestart = $date->timestart;
    $toform->timefinish = $date->timefinish;
	
	$toform->webinar = $session->id;
    $toform->capacity = $session->capacity;
	$toform->presenter = $session->presenter;
	$toform->scoid = $session->scoid;
	$toform->urlpath = $session->urlpath;

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        $toform->$fieldname = webinar_get_customfield_value($field, $session->id, 'session');
    }

    $mform->set_data($toform);
}

if ($c) {
    $heading = get_string('copyingsession', 'webinar', $webinar->name);
}
else if ($d) {
    $heading = get_string('deletingsession', 'webinar', $webinar->name);
}
else if ($id or $f) {
    $heading = get_string('addingsession', 'webinar', $webinar->name);
}
else {
    $heading = get_string('editingsession', 'webinar', $webinar->name);
}

$pagetitle = format_string($webinar->name);
$navlinks[] = array('name' => get_string('modulenameplural', 'webinar'), 'link' => "index.php?id=$course->id", 'type' => 'title');
$navlinks[] = array('name' => $pagetitle, 'link' => "view.php?f=$webinar->id", 'type' => 'activityinstance');
$navlinks[] = array('name' => $heading, 'link' => '', 'type' => 'title');
$navigation = build_navigation($navlinks);

/*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, get_string('modulename', 'webinar')), navmenu($course, $cm));*/
$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->navbar->add($webinar->name);
echo $OUTPUT->header();

//print_box_start();
echo $OUTPUT->box_start();
//print_heading($heading, 'center');
echo $OUTPUT->heading($heading);

if (!empty($errorstr)) {
    echo '<div class="notifyproblem" align="center"><span style="font-size: 12px; line-height: 18px;">'.$errorstr.'</span></div>';
}

if ($d) {
    $viewattendees = has_capability('mod/webinar:viewattendees', $context);
    webinar_print_session($session, $viewattendees);
    //JoeB - dev change for Moodle 2.3 - replace reference to deprecated function notice_yesno()
	//notice_yesno(get_string('deletesessionconfirm', 'webinar', format_string($webinar->name)),
    //             "sessions.php?s=$session->id&amp;d=1&amp;confirm=1&amp;sesskey=$USER->sesskey", $returnurl);
	echo $OUTPUT->confirm(get_string('deletesessionconfirm', 'webinar', format_string($webinar->name)),
                 "sessions.php?s=$session->id&amp;d=1&amp;confirm=1&amp;sesskey=$USER->sesskey", $returnurl);
}
else {
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

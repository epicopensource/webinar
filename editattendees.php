<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'signupmeeting.php';
require_once 'cancelsignupmeeting.php';
require_once 'sendemailsignup.php';
require_once 'sendemailcancelsignup.php';

define('MAX_USERS_PER_PAGE', 5000);

$s              = required_param('s', PARAM_INT); // webinar session ID
$add            = optional_param('add', 0, PARAM_BOOL);
$remove         = optional_param('remove', 0, PARAM_BOOL);
$showall        = optional_param('showall', 0, PARAM_BOOL);
$searchtext     = optional_param('searchtext', '', PARAM_RAW); // search string
$suppressemail  = optional_param('suppressemail', false, PARAM_BOOL); // send email notifications
$previoussearch = optional_param('previoussearch', 0, PARAM_BOOL);
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT); // webinar activity to go back to

if (!$session = webinar_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'webinar');
}
if (!$webinar = $DB->get_record('webinar', array('id' => $session->webinar))) {
    print_error('error:incorrectwebinarid', 'webinar');
}
if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
    print_error('error:coursemisconfigured', 'webinar');
}
if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
    print_error('error:incorrectcoursemodule', 'webinar');
}

/// Check essential permissions
require_course_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('mod/webinar:viewattendees', $context);

/// Get some language strings
$strsearch = get_string('search');
$strshowall = get_string('showall');
$strsearchresults = get_string('searchresults');
$strwebinars = get_string('modulenameplural', 'webinar');
$strwebinar = get_string('modulename', 'webinar');

$errors = array();

/// Handle the POST actions sent to the page
if ($frm = data_submitted()) {

    // Add button
    if ($add and !empty($frm->addselect) and confirm_sesskey()) {
        require_capability('mod/webinar:addattendees', $context);

        foreach ($frm->addselect as $adduser) {
            if (!$adduser = clean_param($adduser, PARAM_INT)) {
                continue; // invalid userid
            }

            // Make sure that the user is enroled in the course
            if (!has_capability('moodle/course:view', $context, $adduser)) {
                $user = $DB->get_record('user', array('id' => $adduser));

            }

            if (!webinar_session_has_capacity($session, $context)) {
                    $errors[] = get_string('full', 'webinar');
                    break; // no point in trying to add other people
            }

            // Check if we are waitlisting or booking
             //if ($session->datetimeknown) {
              //      $status = WEBINAR_STATUS_BOOKED;
            //} 
			//else {
                    $status = WEBINAR_STATUS_WAITLISTED;
           //}

            if (!webinar_user_signup($session, $webinar, $course, '', WEBINAR_BOTH,
                                                $status, $adduser, !$suppressemail)) {
                    $erruser = $DB->get_record('user', array('id' => $adduser), 'id, firstname, lastname');
                    $errors[] = get_string('error:addattendee', 'webinar', fullname($erruser));
            }
			else {
				//Sign up user to this webinar through Adobe Connect API call
				signup_meeting($webinar, $session, $user);
					
				//Send registration email to user
				send_email_signup($webinar, $session, $cm, $user);
			}
        }
    }
    // Remove button
    else if ($remove and !empty($frm->removeselect) and confirm_sesskey()) {
        require_capability('mod/webinar:removeattendees', $context);

        foreach ($frm->removeselect as $removeuser) {
            if (!$removeuser = clean_param($removeuser, PARAM_INT)) {
                continue; // invalid userid
            }

            if (webinar_user_cancel($session, $removeuser, true, $cancelerr)) {
                // Notify the user of the cancellation if the session hasn't started yet
                $timenow = time();
                if (!$suppressemail and !webinar_has_session_started($session, $timenow)) {
                    webinar_send_cancellation_notice($webinar, $session, $removeuser);
                }
            }
            else {
                $errors[] = $cancelerr;
                $erruser = $DB->get_record('user', array('id' => $removeuser), 'id, firstname, lastname');
                $errors[] = get_string('error:removeattendee', 'webinar', fullname($erruser));
            }
        }

        // Update attendees
        webinar_update_attendees($session);
		
		$user = $DB->get_record('user', array('id' => $removeuser));
		
		//Unregister this user from this webinar through Adobe Connect API call
		cancelsignup_meeting($webinar, $session, $user);
		
		//Send cancel registration email to user
		send_email_cancelsignup($webinar, $session, $cm, $user);
    }
    // "Show All" button
    elseif ($showall) {
        $searchtext = '';
        $previoussearch = 0;
    }
}

/// Main page
$pagetitle = format_string($webinar->name);
$navlinks[] = array('name' => $strwebinars, 'link' => "index.php?id=$course->id", 'type' => 'title');
$navlinks[] = array('name' => $pagetitle, 'link' => "view.php?f=$webinar->id", 'type' => 'activityinstance');
$navlinks[] = array('name' => get_string('attendees', 'webinar'), 'link' => "attendees.php?s=$session->id", 'type' => 'activityinstance');
$navlinks[] = array('name' => get_string('addremoveattendees', 'webinar'), 'link' => '', 'type' => 'title');
$navigation = build_navigation($navlinks);
/*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, $strwebinar), navmenu($course, $cm));*/
					
$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->set_url('/editattendees.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions);
echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading(get_string('addremoveattendees', 'webinar'));

/// Get the list of currently signed-up users
$existingusers = webinar_get_attendees($session->id);
$existingcount = $existingusers ? count($existingusers) : 0;

$select  = "username <> 'guest' AND deleted = 0 AND confirmed = 1";

/// Apply search terms
$searchtext = trim($searchtext);
if ($searchtext !== '') {   // Search for a subset of remaining users
    $LIKE      = sql_ilike();
    $FULLNAME  = sql_fullname();

    $selectsql = " AND ($FULLNAME $LIKE '%$searchtext%' OR
                            email $LIKE '%$searchtext%' OR
                         idnumber $LIKE '%$searchtext%' OR
                         username $LIKE '%$searchtext%') ";
    $select  .= $selectsql;
}

/// All non-signed up system users

$availableusers = $DB->get_recordset_sql('SELECT u.id, u.firstname, u.lastname, u.email 
	FROM '.$CFG->prefix.'user u, '.$CFG->prefix.'enrol e, '.$CFG->prefix.'user_enrolments ue 
	WHERE 
	e.courseid = '.$course->id.'
	AND 
	ue.enrolid = e.id 
	AND 
	u.id = ue.userid 
	ORDER BY u.lastname ASC, u.firstname ASC');			  
										  
$availablecount = $DB->get_recordset_sql('SELECT COUNT(u.id) AS avusers
	FROM '.$CFG->prefix.'user u, '.$CFG->prefix.'enrol e, '.$CFG->prefix.'user_enrolments ue 
	WHERE 
	e.courseid = '.$course->id.'
	AND 
	ue.enrolid = e.id 
	AND 
	u.id = ue.userid');		

foreach ($availablecount as $ac) {
	$potential_users = $ac->avusers;
}

//$usercount = $DB->count_records_select('user', $select) - $existingcount;
$usercount = $potential_users;

// Get all signed up non-attendees
$nonattendees = 0;

$nonattendees_sql = "
        SELECT
            u.id,
            u.firstname,
            u.lastname,
            u.email,
            ss.statuscode
        FROM
            {$CFG->prefix}webinar_sessions s
        JOIN
            {$CFG->prefix}webinar_signups su
         ON s.id = su.sessionid
        JOIN
            {$CFG->prefix}webinar_signups_status ss
         ON su.id = ss.signupid
        JOIN
            {$CFG->prefix}user u
         ON u.id = su.userid
        WHERE
            s.id = {$session->id}
        AND ss.superceded != 1
        AND ss.statuscode = ".WEBINAR_STATUS_REQUESTED."
        ORDER BY
            u.lastname, u.firstname
    ";

$nonattendees_rs = $DB->get_recordset_sql($nonattendees_sql);

$table = new html_table();
$table->head = array(get_string('name'), get_string('email'), get_string('status'));
$table->align = array('left');
$table->size = array('50%');
$table->width = '70%';

if ($na_rs = $DB->get_recordset_sql($nonattendees_sql)) {
	foreach ($na_rs as $record) {
		$data[] = $record->firstname . ' ' . $record->lastname;
		
		$data[] = $record->email;
		//$data[] = get_string('status_'.webinar_get_status($user->statuscode), 'webinar');
		
		$table->data[] = $data;
	}
	
	$na_rs->close();
}

/// Prints a form to add/remove users from the session
include('editattendees.html');

if (!empty($errors)) {
    $msg = '<p>';
    foreach ($errors as $e) {
        $msg .= $e.'<br />';
    }
    $msg .= '</p>';
    print_simple_box_start('center');
    notify($msg);
    print_simple_box_end();
}

// Bottom of the page links
print '<p style="text-align: center">';
$url = $CFG->wwwroot.'/mod/webinar/attendees.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions;
print '<a href="'.$url.'">'.get_string('goback', 'webinar').'</a></p>';

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

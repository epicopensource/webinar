<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'signup_form.php';
require_once 'signupmeeting.php';
require_once 'sendemailsignup.php';

$PAGE->set_url($CFG->wwwroot.$SCRIPT);

$s = required_param('s', PARAM_INT); // webinar session ID
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

if (!$session = webinar_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'webinar');
}
if (!$webinar = $DB->get_record('webinar', array('id' => $session->webinar))) {
    print_error('error:incorrectwebinarid', 'webinar');
}
if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
    print_error('error:coursemisconfigured', 'webinar');
}
if (!$cm = get_coursemodule_from_instance("webinar", $webinar->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'webinar');
}

require_course_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('mod/webinar:view', $context);

$returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
if ($backtoallsessions) {
    $returnurl = "$CFG->wwwroot/mod/webinar/view.php?f=$backtoallsessions";
}

$pagetitle = format_string($webinar->name);
$navlinks[] = array('name' => get_string('modulenameplural', 'webinar'), 'link' => "index.php?id=$course->id", 'type' => 'title');
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);

// Guests can't signup for a session, so offer them a choice of logging in or going back.
if (isguestuser()) {
    $loginurl = $CFG->wwwroot.'/login/index.php';
	
    if (!empty($CFG->loginhttps)) {
        $loginurl = str_replace('http:','https:', $loginurl);
    }

    /*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, get_string('modulename', 'webinar')));*/
	$PAGE->set_pagetype('webinar');
	$PAGE->set_title($webinar->name);
	$PAGE->set_heading($webinar->name);
	echo $OUTPUT->header();
    //JoeB - remove deprecated reference to notice_yesno
	echo $OUTPUT->confirm('<p>' . get_string('guestsno', 'webinar') . "</p>\n\n</p>" .
        get_string('liketologin') . '</p>', $loginurl, get_referer(false));
    echo $OUTPUT->footer();
    exit();
}

require_capability('mod/webinar:signup', $context);

$manageremail = false;
if (get_config(NULL, 'webinar_addchangemanageremail')) {
    $manageremail = webinar_get_manageremail($USER->id);
}

$showdiscountcode = ($session->discountcost > 0);

$mform = new mod_webinar_signup_form(null, compact('s', 'backtoallsessions', 'manageremail', 'showdiscountcode'));
if ($mform->is_cancelled()){
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'webinar', $returnurl);
    }

    // User can not update Manager's email (depreciated functionality)
    if (!empty($fromform->manageremail)) {
        add_to_log($course->id, 'webinar', 'update manageremail (FAILED)', "signup.php?s=$session->id", $webinar->id, $cm->id);
    }

    // Get signup type
    if (!$session->datetimeknown) {
        $statuscode = WEBINAR_STATUS_WAITLISTED;
    } elseif (webinar_get_num_attendees($session->id) < $session->capacity) {
        // Save available
        $statuscode = WEBINAR_STATUS_BOOKED;
    } else {
        $statuscode = WEBINAR_STATUS_WAITLISTED;
    }

    if (!webinar_session_has_capacity($session, $context)) {
        print_error('sessionisfull', 'webinar', $returnurl);
    }

    elseif (webinar_manager_needed($webinar) && !webinar_get_manageremail($USER->id)){
        print_error('error:manageremailaddressmissing', 'webinar', $returnurl);
    }
    elseif ($submissionid = webinar_user_signup($session, $webinar, $course, $fromform->discountcode, $fromform->notificationtype, $statuscode)) {
        add_to_log($course->id, 'webinar','signup',"signup.php?s=$session->id", $session->id, $cm->id);

		//Sign up user to this webinar through Adobe Connect API call
		signup_meeting($webinar, $session, $USER);
		
		//Send registration email to user
		send_email_signup($webinar, $session, $cm, $USER);
		
		$PAGE->set_pagetype('webinar');
		$PAGE->set_title($webinar->name);
		$PAGE->set_heading($webinar->name);
		echo $OUTPUT->header();
		
		
		$heading = get_string('registersuccess', 'webinar');
		echo $OUTPUT->heading($heading);
		
        $message = get_string('registrationsuccessful', 'webinar', $webinar->name);
        $timemessage = 4;
		
		$message = '<div style="height: 10px;">&nbsp;</div>' . $message;
        redirect($returnurl, $message, $timemessage);
    }
    else {
        add_to_log($course->id, 'webinar','signup (FAILED)',"signup.php?s=$session->id", $session->id, $cm->id);
        print_error('error:problemsigningup', 'webinar', $returnurl);
    }

    redirect($returnurl);
}
elseif ($manageremail !== false) {
    // Set values for the form
    $toform = new object();
    $toform->manageremail = $manageremail;
    $mform->set_data($toform);
}

/*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, get_string('modulename', 'webinar')));*/
$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
echo $OUTPUT->header();

$heading = get_string('registeron', 'webinar', $webinar->name);

$viewattendees = has_capability('mod/webinar:viewattendees', $context);
$signedup = webinar_check_signup($webinar->id);

/*
if ($signedup and $signedup != $session->id) {
    print_error('error:signedupinothersession', 'webinar', $returnurl);
}
*/

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if (!$signedup and !webinar_session_has_capacity($session, $context)) {
    print_error('sessionisfull', 'webinar', $returnurl);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    exit;
}

//print_r($webinar);

webinar_print_session($session, $viewattendees);

/*
if ($signedup) {
    // Cancellation link
    echo '<a href="'.$CFG->wwwroot.'/mod/webinar/cancelsignup.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions.'" title="'.get_string('cancelbooking','webinar').'">'.get_string('cancelbooking', 'webinar').'</a>';

    // See attendees link
    if ($viewattendees) {
        echo ' &ndash; <a href="'.$CFG->wwwroot.'/mod/webinar/attendees.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions.'" title="'.get_string('seeattendees', 'webinar').'">'.get_string('seeattendees', 'webinar').'</a>';
    }

    echo '<br/><a href="'.$returnurl.'" title="'.get_string('goback', 'webinar').'">'.get_string('goback', 'webinar').'</a>';
}
// Don't allow signup to proceed if a manager is required
elseif (webinar_manager_needed($webinar) && !webinar_get_manageremail($USER->id)){
*/
if (webinar_manager_needed($webinar) && !webinar_get_manageremail($USER->id)){
    // Check to see if the user has a managers email set
    echo '<p><strong>'.get_string('error:manageremailaddressmissing', 'webinar').'</strong></p>';
    echo '<br/><a href="'.$returnurl.'" title="'.get_string('goback', 'webinar').'">'.get_string('goback', 'webinar').'</a>';
}
else {
    // Signup form
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

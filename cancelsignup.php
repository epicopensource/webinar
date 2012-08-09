<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'cancelsignup_form.php';
require_once 'cancelsignupmeeting.php';
require_once 'sendemailcancelsignup.php';

$s  = required_param('s', PARAM_INT); // webinar session ID
$confirm           = optional_param('confirm', false, PARAM_BOOL);
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

$mform = new mod_webinar_cancelsignup_form(null, compact('s', 'backtoallsessions'));
if ($mform->is_cancelled()){
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'webinar', $returnurl);
    }

    $timemessage = 4;

    $errorstr = '';
    if (webinar_user_cancel($session, false, false, $errorstr, $fromform->cancelreason)) {
        add_to_log($course->id, 'webinar', 'cancel booking', "cancelsignup.php?s=$session->id", $webinar->id, $cm->id);

		//Unregister this user from this webinar through Adobe Connect API call
		cancelsignup_meeting($webinar, $session, $USER);
		
		//Send cancel registration email to user
		send_email_cancelsignup($webinar, $session, $cm, $USER);
		
		/* JoeB - dev change for Moodle 2.3, comment this out
		$PAGE->set_pagetype('webinar');
		$PAGE->set_title($webinar->name);
		$PAGE->set_heading($webinar->name);
		echo $OUTPUT->header();
		
		$heading = get_string('confirmcancelbooking', 'webinar');
		echo $OUTPUT->heading($heading);
		*/
		
        $message = get_string('bookingcancelled', 'webinar', $webinar->name);
		$message = '<div style="height: 10px;">&nbsp;</div>' . $message;

        redirect($returnurl, $message, $timemessage);
    }
    else {
        add_to_log($course->id, 'webinar', "cancel booking (FAILED)", "cancelsignup.php?s=$session->id", $webinar->id, $cm->id);
        redirect($returnurl, $errorstr, $timemessage);
    }

    redirect($returnurl);
}

$pagetitle = format_string($webinar->name);
$navlinks[] = array('name' => get_string('modulenameplural', 'webinar'), 'link' => "index.php?id=$course->id", 'type' => 'title');
$navlinks[] = array('name' => $pagetitle, 'link' => '', 'type' => 'activityinstance');
$navigation = build_navigation($navlinks);
/*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, get_string('modulename', 'webinar')));*/
$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->set_url('/cancelsignup.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions);
echo $OUTPUT->header();

$heading = get_string('cancelbookingfor', 'webinar', $webinar->name);

$viewattendees = has_capability('mod/webinar:viewattendees', $context);
$signedup = webinar_check_signup($webinar->id);

//JoeB - dev changes for Moodle 2.3 - replace references for deprecated function
//print_box_start();
//print_heading($heading, 'center');
echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($signedup) {
    webinar_print_session($session, $viewattendees);
    $mform->display();
}
else {
    print_error('notsignedup', 'webinar', $returnurl);
}

//print_box_end();
//print_footer($course);
echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

<?php

/* Sends an email to a newly registered attendee on a session */

function send_email_signup($webinar, $session_info, $cm, $user) {

	global $CFG;

	foreach($session_info->sessiondates as $dates) {
		$startdatetime = date('d F Y', $dates->timestart) . " at " . date('h:i A', $dates->timestart);
	}

	$a = new stdClass();
	$a->name = $user->firstname . " " . $user->lastname;
	$a->starttime = $startdatetime;
	$a->webinarname = $webinar->name;
	$a->webinarintro = $webinar->description;
	$a->webinaragenda = $webinar->agenda;
	//$a->sessionurl = $webinar->sitexmlapiurl  . $session_info->urlpath;
	$a->sessionurl = $CFG->wwwroot . "/mod/" . $cm->modname . "/view.php?id=" . $cm->id;
	$a->adminemail = $webinar->adminemail;
	
	//print_r($a);

	$subject = get_string('sessionregistersubject', 'webinar', $a);
	$contact = get_string('sessionregistercontact', 'webinar', $a);
	$message = get_string('sessionregistermessage', 'webinar', $a);

	email_to_user($user, $contact, $subject, $message);
}

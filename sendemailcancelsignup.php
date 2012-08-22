<?php

/* Sends an email to a newly un-registered attendee on a session */

function send_email_cancelsignup($webinar, $session_info, $cm, $user) {

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
	$a->adminemail = $webinar->adminemail;
	
	//print_r($a);

	$subject = get_string('sessionunregistersubject', 'webinar', $a);
	$contact = get_string('sessionunregistercontact', 'webinar', $a);
	$message = get_string('sessionunregistermessage', 'webinar', $a);

	//last task - strip <p> and </p> from the message before we send the email
	$message = str_replace('<p>','',$message);
	$message = str_replace('</p>','',$message);

	email_to_user($user, $contact, $subject, $message);
}

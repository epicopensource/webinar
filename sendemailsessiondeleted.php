<?php

require_once 'lib.php';

/* Sends an email to all attendees upon a session being deleted/cancelled */

function send_email_sessiondeleted($webinar, $session_info) {

	global $CFG;

	foreach($session_info->sessiondates as $dates) {
		$startdatetime = date('d F Y', $dates->timestart) . " at " . date('h:i A', $dates->timestart);
	}
	
	if ($attendees = webinar_get_attendees($session_info->id)) {
		foreach($attendees as $user) {
	
			$a = new stdClass();
			$a->name = $user->firstname . " " . $user->lastname;
			$a->starttime = $startdatetime;
			$a->webinarname = $webinar->name;
			$a->webinarintro = $webinar->description;
			$a->webinaragenda = $webinar->agenda;
			$a->adminemail = $webinar->adminemail;
			
			//print_r($a);

			$subject = get_string('sessiondeletedsubject', 'webinar', $a);
			$contact = get_string('sessiondeletedcontact', 'webinar', $a);
			$message = get_string('sessiondeletedmessage', 'webinar', $a);
			//last task - strip <p> and </p> from the message before we send the email
			$message = str_replace('<p>','',$message);
			$message = str_replace('</p>','',$message);

			email_to_user($user, $contact, $subject, $message);
		}
	}
}

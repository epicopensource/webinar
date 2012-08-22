<?php

require_once 'lib.php';

/* Sends an email to a newly un-registered attendee on a session */

function send_email_sessionupdated($webinar, $session_info, $cm, $date) {

	global $CFG;

	if ($attendees = webinar_get_attendees($session_info->id)) {
		foreach($attendees as $user) {
	
			$a = new stdClass();
			$a->name = $user->firstname . " " . $user->lastname;
			$a->starttime = date('d F Y h:i A', $date->timestart);
			$a->webinarname = $webinar->name;
			$a->webinarintro = $webinar->description;
			$a->webinaragenda = $webinar->agenda;
			$a->sessionurl = $CFG->wwwroot . "/mod/" . $cm->modname . "/view.php?id=" . $cm->id;
			$a->adminemail = $webinar->adminemail;
			
			//print_r($a);

			$subject = get_string('sessionupdatedsubject', 'webinar', $a);
			$contact = get_string('sessionupdatedcontact', 'webinar', $a);
			$message = get_string('sessionupdatedmessage', 'webinar', $a);

			//last task - strip <p> and </p> from the message before we send the email
			$message = str_replace('<p>','',$message);
			$message = str_replace('</p>','',$message);

			email_to_user($user, $contact, $subject, $message);
		}
	}
}

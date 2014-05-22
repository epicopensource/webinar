<?php

require_once '../../config.php';
require_once 'lib.php';

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f  = optional_param('f', 0, PARAM_INT); // webinar activity ID
$s  = optional_param('s', 0, PARAM_INT); // webinar session ID
$takeattendance    = optional_param('takeattendance', false, PARAM_BOOL); // take attendance
$cancelform        = optional_param('cancelform', false, PARAM_BOOL); // cancel request
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT); // webinar activity to go back to

if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'webinar');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'webinar');
    }
    if (!$webinar = $DB->get_record('webinar', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'webinar');
    }
}
elseif ($s) {
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
}
else {
    if (!$webinar = $DB->get_record('webinar', array('id' => $f))) {
        print_error('error:incorrectwebinarid', 'webinar');
    }
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        print_error('error:coursemisconfigured', 'webinar');
    }
    if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
        print_error('error:incorrectcoursemodule', 'webinar');
    }
 }

require_course_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('mod/webinar:viewattendees', $context);

// Handle submitted data
if ($form = data_submitted()) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    require_capability('mod/webinar:takeattendance', $context);

    if ($cancelform) {
        redirect("attendees.php?s=$s&amp;backtoallsessions=$backtoallsessions");
    }
    elseif (!empty($form->requests)) {
        // Approve requests
        if (webinar_approve_requests($form)) {
            add_to_log($course->id, 'webinar', 'approve requests', "view.php?id=$cm->id", $webinar->id, $cm->id);
        }
    }
    elseif (webinar_take_attendance($form)) {
        add_to_log($course->id, 'webinar', 'take attendance', "view.php?id=$cm->id", $webinar->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'webinar', 'take attendance (FAILED)', "view.php?id=$cm->id", $webinar->id, $cm->id);
    }
}

$pagetitle = format_string($webinar->name);
$navlinks[] = array('name' => get_string('modulenameplural', 'webinar'), 'link' => "index.php?id=$course->id", 'type' => 'title');
$navlinks[] = array('name' => $pagetitle, 'link' => "view.php?f=$webinar->id", 'type' => 'activityinstance');
$navlinks[] = array('name' => get_string('attendees', 'webinar'), 'link' => '', 'type' => 'title');
$navigation = build_navigation($navlinks);
/*print_header_simple($pagetitle, '', $navigation, '', '', true,
                    update_module_button($cm->id, $course->id, get_string('modulename', 'webinar')), navmenu($course, $cm));*/

$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->set_url('/attendees.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions);
echo $OUTPUT->header();

if ($takeattendance && !has_capability('mod/webinar:takeattendance', $context)) {
    $takeattendance = 0;
}

// Check the session has already started
if ($takeattendance && !webinar_has_session_started($session, time())) {
    error('Can not take attendance for a session that has not yet started', 'attendees.php?s='.$session->id);
    exit();
}

$heading = '';
if ($takeattendance) {
    $heading = get_string('takeattendance', 'webinar');
}
else {
    add_to_log($course->id, 'webinar', 'view attendees', "view.php?id=$cm->id", $webinar->id, $cm->id);
    $heading = get_string('attendees', 'webinar');
}
$heading .= ' - ' . format_string($webinar->name);

//JoeB - dev changes for Moodle 2.3 - replace references to deprecated functions print_box_start and print_heading()
//print_box_start();
//print_heading($heading, 'center');
echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($takeattendance) {
    echo '<form action="attendees.php?s='.$s.'" method="post">';
    echo '<p>'. get_string('attendanceinstructions', 'webinar');
    echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'" />';
    echo '<input type="hidden" name="s" value="'.$s.'" />';
    echo '<input type="hidden" name="backtoallsessions" value="'.$backtoallsessions.'" /></p>';
}
else {
    webinar_print_session($session, true);
}

//JoeB - dev upgrade for 2.3, replace deprecated print_table()
//$table = new object();
$table = new html_table();
$table->head = array(get_string('name'));
$table->summary = get_string('attendeestablesummary', 'webinar');
$table->align = array('left');
/*
$table->size = array('100%');
$table->width = '50%';
*/

foreach ($session->sessiondates as $dates) {
	$timefinish = $dates->timefinish;
}

if ($takeattendance) {
    $table->head[] = get_string('currentstatus', 'webinar');
    $table->align[] = array('center');
    $table->head[] = get_string('attendedsession', 'webinar');
    $table->align[] = array('center');
}
else if (time() >= $timefinish) {
	//compare the finish time to the current time - if the webinar has now finished, print out the attendance report
	$table->head[] = get_string('jointime', 'webinar');
    $table->align[] = array('center');
	$table->head[] = get_string('leavetime', 'webinar');
    $table->align[] = array('center');
	$table->head[] = get_string('attendedsession', 'webinar');
    $table->align[] = array('center');
}
else {
	//webinar has not yet started, or is in progress - just show the users who are booked to attend the course
    $table->head[] = get_string('attendance', 'webinar');
    $table->align[] = array('center');
}

$status_options = array();
foreach ($WEBINAR_STATUS as $key => $value) {
    if ($key <= WEBINAR_STATUS_BOOKED) {
        continue;
    }

    $status_options[$key] = get_string('status_'.$value, 'webinar');
}

if ($attendees = webinar_get_attendees($session->id)) {
    foreach($attendees as $attendee) {
        $data = array();
        $data[] = "<a href=\"$CFG->wwwroot/user/view.php?id={$attendee->id}&amp;course={$course->id}\">". format_string(fullname($attendee)).'</a>';

        if ($takeattendance) {
            // Show current status
            $data[] = get_string('status_'.webinar_get_status($attendee->statuscode), 'webinar');

            $optionid = 'submissionid_'.$attendee->submissionid;
            $status = $attendee->statuscode;
            $select = choose_from_menu($status_options, $optionid, $status, 'choose', '', '0', true);
            $data[] = $select;
        }
		else if (time() >= $timefinish) {
			//compare the finish time to the current time - if the webinar has now finished, print out the attendance report
			
            /*
			//Step 1 - get session value
			$url = $webinar->sitexmlapiurl . "?action=common-info";
			$xmlstr = file_get_contents($url);
			$xml = new SimpleXMLElement($xmlstr);
			//print_r($xml);
			$session_value = $xml->common->cookie;
            */

			//Step 2 - login
            /*
			$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session_value;
			$xmlstr = file_get_contents($url);
			$xml = new SimpleXMLElement($xmlstr);
			//print_r($xml);
            */

            $url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword;

            $ch=curl_init($url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $response = curl_exec($ch);
            curl_close($ch);

            $breeze_session_first_strip = strstr($response, 'BREEZESESSION');
            $breeze_session_second_strip = strstr($breeze_session_first_strip, ';', true);
            $breeze_session = str_replace('BREEZESESSION=', '', $breeze_session_second_strip);

            // Create a stream for HTTP headers, including the BREEZESESSION cookie
            $opts = array(
                      'http'=>array(
                        'method'=>"GET",
                        'header'=>"Cookie: " . $breeze_session_second_strip . "\r\n"
                      )
            );

            $context = stream_context_create($opts);



			$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $attendee->email; // . "&session=" . $session_value;
			$xmlstr = file_get_contents($url, false, $context);
			$xml = new SimpleXMLElement($xmlstr);
			//print_r($xml);
			
			if ($xml->{'principal-list'}->principal) {
				foreach($xml->{'principal-list'}->principal->attributes() as $key => $val) {
					if($key == 'principal-id') {
						$principal_id = $val;
					}
				}
			}

			//Step 4 - get a report of if/when this user has accessed the course - process the XML to get their join and leave times for the webinar session
			$url = $webinar->sitexmlapiurl . "?action=report-bulk-consolidated-transactions&filter-sco-id=" . $session->scoid . "&filter-principal-id=" . $principal_id; // . "&session=" . $session_value;
			//echo $url;
			//echo "<br/>";
			
			$xmlstr = file_get_contents($url, false, $context);
			$xml = new SimpleXMLElement($xmlstr);
			//print_r($xml);
			//echo "<br/><br/>";
			
			if($xml->{'report-bulk-consolidated-transactions'}->row) {
			
				$user_joined = true;
			
				foreach ($xml->{'report-bulk-consolidated-transactions'}->row as $user_access) {
					$tdate_joined = $user_access->{'date-created'};
					$tdate_left = $user_access->{'date-closed'};
				}
				
				//format the AdobeConnect dates into module standard format for display
				$datetime_joined = explode("T", $tdate_joined);
				$datecomponents_joined = explode("-", $datetime_joined[0]);
				$timecomponents_joined = explode(":", $datetime_joined[1]);
				$timestamp_joined = mktime($timecomponents_joined[0], $timecomponents_joined[1], 0, $datecomponents_joined[1], $datecomponents_joined[2], $datecomponents_joined[0]);
				$date_joined = date('d F Y h:i A', $timestamp_joined);
				
				$datetime_left = explode("T", $tdate_left);
				$datecomponents_left = explode("-", $datetime_left[0]);
				$timecomponents_left = explode(":", $datetime_left[1]);
				$timestamp_left = mktime($timecomponents_left[0], $timecomponents_left[1], 0, $datecomponents_left[1], $datecomponents_left[2], $datecomponents_left[0]);
				$date_left = date('d F Y h:i A', $timestamp_left);
			}
			else {
				$user_joined = false;
			}
			
			//prevent display of Unix epoch if user never accessed the session
			if ((!$user_joined) || (strstr($date_joined, "01 January 1970"))) {
				$data[] = "Did not attend";
				$data[] = "-";
			}
			else {
				$data[] = $date_joined;
				$data[] = $date_left;
			}
			
			foreach($status_options as $key=>$val) {
				if($key == $attendee->statuscode) {
					$data[] = $val;
				}
			}
			
			
		}
        else {
            $data[] = str_replace(' ', '&nbsp;', get_string('status_'.webinar_get_status($attendee->statuscode), 'webinar'));
        }
        $table->data[] = $data;
    }

    //print_table($table);
	echo html_writer::table($table);
}
else {
	//JoeB - dev changes for Moodle 2.3 - replace references to deprecated function print_heading()
    //print_heading(get_string('nosignedupusers', 'webinar'));
	echo $OUTPUT->heading(get_string('nosignedupusers', 'webinar'));
}

if ($takeattendance) {
    echo '<p>';
    echo '<input type="submit" value="'.get_string('saveattendance', 'webinar').'" />';
    echo '&nbsp;<input type="submit" name="cancelform" value="'.get_string('cancel').'" />';
    echo '</p></form>';
}
else {
    // Actions
    print '<p align="center">';
    if (has_capability('mod/webinar:takeattendance', $context)) {
		
        if (!$takeattendance && !empty($attendees) && webinar_has_session_started($session, time())) {
            // Take attendance
            echo '<a href="attendees.php?s='.$session->id.'&amp;takeattendance=1&amp;backtoallsessions='.$backtoallsessions.'">'.get_string('takeattendance', 'webinar').'</a> - ';
        }
    }

    if (has_capability('mod/webinar:addattendees', $context) ||
        has_capability('mod/webinar:removeattendees', $context)) {
        // Add/remove attendees
        echo '<a href="editattendees.php?s='.$session->id.'&amp;backtoallsessions='.$backtoallsessions.'">'.get_string('addremoveattendees', 'webinar').'</a> - ';
    }

    // Go back
    $url = "$CFG->wwwroot/course/view.php?id=$course->id";
    if ($backtoallsessions) {
        $url = "view.php?f={$webinar->id}&amp;backtoallsessions=$backtoallsessions";
    }
    print '<a href="'.$url.'">'.get_string('goback', 'webinar').'</a></p>';
}

// View unapproved requests
if (!$takeattendance && ($attendees = webinar_get_requests($session->id))) {

    echo '<br id="unapproved" />';
    echo $OUTPUT->heading(get_string('unapprovedrequests', 'webinar'));

    echo '<form action="attendees.php?s='.$s.'" method="post">';
    echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'" />';
    echo '<input type="hidden" name="s" value="'.$s.'" />';
    echo '<input type="hidden" name="backtoallsessions" value="'.$backtoallsessions.'" /></p>';

    //$table = new object();
	$table = new html_table();
    $table->summary = get_string('requeststablesummary', 'webinar');
    $table->head = array(get_string('name'), get_string('timerequested', 'webinar'),
                         get_string('decidelater', 'webinar'), get_string('decline', 'webinar'), get_string('approve', 'webinar'));
    $table->align = array('left', 'center', 'center', 'center', 'center');

    $cantakeattendance = has_capability('mod/webinar:takeattendance', $context);
    foreach($attendees as $attendee) {

        // Check the logged in user has permissions to see the user
        if (!$cantakeattendance) {
            if (webinar_get_manageremail($attendee->id) !== $USER->email) {
                continue;
            }
        }

        $data = array();
        $data[] = "<a href=\"{$CFG->wwwroot}/user/view.php?id={$attendee->id}&amp;course={$course->id}\">". format_string(fullname($attendee)).'</a>';
        $data[] = userdate($attendee->timerequested, get_string('strftimedatetime'));
        $data[] = '<input type="radio" name="requests['.$attendee->id.']" value="0" checked="checked" />';
        $data[] = '<input type="radio" name="requests['.$attendee->id.']" value="1" />';
        $data[] = '<input type="radio" name="requests['.$attendee->id.']" value="2" />';
        $table->data[] = $data;
    }

    if (empty($table->data)) {
        $table->data[] = array(get_string('noactionableunapprovedrequests', 'webinar'), '', '');
    }

    //print_table($table);
	echo html_writer::table($table);

    echo '<p><input type="submit" value="Update requests" /></p>';
    echo '</form>';
}

// View cancellations
if (!$takeattendance and has_capability('mod/webinar:viewcancellations', $context) and
    ($attendees = webinar_get_cancellations($session->id))) {

    echo '<br />';
    echo $OUTPUT->heading(get_string('cancellations', 'webinar'));

    //$table = new object();
	$table = new html_table();
    $table->summary = get_string('cancellationstablesummary', 'webinar');
    $table->head = array(get_string('name'), get_string('timesignedup', 'webinar'),
                         get_string('timecancelled', 'webinar'), get_string('cancelreason', 'webinar'));
    $table->align = array('left', 'center', 'center');

    foreach($attendees as $attendee) {
        $data = array();
        $data[] = "<a href=\"$CFG->wwwroot/user/view.php?id={$attendee->id}&amp;course={$course->id}\">". format_string(fullname($attendee)).'</a>';
        $data[] = userdate($attendee->timesignedup, get_string('strftimedatetime'));
        $data[] = userdate($attendee->timecancelled, get_string('strftimedatetime'));
        $data[] = format_string($attendee->cancelreason);
        $table->data[] = $data;
    }
    //print_table($table);
	echo html_writer::table($table);
}

//JoeB - dev changes for Moodle 2.3 - replace references to deprecated functions print_box_end and print_footer
//print_box_end();
//print_footer($course);
echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

function webinar_get_cancellations($sessionid)
{
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    // Nasty SQL follows:
    // Load currently cancelled users,
    // include most recent booked/waitlisted time also
    $sql = "
            SELECT
                su.id AS signupid,
                u.id,
                u.firstname,
                u.lastname,
                MAX(ss.timecreated) AS timesignedup,
                c.timecreated AS timecancelled,
                c.note AS cancelreason
            FROM
                {$CFG->prefix}webinar_signups su
            JOIN
                {$CFG->prefix}user u
             ON u.id = su.userid
            JOIN
                {$CFG->prefix}webinar_signups_status c
             ON su.id = c.signupid
            AND c.statuscode = ".WEBINAR_STATUS_USER_CANCELLED."
            AND c.superceded = 0
            LEFT JOIN
                {$CFG->prefix}webinar_signups_status ss
             ON su.id = ss.signupid
             AND ss.statuscode IN (
                 ".WEBINAR_STATUS_BOOKED.",
                 ".WEBINAR_STATUS_WAITLISTED.",
                 ".WEBINAR_STATUS_REQUESTED."
             )
            AND ss.superceded = 1
            WHERE
                su.sessionid = {$sessionid}
            GROUP BY
                su.id,
                u.id,
                u.firstname,
                u.lastname,
                c.timecreated,
                c.note
            ORDER BY
                {$fullname},
                c.timecreated
    ";
    return $DB->get_records_sql($sql);
}

function webinar_get_requests($sessionid)
{
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $sql = "SELECT su.id AS signupid, u.id, u.firstname, u.lastname,
                   ss.timecreated AS timerequested
              FROM {$CFG->prefix}webinar_signups su
              JOIN {$CFG->prefix}webinar_signups_status ss ON su.id=ss.signupid
              JOIN {$CFG->prefix}user u ON u.id = su.userid
             WHERE su.sessionid = $sessionid AND ss.superceded != 1 AND ss.statuscode = ".WEBINAR_STATUS_REQUESTED."
          ORDER BY $fullname, ss.timecreated";
    return $DB->get_records_sql($sql);
}

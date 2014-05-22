<?php

require_once '../../config.php';
require_once 'lib.php';

$PAGE->set_url($CFG->wwwroot.$SCRIPT);

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // webinar ID
$location = optional_param('location', '', PARAM_TEXT); // location
$download = optional_param('download', '', PARAM_ALPHA); // download attendance

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
elseif ($f) {
    if (!$webinar = $DB->get_record('webinar', array('id' => $f))) {
        print_error('error:incorrectwebinarid', 'webinar');
    }
    if (!$course = $DB->get_record('course', array('id' => $webinar->course))) {
        print_error('error:coursemisconfigured', 'webinar');
    }
    if (!$cm = get_coursemodule_from_instance('webinar', $webinar->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'webinar');
    }
}
else {
    print_error('error:mustspecifycoursemodulewebinar', 'webinar');
}

//$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$context = context_module::instance($cm->id);

if (!empty($download)) {
    require_capability('mod/webinar:viewattendees', $context);
    webinar_download_attendance($webinar->name, $webinar->id, $location, $download);
    exit();
}

require_course_login($course);
require_capability('mod/webinar:view', $context);

add_to_log($course->id, 'webinar', 'view', "view.php?id=$cm->id", $webinar->id, $cm->id);

$PAGE->set_pagetype('webinar');
$PAGE->set_title($webinar->name);
$PAGE->set_heading($webinar->name);
$PAGE->navbar->add($webinar->name);
echo $OUTPUT->header();

if (empty($cm->visible) and !has_capability('mod/webinar:viewemptyactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}

//print_box_start();
$OUTPUT->box_start();

echo $OUTPUT->heading($webinar->name);

echo '<table align="center">';

if ($webinar->description !== "") {
    echo '<tr>
        <td valign="top">Description:</td>
        <td width="20px">&nbsp;</td>
        <td valign="top">' . $webinar->description . '</td>
        </tr>';
}
if ($webinar->agenda !== "") {
    echo '<tr>
        <td valign="top">Agenda:</td>
        <td width="20px">&nbsp;</td>
        <td valign="top">' . $webinar->agenda . '</td>
        </tr>';
}

echo '</table>';

$locations = get_locations($webinar->id);
if (count($locations) > 2) {
    echo '<form method="get" action="view.php">';
    echo '<div><input type="hidden" name="f" value="'.$webinar->id.'"/>';
    choose_from_menu($locations, 'location', $location, '');
    echo '<input type="submit" value="'.get_string('showbylocation','webinar').'"/>';
    echo '</div></form>';
}

print_session_list($course->id, $webinar->id, $location, $webinar);

$OUTPUT->box_end();

echo $OUTPUT->footer();

function print_session_list($courseid, $webinarid, $location, $webinar)
{
    global $USER;
    global $CFG;
	global $DB;
	global $OUTPUT;

    $timenow = time();

    //$context = get_context_instance(CONTEXT_COURSE, $courseid, $USER->id);
    $context = context_course::instance($courseid, $USER->id);

    $viewattendees = has_capability('mod/webinar:viewattendees', $context);
	
    $editsessions = has_capability('mod/webinar:editsessions', $context);

    $customfields = webinar_get_session_customfields();

    // Table headers
    $tableheader = array();
    foreach ($customfields as $field) {
        if (!empty($field->showinsummary)) {
            $tableheader[] = format_string($field->name);
        }
    }
	
	echo '
	<style type="text/css">
		.generaltable th {
			text-align: left;
		}
	</style>
	';
	
	$tableheader[] = get_string('startdatetime', 'webinar');
	$tableheader[] = get_string('finishdatetime', 'webinar');
	$tableheader[] = get_string('presenter', 'webinar');
    $tableheader[] = get_string('maximumattendees', 'webinar');
	$tableheader[] = get_string('confirmed', 'webinar');
    $tableheader[] = get_string('status', 'webinar');
    $tableheader[] = get_string('options', 'webinar');

    $upcomingdata = array();
    $upcomingtbddata = array();
    $previousdata = array();
    $upcomingrowclass = array();
    $upcomingtbdrowclass = array();
    $previousrowclass = array();

    if ($sessions = webinar_get_sessions($webinarid, $location) ) {
        foreach($sessions as $session) {
            $sessionrow = array();

            $sessionstarted = false;
            $sessionfull = false;
            $sessionwaitlisted = false;
            $isbookedsession = false;
			
			$bookedsession = null;
			if ($submissions = webinar_session_get_user_submissions($webinarid, $USER->id, $session->id)) {
				$submission = array_shift($submissions);
				$bookedsession = $submission;
			}

            // Custom fields
            $customdata = $DB->get_records('webinar_session_data', array('sessionid' => $session->id), 'fieldid, data');
            foreach ($customfields as $field) {
                if (empty($field->showinsummary)) {
                    continue;
                }

                if (empty($customdata[$field->id])) {
                    $sessionrow[] = '&nbsp;';
                }
                else {
                    $sessionrow[] = format_string($customdata[$field->id]->data);
                }
            }

			foreach ($session->sessiondates as $date) {
                $sessionstart = date('d F Y h:i A', $date->timestart);
				$sessionfinish = date('d F Y h:i A', $date->timefinish);
            }
			 
			 $sessionrow[] = $sessionstart;
			 $sessionrow[] = $sessionfinish;
			 
			 $presenter_name = $DB->get_records_sql("SELECT
                        u.firstname,
                        u.lastname
                    FROM 
						{$CFG->prefix}user u 
                    WHERE 
						u.id = $session->presenter 
					LIMIT 1");

				
			 if($presenter_name) {
				foreach($presenter_name as $pname) {
					$presenter = $pname->firstname . " " . $pname->lastname;
				}
			 }
			 $sessionrow[] = $presenter;

            // Capacity
            $signupcount = webinar_get_num_attendees($session->id);

            $sessionrow[] = $session->capacity;
			$sessionrow[] = $signupcount;

            // Status
            $status  = get_string('bookingopen', 'webinar');
            if (webinar_has_session_started($session, $timenow) && webinar_is_session_in_progress($session, $timenow)) {

				$status = get_string('sessioninprogress', 'webinar');
                $sessionstarted = true;
            }
            elseif (webinar_has_session_started($session, $timenow)) {

				$status = get_string('closed', 'webinar');
                $sessionstarted = true;
            }

			elseif ($bookedsession) {
                $signupstatus = webinar_get_status($bookedsession->statuscode);

                $status = get_string('status_'.$signupstatus, 'webinar');
                $isbookedsession = true;
            }
            elseif ($signupcount >= $session->capacity) {
				$status = get_string('bookingfull', 'webinar');
                $sessionfull = true;
            }

            $sessionrow[] = $status;

            /*
			//Get session value
			$url = $webinar->sitexmlapiurl . "?action=common-info";
			$xmlstr = file_get_contents($url);
			$xml = new SimpleXMLElement($xmlstr);
			$session_value = $xml->common->cookie;

			//Login user
			$url = $webinar->sitexmlapiurl . "?action=login&login=" . $USER->email . "&password=" . $webinar->adminpassword . "&session=" . $session_value;
			$xmlstr = file_get_contents($url);
			$xml = new SimpleXMLElement($xmlstr);
            */

            $url = $webinar->sitexmlapiurl . "?action=login&login=" . $USER->email . "&password=" . $webinar->adminpassword;

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





			$meetingurl = str_replace('/api/xml', '', $webinar->sitexmlapiurl) . $session->urlpath;
			$meetingurlwithsession = $meetingurl . '?session=' . $breeze_session; //$session_value;
			
            // Options
            $options = '';
            if ($editsessions) {
                $options .= ' <a href="sessions.php?s='.$session->id.'" title="'.get_string('editsession', 'webinar').'">'
                    . '<img src="'.$CFG->wwwroot.'/pix/t/edit.gif" class="iconsmall" alt="'.get_string('edit', 'webinar').'" /></a> '
                    . '<a href="sessions.php?s='.$session->id.'&amp;c=1" title="'.get_string('copysession', 'webinar').'">'
                    . '<img src="'.$CFG->wwwroot.'/pix/t/copy.gif" class="iconsmall" alt="'.get_string('copy', 'webinar').'" /></a> '
                    . '<a href="sessions.php?s='.$session->id.'&amp;d=1" title="'.get_string('deletesession', 'webinar').'">'
                    . '<img src="'.$CFG->wwwroot.'/pix/t/delete.gif" class="iconsmall" alt="'.get_string('delete').'" /></a><br />';
            }
            if ($viewattendees){
                $options .= '<br/><a href="attendees.php?s='.$session->id.'&amp;backtoallsessions='.$webinarid.'" title="'.get_string('seeattendees', 'webinar').'">'.get_string('attendees', 'webinar').'</a><br />';
            }
			
			if($status == 'Closed') {
			
				//Give user permission to watch old recordings of a webinar by adding them as a participant
				//To do this, we must first get their principal ID if they are already added to Adobe Connect, or if not, add them first
				//Need to temporarily login as admin in order to do this
				
				/*
                $url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session_value;
				$xmlstr = file_get_contents($url);
				$xml = new SimpleXMLElement($xmlstr);
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

				$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $USER->email; //. "&session=" . $session_value;
				$xmlstr = file_get_contents($url, false, $context);
				$xml = new SimpleXMLElement($xmlstr);

				if ($xml->{'principal-list'}->principal) {
					//User email address has been matched on Adobe Connect - get back their principal ID
					foreach($xml->{'principal-list'}->principal->attributes() as $key => $val) {
						if($key == 'principal-id') {
							$principal_id = $val;
						}
					}
				}
				else {
					//User email address is not registered yet with Adobe Connect - add them and get back the principal ID
					$url = $webinar->sitexmlapiurl . "?action=principal-update&first-name=" . str_replace(' ', '%20', $USER->firstname) . "&last-name=" . str_replace(' ', '%20', $USER->lastname) . "&login=" . $USER->email . 
						"&password=" . $webinar->adminpassword . "&type=user&send-email=false&has-children=0&email=" . $USER->email; // . "&session=" . $session_value;
						
					$xmlstr = file_get_contents($url, false, $context);
					$xml = new SimpleXMLElement($xmlstr);

					foreach($xml->principal->attributes() as $key => $val) {
						if($key == 'principal-id') {
							$principal_id = $val;
						}
					}
				}
				
				//Now, add user as a meeting participant using the principalID obtained above
				$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $session->scoid . "&permission-id=view&session=" . $breeze_session; //$session_value;
				$xmlstr = file_get_contents($url, false, $context);
				$xml = new SimpleXMLElement($xmlstr);
				
				//Login BACK in as user, after having logged in as admin to add current user as participant
				/*
                $url = $webinar->sitexmlapiurl . "?action=login&login=" . $USER->email . "&password=" . $webinar->adminpassword . "&session=" . $session_value;
				$xmlstr = file_get_contents($url);
				$xml = new SimpleXMLElement($xmlstr);
                */

                $url = $webinar->sitexmlapiurl . "?action=login&login=" . $USER->email . "&password=" . $webinar->adminpassword;
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

				//get back the recording URL path for the meeting SCO ID
				$url = $webinar->sitexmlapiurl . "?action=sco-contents&sco-id=" . $session->scoid . "&filter-icon=archive"; //&session=" . $session_value;
				$xmlstr = file_get_contents($url, false, $context);
				$xml = new SimpleXMLElement($xmlstr);
				
				foreach ($xml->scos->sco as $sco) {
				
					$recording_urlpath = $sco->{'url-path'};
					$recordingurl = str_replace('/api/xml', '', $webinar->sitexmlapiurl) . $recording_urlpath;
					$recordingurlwithsession = $recordingurl . '?session=' . $breeze_session; //$session_value;
					
					$options .= '<a href="'. $recordingurlwithsession .'" title="'.get_string('viewrecording', 'webinar').'" onClick="javascript:window.open(\'' . $recordingurlwithsession . '\', \'Breeze\', \'toolbar=no,menubar=no,width=1024,height=768,resizable=yes\'); return false">' . get_string('viewrecording', 'webinar') . '</a><br/>';
				}
			}

			//check if the user is the presenter/host of the session - if so, allow them to join the session as host as they are already registered with Adobe Connect
			if (($session->presenter == $USER->id) and (!$sessionstarted)) {
				$options .= '<a href="'. $meetingurlwithsession .'" title="'.get_string('joinwebinarashost', 'webinar').'" onClick="javascript:window.open(\'' . $meetingurlwithsession . '\', \'Breeze\', \'toolbar=no,menubar=no,width=1024,height=768,resizable=yes\'); return false">' . get_string('joinwebinarashost', 'webinar') . '</a><br/>';
				$ishost = true;
			}
			else {
				$ishost = false;
			}
			
            if ($isbookedsession) {
                $options .= '<a href="cancelsignup.php?s='.$session->id.'&amp;backtoallsessions='.$webinarid.'" title="'.get_string('cancelbooking', 'webinar').'">'.get_string('cancelbooking', 'webinar').'</a><br/>';
				$options .= '<a href="'. $meetingurlwithsession .'" title="'.get_string('joinwebinar', 'webinar').'" onClick="javascript:window.open(\'' . $meetingurlwithsession . '\', \'Breeze\', \'toolbar=no,menubar=no,width=1024,height=768,resizable=yes\'); return false">' . get_string('joinwebinar', 'webinar') . '</a>';
			}
			elseif (!$sessionstarted and !$ishost) {
                $options .= '<a href="signup.php?s='.$session->id.'&amp;backtoallsessions='.$webinarid.'">'.get_string('register', 'webinar').'</a>';
            }

            if (empty($options)) {
                $options = get_string('none', 'webinar');
            }
            $sessionrow[] = $options;

            // Set the CSS class for the row
            $rowclass = '';
            if ($sessionstarted) {
                $rowclass = 'dimmed_text';
            }
            elseif ($isbookedsession) {
                $rowclass = 'highlight';
            }
            elseif ($sessionfull) {
                $rowclass = 'dimmed_text';
            }

            // Put the row in the right table
            if ($sessionstarted) {
                $previousrowclass[] = $rowclass;
                $previousdata[] = $sessionrow;
            }
            elseif ($sessionwaitlisted) {
                $upcomingtbdrowclass[] = $rowclass;
                $upcomingtbddata[] = $sessionrow;
            }
            else { // Normal scheduled session
                $upcomingrowclass[] = $rowclass;
                $upcomingdata[] = $sessionrow;
            }
        }
    }

    // Upcoming sessions
    
	$OUTPUT->heading(get_string('upcomingsessions', 'webinar'));
	//print_heading(get_string('upcomingsessions', 'webinar')); //remove deprecated print_heading()
	
    if (empty($upcomingdata) and empty($upcomingtbddata)) {
		echo '<p align="center">' . get_string('noupcoming', 'webinar', $webinar->name) . '</p>';
    }
    else {
        //JoeB - dev upgrade for 2.3, replace deprecated print_table()
		//$upcomingtable = new object();
		$upcomingtable = new html_table();
		
        $upcomingtable->summary = get_string('upcomingsessionslist', 'webinar');
        $upcomingtable->head = $tableheader;
        $upcomingtable->rowclasses = array_merge($upcomingrowclass, $upcomingtbdrowclass);
        $upcomingtable->width = '100%';
        $upcomingtable->data = array_merge($upcomingdata, $upcomingtbddata);
        
		//print_table($upcomingtable);
		echo html_writer::table($upcomingtable);
    }

    if ($editsessions) {
        echo '<p align="center"><a href="sessions.php?f='.$webinarid.'">'.get_string('addsession', 'webinar').'</a></p>';
    }

    // Previous sessions
    if (!empty($previousdata)) {
        echo $OUTPUT->heading(get_string('previoussessions', 'webinar'));
        
		//$previoustable = new object();
		$previoustable = new html_table();
		
        $previoustable->summary = get_string('previoussessionslist', 'webinar');
        $previoustable->head = $tableheader;
        $previoustable->rowclasses = $previousrowclass;
        $previoustable->width = '100%';
        $previoustable->data = $previousdata;
		
        //print_table($previoustable);
		echo html_writer::table($previoustable);
    }
}

/**
 * Get webinar locations
 *
 * @param   interger    $webinarid
 * @return  array
 */
function get_locations($webinarid)
{
    global $CFG, $DB;

    $locationfieldid = $DB->get_field('webinar_session_field', 'id', array('shortname' => 'location'));
    if (!$locationfieldid) {
        return array();
    }

    $sql = "SELECT DISTINCT d.data AS location
              FROM {$CFG->prefix}webinar f
              JOIN {$CFG->prefix}webinar_sessions s ON s.webinar = f.id
              JOIN {$CFG->prefix}webinar_session_data d ON d.sessionid = s.id
             WHERE f.id = $webinarid AND d.fieldid = $locationfieldid";

    if ($records = $DB->get_records_sql($sql)) {
        $locationmenu[''] = get_string('alllocations', 'webinar');

        $i=1;
        foreach ($records as $record) {
            $locationmenu[$record->location] = $record->location;
            $i++;
        }

        return $locationmenu;
    }

    return array();
}

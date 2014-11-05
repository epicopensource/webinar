<?php

/* Calls to Adobe Connect module and updates the details of a given meeting / webinar session */

function update_meeting($webinar, $session_info, $date, $presenter_details) {

	/*
	//Step 1 - get session value
	$url = $webinar->sitexmlapiurl . "?action=common-info";
	$xmlstr = file_get_contents($url);
	$xml = new SimpleXMLElement($xmlstr);
	$session = $xml->common->cookie;

	//Step 2 - login
	$url = $webinar->sitexmlapiurl . "?action=login&login=" . $webinar->adminemail . "&password=" . $webinar->adminpassword . "&session=" . $session;
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

	//Step 3 - update the meeting room
	//Format the Moodle dates to update Adobe Connect
	$datebegin = date('Y-m-d', $date->timestart);
	$timebegin = date('H:i', $date->timestart);
	$datetimebegin = $datebegin . "T" . $timebegin;

	$dateend = date('Y-m-d', $date->timefinish);
	$timeend = date('H:i', $date->timefinish);
	$datetimeend = $dateend . "T" . $timeend;

	$meetingname = str_replace(' ', '%20', $webinar->name . ' ' . $datebegin . ' ' . $timebegin);

	$scoid = $session_info->scoid;

	$url = $webinar->sitexmlapiurl . "?action=sco-update&type=meeting&name=" . $meetingname .
				"&sco-id=" . $scoid . "&date-begin=" . $datetimebegin . "&date-end=" . $datetimeend . "&url-path="; //&session=" . $session;

	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);

	//Step 4 - update/add a presenter - pass the presenter email address, check if it exists on Adobe Connect already - if not, add a new user to the system, otherwise get their permission ID
	$url = $webinar->sitexmlapiurl . "?action=principal-list&filter-email=" . $presenter_details->email; // . "&session=" . $session;
	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);

	if ($xml->{'principal-list'}->principal) {
		//Presenter email address has been matched on Adobe Connect - get back their principal ID
		foreach($xml->{'principal-list'}->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}
	else {
		//Presenter email address is not registered yet with Adobe Connect - add them and get back the principal ID
		$url = $webinar->sitexmlapiurl . "?action=principal-update&first-name=" . str_replace(' ', '%20', $presenter_details->firstname) . "&last-name=" . str_replace(' ', '%20', $presenter_details->lastname) . "&login=" . $presenter_details->email .
			"&password=" . $webinar->adminpassword . "&type=user&send-email=false&has-children=0&email=" . $presenter_details->email; // . "&session=" . $session;

		$xmlstr = file_get_contents($url, false, $context);
		$xml = new SimpleXMLElement($xmlstr);

		foreach($xml->principal->attributes() as $key => $val) {
			if($key == 'principal-id') {
				$principal_id = $val;
			}
		}
	}

	//take the presenter user's principal ID and assign them as presenter of the session
	$url = $webinar->sitexmlapiurl . "?action=permissions-update&principal-id=" . $principal_id . "&acl-id=" . $scoid . "&permission-id=host"; //&session=" . $session;

	$xmlstr = file_get_contents($url, false, $context);
	$xml = new SimpleXMLElement($xmlstr);


}